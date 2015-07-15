<?php

namespace TreeHouse\BehatCommon;

use Doctrine\Common\Inflector\Inflector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit_Framework_Assert as Assert;

class PDOContext extends AbstractPersistenceContext
{
    /**
     * @var string
     */
    private $dsn;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var array
     */
    private $options;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param string      $dsn
     * @param string      $username
     * @param string      $password
     * @param array       $options
     * @param string|null $purge_database_tag
     */
    public function __construct($dsn, $username, $password, array $options = [], $purge_database_tag = null)
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;

        parent::__construct($purge_database_tag);
    }

    /**
     * @param string $name
     * @param array  $data
     */
    protected function persistData($name, array $data)
    {
        $table = $this->convertNameToTable($this->singularify($name));

        $this->persistRows($table, $data);
    }

    /**
     * @param string $name
     * @param array  $data
     */
    protected function assertDataPersisted($name, array $data)
    {
        $table = $this->convertNameToTable($this->singularify($name));

        $this->assertRowsHaveBeenPersisted($table, $data);
    }

    /**
     * @Then /^the following (.*?) should NOT exist:$/
     *
     * {@inheritdoc}
     */
    protected function assertDataNotPersisted($name, array $data)
    {
        $alias = $this->convertNameToTable($this->singularify($name));

        $this->assertRowsHaveNotBeenPersisted($alias, $data);
    }

    /**
     * @param string $table
     * @param array  $data
     *
     * @throws DBALException
     */
    protected function insert($table, array $data)
    {
        $conn = $this->getConnection();

        try {
            $parameters = [];

            foreach ($data as $key => $value) {
                $parameters[$key] = ':' . $key;
            }
            $conn->beginTransaction();
            $sql = sprintf(
                'INSERT INTO `%s` (`%s`) VALUES (%s)',
                $table,
                implode('`,`', array_keys($parameters)),
                implode(',', $parameters)
            );

            $stmt = $conn->prepare($sql);
            $stmt->execute($data);

            $conn->commit();
        } catch (DBALException $e) {
            $conn->rollBack();

            throw $e;
        }
    }

    /**
     * @param string $table
     * @param array  $criteria
     *
     * @return array|null
     */
    protected function find($table, array $criteria)
    {
        $parameters = [];
        $conn = $this->getConnection();
        $sql = sprintf('SELECT * FROM `%s` WHERE 1', $table);
        $firstKey = reset(array_keys($criteria));
        foreach ($criteria as $key => $value) {
            if ($key === $firstKey) {
                $prefix = 'WHERE';
            } else {
                $prefix = 'AND';
            }
            $parameters[$key] = ':' . $key;
            $sql .= sprintf(' %s %s = :%s', $prefix, $key, $parameters[$key]);
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($parameters);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (array_key_exists(0, $result)) {
            return $result[0];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function purgeDatabase()
    {
        $conn = $this->getConnection();
        $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        $conn->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables = $conn->query('SHOW TABLES') as $table) {
            $table = reset($table);
            $conn->exec(sprintf('TRUNCATE `%s`', $table));
        }

        $conn->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return \PDO
     */
    protected function getConnection()
    {
        if (!$this->connection) {
            $this->connection = new \PDO(
                $this->dsn,
                $this->username,
                $this->password,
                $this->options
            );
        }

        return $this->connection;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function convertNameToTable($name)
    {
        return Inflector::pluralize($name);
    }

    /**
     * {@inheritdoc}
     */
    private function persistRows($table, array $rows)
    {
        foreach ($rows as $row) {
            $row = $this->applyMapping($this->getFieldMapping($table), $row);
            $row = array_merge($this->getDefaultFixture($table), $row);
            $this->transformFixture($table, $row);
            $this->insert($table, $row);
        }
    }

    /**
     * {@inheritdoc}
     */
    private function assertRowsHaveBeenPersisted($table, $rows)
    {
        foreach ($rows as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($table), $criteria);
            $this->transformFixture($table, $criteria);
            $criteria = $this->parseFormatters($criteria);
            $match = $this->find($table, $criteria);
            Assert::assertNotNull($match);
        }
    }

    /**
     * {@inheritdoc}
     */
    private function assertRowsHaveNotBeenPersisted($table, $rows)
    {
        foreach ($rows as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($table), $criteria);
            $this->transformFixture($table, $criteria);
            $criteria = $this->parseFormatters($criteria);
            $match = $this->find($table, $criteria);
            Assert::assertNotNull(
                $match,
                sprintf(
                    'There should not be a record in table "%s" with these criteria: %s',
                    $table,
                    json_encode($criteria)
                )
            );
        }
    }
}
