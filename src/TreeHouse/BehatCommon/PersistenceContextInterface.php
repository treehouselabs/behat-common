<?php

namespace TreeHouse\BehatCommon;

use Behat\Gherkin\Node\TableNode;

interface PersistenceContextInterface
{
    /**
     * @param string    $name
     * @param TableNode $table
     */
    public function theFollowingDataShouldNotHaveBeenPersisted($name, TableNode $table);

    /**
     * @param string    $name
     * @param TableNode $table
     */
    public function theFollowingDataShouldBePersisted($name, TableNode $table);

    /**
     * @param string    $name
     * @param TableNode $table
     */
    public function theFollowingDataShouldHaveBeenPersisted($name, TableNode $table);
}
