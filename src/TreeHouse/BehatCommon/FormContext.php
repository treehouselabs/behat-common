<?php

namespace TreeHouse\BehatCommon;

use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit_Framework_Assert as Assert;

class FormContext extends RawMinkContext
{
    /**
     * @var string
     */
    private $fieldContainerClass;

    /**
     * @param string $field_container_class
     */
    public function __construct($field_container_class = 'controls')
    {
        $this->fieldContainerClass = $field_container_class;
    }

    /**
     * @Then the field :name should have an error containing :message
     */
    public function theFieldShouldHaveAnErrorContaining($name, $message)
    {
        $field = $this->assertSession()->fieldExists($name);
        $parent = $field->getParent();

        if (false === stripos($parent->getAttribute('class'), $this->fieldContainerClass)) {
            $parent = $parent->getParent();
        }

        Assert::assertContains($message, $parent->getText());
    }

    /**
     * @Then the field :name should not/NOT have an error containing :message
     */
    public function theFieldShouldNotHaveAnErrorContaining($name, $message)
    {
        $field = $this->assertSession()->fieldExists($name);
        $parent = $field->getParent();

        if (false === stripos($parent->getAttribute('class'), $this->fieldContainerClass)) {
            $parent = $parent->getParent();
        }

        Assert::assertNotContains($message, $parent->getText());
    }

    /**
     * @When I fill in the :element element with :value
     */
    public function fillElement($element, $value)
    {
        $field = $this->assertSession()->elementExists('css', $element);
        $field->setValue($value);
    }
}
