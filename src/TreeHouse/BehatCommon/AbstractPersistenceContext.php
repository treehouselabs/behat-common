<?php

namespace TreeHouse\BehatCommon;

use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\Common\Inflector\Inflector;
use Nelmio\Alice\Fixtures\Loader;
use TreeHouse\BehatCommon\Alice\Instances\Instantiator\Methods\ObjectConstructor;

abstract class AbstractPersistenceContext extends RawMinkContext
{
    /**
     * @var bool
     */
    protected $purgedDb = false;

    /**
     * @AfterScenario
     */
    public function afterScenario()
    {
        $this->purgedDb = false;
    }

    protected function ensurePurgedDb()
    {
        if (!$this->purgedDb) {
            $this->purgeDatabase();

            $this->purgedDb = true;
        }
    }

    /**
     * @Given /^the following ((?!.*should).*) exist(s)?:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldBePersisted($name, TableNode $data)
    {
        $this->ensurePurgedDb();

        $this->persistData($name, $data->getHash());
    }

    /**
     * @Then /^the following (.*?) should( still)? exist(s)?:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldHaveBeenPersisted($name, TableNode $data)
    {
        $this->ensurePurgedDb();

        $this->assertDataPersisted($name, $data->getHash());
    }

    /**
     * @Then /^the following (.*?) should not exist:$/
     *
     * @inheritdoc
     */
    public function theFollowingDataShouldNotHaveBeenPersisted($name, TableNode $data)
    {
        $this->ensurePurgedDb();

        $this->assertDataNotPersisted($name, $data->getHash());
    }

    /**
     * @param array       $data
     * @param string|null $class
     *
     * @return array|object
     */
    protected function parseFormatters(array $data, $class = null)
    {
        if ($class === null) {
            $fixtureData = [[$data]];
        } else {
            $fixtureData = [$class => [$data]];
        }

        $loader = new Loader();

        if ($class === null) {
            $loader->addInstantiator(new ObjectConstructor());
        }

        $objectsOrArrays = $loader->load($fixtureData);

        $parsed = reset($objectsOrArrays);

        if ($class === null) {
            return (array) $parsed;
        }

        return $parsed;
    }

    /**
     * Singularifies a given value
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
     * Removes a value with the given $key from an array, if it exists
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
     * that is being persisted to, or queried against the database
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
     * Allows subclasses to define a base set of fixture data that will be used when persisting data to the database
     *
     * Note: this is called before applyMapping(), so field names should be connection-agnostic!
     *
     * @param string $alias
     *
     * @return array
     */
    protected function getDefaultFixture($alias)
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
     * @param string $alias
     * @param array  $fixture
     */
    protected function transformFixture($alias, array &$fixture)
    {
        // implement this in your own subclass
    }

    /**
     * Return any mapping you want to be performed on
     * data/criteria that will be tested against the database
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
     */
    abstract protected function assertDataPersisted($name, array $data);

    /**
     * @param string $name
     * @param array  $data
     *
     * @throw \PHPUnit_Framework_ExpectationFailedException
     */
    abstract protected function assertDataNotPersisted($name, array $data);

    /**
     * Purges the relevant database, if the scenario was tagged
     * with the tag configured during construction
     */
    abstract protected function purgeDatabase();
}
