<?php

namespace TreeHouse\BehatCommon;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\Assert;

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
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array  $options
     */
    public function __construct($dsn, $username, $password, array $options = [])
    {
        $this->dsn      = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options  = $options;
    }

    /**
     * @inheritdoc
     */
    protected function persistData($name, array $data)
    {
        $table = $this->convertNameToTable($this->singularize($name));

        foreach ($data as $row) {
            $row = $this->applyMapping($this->getFieldMapping($table), $row);
            $row = array_merge($this->getDefaultFixture($table), $row);
            $this->transformFixture($table, $row);
            $this->insert($table, $row);
        }
    }

    /**
     * @inheritdoc
     */
    protected function assertDataPersisted($name, array $criterias)
    {
        $table = $this->convertNameToTable($this->singularize($name));
        $found = [];

        foreach ($criterias as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($table), $criteria);
            $this->transformFixture($table, $criteria);
            $match = $this->find($table, $criteria);
            Assert::assertNotEmpty($match);

            $found[] = $match;
        }

        return $found;
    }

    /**
     * @inheritdoc
     */
    protected function assertDataNotPersisted($name, array $criterias)
    {
        $table = $this->convertNameToTable($this->singularize($name));

        foreach ($criterias as $criteria) {
            $criteria = $this->applyMapping($this->getFieldMapping($table), $criteria);
            $this->transformFixture($table, $criteria);
            $match = $this->find($table, $criteria);
            Assert::assertNotEmpty(
                $match,
                sprintf(
                    'There should not be a record in table "%s" with these criteria: %s',
                    $table,
                    json_encode($criteria)
                )
            );
        }
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
        $parts = [];
        $conn  = $this->getConnection();
        unset($criteria['profile.date_of_birth']);
        foreach ($criteria as $key => $value) {
            $parts[] = sprintf('%s = :%s', $key, $key);
        }

        if (empty($criteria)) {
            $sql = sprintf('SELECT * FROM `%s`', $table);
        } else {
            $sql = sprintf('SELECT * FROM `%s` WHERE %s', $table, implode(' AND ', $parts));
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($criteria);

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (array_key_exists(0, $result)) {
            return $result[0];
        }

        return [];
    }

    /**
     * @inheritdoc
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
        return $name;
    }
}
