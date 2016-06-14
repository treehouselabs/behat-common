<?php

namespace TreeHouse\BehatCommon;

use Behat\Symfony2Extension\Context\KernelAwareContext;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit_Framework_Assert as Assert;
use Symfony\Component\PropertyAccess\PropertyAccess;
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
        $meta = $this->getEntityManager()->getClassMetadata($alias);

        // remember generator as it could change during persisting
        $generatorType = $meta->generatorType;
        $generator = $meta->idGenerator;
        $class = $meta->getName();

        foreach ($data as $row) {
            $row = $this->applyMapping($this->getFieldMapping($class), $row);
            $row = $this->rowToEntityData($class, $row, true);
            $this->persistEntityData($class, $row);
        }

        // set back generator
        $meta->setIdGeneratorType($generatorType);
        $meta->setIdGenerator($generator);
    }

    /**
     * @inheritdoc
     */
    public function assertDataPersisted($name, array $data)
    {
        $alias = $this->convertNameToAlias($this->singularize($name));
        $class = $this->getEntityManager()->getClassMetadata($alias)->getName();
        $found = [];

        foreach ($data as $row) {
            $criteria = $this->applyMapping($this->getFieldMapping($class), $row);
            $criteria = $this->rowToEntityData($class, $criteria, false);

            $jsonArrayFields = $this->getJsonArrayFields($class, $criteria);
            $criteria = array_diff_key($criteria, $jsonArrayFields);

            $entity = $this->getEntityManager()->getRepository($class)->findOneBy($criteria);

            Assert::assertNotNull(
                $entity,
                sprintf(
                    'The repository should find data of type "%s" with these criteria: %s',
                    $alias,
                    json_encode($criteria)
                )
            );

            if (!empty($jsonArrayFields)) {
                // refresh json fields as they may have changed beforehand
                $this->getEntityManager()->refresh($entity);

                // json array fields can not be matched by the ORM (depends on driver and requires driver-specific operators),
                // therefore we need to check these separately
                $accessor = PropertyAccess::createPropertyAccessor();
                foreach ($jsonArrayFields as $field => $value) {
                    Assert::assertSame($value, $accessor->getValue($entity, $field));
                }
            }

            $found[] = $entity;
        }

        return $found;
    }

    /**
     * @inheritdoc
     */
    public function assertDataNotPersisted($name, array $data)
    {
        $alias = $this->convertNameToAlias($this->singularize($name));
        $class = $this->getEntityManager()->getClassMetadata($alias)->getName();

        foreach ($data as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($class), $criteria);
            $criteria = $this->rowToEntityData($class, $criteria, false);
            $entity = $this->getEntityManager()->getRepository($class)->findOneBy($criteria);

            Assert::assertNull(
                $entity,
                sprintf(
                    'The repository should not find an instance of "%s" with these criteria: %s',
                    $class,
                    json_encode($criteria)
                )
            );
        }
    }

    /**
     * @param string $class
     */
    public function ensureAssignedIdGenerator($class)
    {
        $manager = $this->getEntityManager();
        $meta = $manager->getClassMetadata($class);

        if ($meta->generatorType !== ClassMetadata::GENERATOR_TYPE_NONE) {
            $meta->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
            $meta->setIdGenerator(new AssignedGenerator());
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
     * Converts a fixture's values to ones that are expected by their entity's configuration.
     *
     * @todo Convert embedded objects to their proper entity objects (e.g.: {"title": "Foobar"})
     *
     * @param string $entityName
     * @param array  $row
     *
     * @return array
     */
    protected function transformEntityValues($entityName, array $row)
    {
        $meta = $this->getEntityManager()->getClassMetadata($entityName);
        foreach ($row as $property => $value) {
            $propertyName = Inflector::camelize($property);
            $fieldType = $meta->getTypeOfField($propertyName);

            unset($row[$property]);

            $row[$propertyName] = $value;

            if (is_object($value)) {
                continue;
            }

            if (mb_strtolower($value) === 'null') {
                $value = null;
            }

            switch ($fieldType) {
                case 'array':
                case 'json_array':
                    $value = json_decode($value, true);
                    break;
				case 'boolean':
					if (in_array($value, ['true', '1', 1])) {
						$value = true;
					} elseif (in_array($value, ['false', '0', 0])) {
						$value = false;
					}
					break;
                case 'date':
                case 'datetime':
                    if (!empty($value)) {
                        $value = new \DateTime($value);
                    } else {
                        $value = null;
                    }
                    break;
                case 'datetimetz':
                    if (!empty($value)) {
                        $value = new \DateTime($value, new \DateTimeZone('UTC'));
                    } else {
                        $value = null;
                    }
                    break;
                case null:
                    if ($value && $meta->hasAssociation($propertyName)) {
                        $class = $meta->getAssociationTargetClass($propertyName);

                        if (is_array($jsonValue = json_decode($value, true))) {
                            $criteria = $jsonValue;
                        } else {
                            $criteria = [$this->getDefaultIdentifier($class) => $value];
                        }

                        $associatedValue = $this->getEntityManager()->getRepository($class)->findOneBy($criteria);

                        if ($associatedValue === null) {
                            throw new \RuntimeException(sprintf('There is no %s entity with ID %s to associate with this %s entity', $class, $value, $entityName));
                        }

                        $value = $associatedValue;
                    }
                    break;
            }

            $row[$propertyName] = $value;
        }

        return $row;
    }

    /**
     * @param string $class
     * @param array  $row
     * @param bool   $useDefaults
     *
     * @return array
     */
    protected function rowToEntityData($class, array $row, $useDefaults = true)
    {
        if ($useDefaults) {
            $row = array_merge($this->getDefaultFixture($class), $row);
        }

        $this->transformFixture($class, $row);
        $row = $this->transformEntityValues($class, $row);

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
     * @param string $class
     * @param array  $entityData
     */
    protected function persistEntityData($class, array $entityData)
    {
        $entity = $this->entityDataToEntity($class, $entityData);
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush($entity);
        //$em->refresh($entity);
        $em->clear(get_class($entity));
    }

    /**
     * @param string $class
     * @param array  $entityData
     *
     * @return object
     */
    protected function entityDataToEntity($class, array $entityData)
    {
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
        $this->ensureAssignedIdGenerator(get_class($object));

        // use reflection to set the id (we shouldn't have a setter for this)
        $reflectionClass = new \ReflectionClass($object);
        $reflectionProperty = $reflectionClass->getProperty('id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $id);
    }

    /**
     * @param string $class
     * @param array $fields
     *
     * @return array
     */
    private function getJsonArrayFields($class, array $fields)
    {
        $jsonFields = [];
        $metadata = $this->getEntityManager()->getClassMetadata($class);
        foreach ($metadata->getFieldNames() as $field) {
            if (array_key_exists($field, $fields)) {
                $mapping = $metadata->getFieldMapping($field);
                if ($mapping['type'] == 'json_array') {
                    $jsonFields[$field] = $fields[$field];
                }
            }
        }

        return $jsonFields;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    protected function getDefaultIdentifier(string $class) : string
    {
        $ids = $this->getEntityManager()->getClassMetadata($class)->getIdentifierFieldNames();

        return reset($ids);
    }
}
