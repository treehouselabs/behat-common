<?php

namespace TreeHouse\BehatCommon;

use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit\Framework\Assert;

class NavigationContext extends RawMinkContext
{
    /**
     * @Then the current URL should end with :suffix
     */
    public function theCurrentUrlShouldEndWith($suffix)
    {
        Assert::assertStringEndsWith($suffix, $this->getSession()->getCurrentUrl());
    }
}
