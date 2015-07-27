<?php

namespace TreeHouse\BehatCommon;

use Behat\Symfony2Extension\Context\KernelAwareContext;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit_Framework_Assert as Assert;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class DoctrineOrmContext extends AbstractPersistenceContext implements KernelAwareContext
{
    use KernelAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var string
     */
    private $defaultPrefix;

    /**
     * @param EntityManagerInterface|null $entity_manager
     * @param string                      $default_prefix
     */
    public function __construct(
        EntityManagerInterface $entity_manager = null,
        $default_prefix = 'AppBundle'
    ) {
        $this->entityManager = $entity_manager;
        $this->defaultPrefix = $default_prefix;
    }

    /**
     * @inheritdoc
     */
    protected function persistData($name, array $data)
    {
        $alias = $this->convertNameToAlias($this->singularize($name));

        foreach ($data as $row) {
            $row = $this->applyMapping($this->getFieldMapping($alias), $row);
            $row = $this->rowToEntityData($alias, $row, true);
            $this->persistEntityData($alias, $row);
        }
    }

    /**
     * @inheritdoc
     */
    public function assertDataPersisted($name, array $data)
    {
        $alias = $this->convertNameToAlias($this->singularize($name));

        foreach ($data as $row) {
            $criteria = $this->applyMapping($this->getFieldMapping($alias), $row);
            $criteria = $this->rowToEntityData($alias, $criteria, false);
            $entity = $this->getEntityManager()->getRepository($alias)->findOneBy($criteria);

            Assert::assertNotNull(
                $entity,
                sprintf(
                    'The repository should find data of type "%s" with these criteria: %s',
                    $alias,
                    json_encode($criteria)
                )
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function assertDataNotPersisted($name, array $data)
    {
        $alias = $this->convertNameToAlias($this->singularize($name));

        foreach ($data as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($alias), $criteria);
            $criteria = $this->rowToEntityData($alias, $criteria, false);
            $entity = $this->getEntityManager()->getRepository($alias)->findOneBy($criteria);

            Assert::assertNull(
                $entity,
                sprintf(
                    'The repository should not find data of type "%s" with these criteria: %s',
                    $alias,
                    json_encode($criteria)
                )
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function purgeDatabase()
    {
        $purger = new ORMPurger($this->getEntityManager());
        $purger->purge();
    }

    /**
     * Converts a fixture's values to ones that are expected by their entity's configuration
     *
     * @todo Convert embedded objects to their proper entity objects (e.g.: {"title": "Foobar"})
     *
     * @param string $entityName
     * @param array $row
     *
     * @return array
     */
    protected function transformEntityValues($entityName, array $row)
    {
        $meta = $this->getEntityManager()->getClassMetadata($entityName);
        foreach ($row as $property => $value) {
            $propertyName = Inflector::camelize($property);
            $fieldType    = $meta->getTypeOfField($propertyName);

            if (mb_strtolower($value) === 'null') {
                $value = null;
            }

            switch ($fieldType) {
                case 'array':
                case 'json_array':
                    $value = json_decode($value, true);
                    break;
                case 'date':
                case 'datetime':
                    if (!empty($value)) {
                        $value = new \DateTime($value);
                    } else {
                        $value = null;
                    }
                    break;
                case null:
                    if ($value && $meta->hasAssociation($propertyName)) {
                        if (is_array($jsonValue = json_decode($value, true))) {
                            $criteria = $jsonValue;
                        } else {
                            $criteria = ['id' => $value];
                        }

                        $class           = $meta->getAssociationTargetClass($propertyName);
                        $associatedValue = $this->getEntityManager()->getRepository($class)->findOneBy($criteria);

                        if ($associatedValue === null) {
                            throw new \RuntimeException(sprintf('There is no %s entity with ID %s to associate with this %s entity', $class, $value, $entityName));
                        }

                        $value = $associatedValue;
                    }
                    break;
            }

            unset($row[$property]);

            $row[$propertyName] = $value;
        }

        return $row;
    }

    /**
     * @param string $entityName
     * @param array  $row
     * @param bool   $useDefaults
     *
     * @return array
     */
    protected function rowToEntityData($entityName, array $row, $useDefaults = true)
    {
        if ($useDefaults) {
            $row = array_merge($this->getDefaultFixture($entityName), $row);
        }

        $this->transformFixture($entityName, $row);
        $row = $this->transformEntityValues($entityName, $row);

        return $row;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function convertNameToAlias($name)
    {
        if (stristr($name, ':')) {
            // already aliased
            return $name;
        }

        $alias = ucwords($name);
        $alias = str_replace(' ', '', $alias);
        $alias = sprintf('%s:%s', $this->defaultPrefix, $alias);

        return $alias;
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        if (!$this->entityManager) {
            $this->entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        }

        return $this->entityManager;
    }

    /**
     * @param string $alias
     * @param array  $entityData
     */
    protected function persistEntityData($alias, array $entityData)
    {
        $entity = $this->entityDataToEntity($alias, $entityData);
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush($entity);
        $em->refresh($entity);
        $em->clear($entity);
    }

    /**
     * @param string $alias
     * @param array  $entityData
     *
     * @return object
     */
    protected function entityDataToEntity($alias, array $entityData)
    {
        $class = $this->getEntityManager()->getClassMetadata($alias)->getName();
        $object = new $class();

        $accessor = new PropertyAccessor();
        foreach ($entityData as $key => $value) {
            if ($key === 'id') {
                $this->setId($object, $value);
                continue;
            }

            $accessor->setValue($object, $key, $value);
        }

        return $object;
    }

    /**
     * @param object $object
     * @param mixed  $id
     */
    protected function setId($object, $id)
    {
        // use reflection to set the id (we shouldn't have a setter for this)
        $reflectionClass = new \ReflectionClass($object);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $id);
    }
}
