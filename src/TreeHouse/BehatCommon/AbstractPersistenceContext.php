<?php

namespace TreeHouse\BehatCommon;

use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\Common\Inflector\Inflector;

abstract class AbstractPersistenceContext extends RawMinkContext
{
    /**
     * @Given /^the database has been purged$/
     */
    public function theDatabaseHasBeenPurged()
    {
        $this->purgeDatabase();
    }

    /**
     * @Given /^the following ((?!.*should).*) exist(s)?:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldBePersisted($name, TableNode $data)
    {
        $this->persistData($name, $data->getHash());
    }

    /**
     * @Then /^the following (.*?) should( still)? exist(s)?:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldHaveBeenPersisted($name, TableNode $data)
    {
        $this->assertDataPersisted($name, $data->getHash());
    }

    /**
     * @Then /^the following (.*?) should not exist:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldNotHaveBeenPersisted($name, TableNode $data)
    {
        $this->assertDataNotPersisted($name, $data->getHash());
    }

    /**
     * Singularifies a given value.
     *
     * @param string $value
     *
     * @return string
     */
    protected function singularize($value)
    {
        $singularified = Inflector::singularize($value);

        if (is_array($singularified)) {
            $singularified = end($singularified);
        }

        return $singularified;
    }

    /**
     * Removes a value with the given $key from an array, if it exists.
     *
     * @param string $key
     * @param array  $data
     */
    protected function unsetIfExists($key, array &$data)
    {
        if (array_key_exists($key, $data)) {
            unset($data[$key]);
        }
    }

    /**
     * Allows subclasses to apply their specific mapping to any data
     * that is being persisted to, or queried against the database.
     *
     * @param array $mapping
     * @param array $data
     *
     * @return array
     */
    protected function applyMapping(array $mapping, array $data)
    {
        if (!empty($mapping)) {
            foreach ($data as $key => $value) {
                unset($data[$key]);
                if (array_key_exists($key, $mapping)) {
                    $data[$mapping[$key]] = $value;
                }
            }
        }

        return $data;
    }

    /**
     * Allows subclasses to define a base set of fixture data that will be used when persisting data to the database.
     *
     * Note: this is called before applyMapping(), so field names should be connection-agnostic!
     *
     * @param string $type
     *
     * @return array
     */
    protected function getDefaultFixture($type)
    {
        // implement this in your own subclass
        return [];
    }

    /**
     * Allows subclasses to change any fixture data that is being persisted
     * or queried against the relevant database.
     *
     * Note: this is called before applyMapping(), so field names should stay connection-agnostic!
     *
     * @param string $type
     * @param array  $fixture
     */
    protected function transformFixture($type, array &$fixture)
    {
        // implement this in your own subclass
    }

    /**
     * Return any mapping you want to be performed on
     * data/criteria that will be tested against the database.
     *
     * If you return an empty array no mapping will be applied
     * In all other cases every key in the original data
     * that is not a key in your mapping will be removed
     *
     * @param string $alias
     *
     * @return array
     */
    protected function getFieldMapping($alias)
    {
        // implement this in your own subclass
        return [];
    }

    /**
     * Purges the relevant database.
     */
    abstract protected function purgeDatabase();

    /**
     * @param string $name
     * @param array  $data
     *
     * @throw \PHPUnit_Framework_ExpectationFailedException
     */
    abstract protected function persistData($name, array $data);

    /**
     * @param string $name
     * @param array  $data
     *
     * @throw \PHPUnit_Framework_ExpectationFailedException
     *
     * @return mixed[]
     */
    abstract protected function assertDataPersisted($name, array $data);

    /**
     * @param string $name
     * @param array  $data
     *
     * @throw \PHPUnit_Framework_ExpectationFailedException
     */
    abstract protected function assertDataNotPersisted($name, array $data);
}
