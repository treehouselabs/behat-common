<?php

namespace TreeHouse\BehatCommon;

use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit_Framework_Assert as Assert;

class MetaContext extends RawMinkContext
{
    /**
     * @Then the browser title should be :title
     */
    public function theBrowserTitleShouldBe($title)
    {
        $this->assertSession()->elementTextContains('css', 'title', $title);
    }

    /**
     * @Then there should be a link titled :title
     */
    public function thereShouldBeALinkTitled($title)
    {
        $this->assertSession()->elementExists('css', sprintf('a:contains("%s")', $title));
    }

    /**
     * @Then there should be a link titled :title with attribute :attribute and value :value
     */
    public function thereShouldBeALinkTitledWithAttributeAndValue($title, $attribute, $value)
    {
        $link = $this->thereShouldBeAnElementWithAttributeAndValue('a', $title, $attribute, $value);

        Assert::assertEquals($title, $link->getText());
    }

    /**
     * @Then there should NOT be a link titled :title
     */
    public function thereShouldNotBeALinkTitled($title)
    {
        $this->assertSession()->elementNotExists('css', sprintf('a:contains("%s")', $title));
    }

    /**
     * @Then there should be a(n) :tag element :title with attribute :attribute and value :value
     */
    public function thereShouldBeAnElementWithAttributeAndValue($tag, $attribute, $value)
    {
        return $this->assertSession()->elementExists('css', sprintf('%s[%s="%s"]', $tag, $attribute, $value));
    }

    /**
     * @Then there should be a(n) :tag element :title with attribute :attribute whose value contains :attributeValue
     */
    public function thereShouldBeAnElementWithAttributeWhoseValueContains($tag, $attributeKey, $attributeValue)
    {
        $this->assertSession()->elementAttributeContains('css', $tag, $attributeKey, $attributeValue);
    }

    /**
     * @Then there should be a meta-tag with attribute :attributeKey and value :attributeValue
     */
    public function thereShouldBeAMetaTagWithAttributeAndValue($attributeKey, $attributeValue)
    {
        $this->thereShouldBeAnElementWithAttributeAndValue('meta', $attributeKey, $attributeValue);
    }

    /**
     * @Then there should be a meta-tag with attribute :attributeKey whose value contains :attributeValue
     */
    public function thereShouldBeAMetaTagWithAttributeWhoseValueContains($attributeKey, $attributeValue)
    {
        $this->thereShouldBeAnElementWithAttributeWhoseValueContains('meta', $attributeKey, $attributeValue);
    }
}
