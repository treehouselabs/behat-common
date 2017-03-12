<?php

namespace TreeHouse\BehatCommon;

use Behat\Mink\Element\NodeElement;
use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit\Framework\Assert;

class SeoContext extends RawMinkContext
{
    /**
     * @Then the browser title should be :title
     */
    public function theBrowserTitleShouldBe($title)
    {
        $this->assertSession()->elementTextContains('css', 'title', $title);
    }

    /**
     * @Then there should be a link titled :title with attribute :attribute and value :value
     */
    public function thereShouldBeALinkTitledWithAttributeAndValue($title, $attribute, $value)
    {
        $link = $this->thereShouldBeAnElementTitledWithAttributeAndValue('a', $title, $attribute, $value);

        Assert::assertEquals($title, $link->getText());
    }

	/**
     * @Then there should not be a link titled :title with attribute :attribute and value :value
     */
    public function thereShouldNotBeALinkTitledWithAttributeAndValue($title, $attribute, $value)
    {
        $this->thereShouldNotBeAnElementTitledWithAttributeAndValue('a', $title, $attribute, $value);
    }

    /**
     * @Then there should be a(n) :tag element titled :title with attribute :attribute and value :value
     */
    public function thereShouldBeAnElementTitledWithAttributeAndValue($tag, $title, $attribute, $value)
    {
        return $this->assertSession()->elementExists('css', sprintf('%s:contains("%s")[%s="%s"]', $tag, $title, $attribute, $value));
    }

	/**
     * @Then there should not be a(n) :tag element with attribute :attribute and value :value
     */
    public function thereShouldNotBeAnElementTitledWithAttributeAndValue($tag, $title, $attribute, $value)
    {
        $this->assertSession()->elementNotExists('css', sprintf('%s:contains("%s")[%s="%s"]', $tag, $title, $attribute, $value));
    }

    /**
     * @Then there should be a(n) :tag element with attribute :attribute and value :value
     */
    public function thereShouldBeAnElementWithAttributeAndValue($tag, $attribute, $value)
    {
        return $this->assertSession()->elementExists('css', sprintf('%s[%s="%s"]', $tag, $attribute, $value));
    }

    /**
     * @Then there should be a(n) :tag element titled :title with attribute :attribute whose value contains :attributeValue
     */
    public function thereShouldBeAnElementWithAttributeWhoseValueContains($tag, $attributeKey, $attributeValue)
    {
        $this->assertSession()->elementAttributeContains('css', $tag, $attributeKey, $attributeValue);
    }

    /**
     * @Then there should be a meta-tag with property :propertyValue and content :contentValue
     */
    public function thereShouldBeAMetaTagWithPropertyAndContent($propertyValue, $contentValue)
    {
        $tag = $this->getMetaTagByProperty($propertyValue);

        Assert::assertSame($contentValue, $tag->getAttribute('content'));
    }

    /**
     * @Then there should be a meta-tag with property :propertyValue and content matching :contentValueRegex
     */
    public function thereShouldBeAMetaTagWithPropertyAndContentMatching($propertyValue, $contentValueRegex)
    {
        $tag = $this->getMetaTagByProperty($propertyValue);

        Assert::assertRegExp($contentValueRegex, $tag->getAttribute('content'));
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

    /**
     * @Then there should be a link titled :title
     */
    public function thereShouldBeALinkTitled($title)
    {
        $this->assertSession()->elementExists('css', sprintf('a:contains("%s")', $title));
    }

    /**
     * @Then there should not be a link titled :title
     */
    public function thereShouldNotBeALinkTitled($title)
    {
        $this->assertSession()->elementNotExists('css', sprintf('a:contains("%s")', $title));
    }

    /**
     * @Then there should be a link titled :title with "nofollow"
     */
    public function thereShouldBeALinkTitledWithNoFollow($title)
    {
        $this->assertSession()->elementExists('css', sprintf('a:contains("%s")[rel="nofollow"]', $title));
    }

    /**
     * @Then there should be a link titled :title without "nofollow"
     */
    public function thereShouldBeALinkTitledWithoutNoFollow($title)
    {
        $element = $this->assertSession()->elementExists('css', sprintf('a:contains("%s")', $title));

        if (!$element->hasAttribute('rel')) {
            return;
        }

        Assert::assertNotEquals(
            'nofollow',
            $element->getAttribute('rel'),
            'The link\'s "rel" attribute should not match "nofollow"'
        );
    }

    /**
     * @Then the meta-title should be :expected
     * @Then the browser-title should be :expected
     */
    public function theMetaTitleShouldBe($expected)
    {
        $element = $this->assertSession()->elementExists('css', 'title');
        $actual = $element->getText();

        Assert::assertEquals($expected, $actual, 'The expected and actual meta-descriptions should match');
    }

    /**
     * @Then the page can be :action by robots
     *
     * @param string $action
     */
    public function thePageCanBeActionByRobots($action)
    {
        $action = strtolower($action);

        switch ($action) {
            case 'indexed':
            case 'followed':
            $directive = substr($action, 0, -2);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown robots action: %s', $action));
        }

        $this->theRobotsMetaHasDirective($directive);
    }

    /**
     * @Then the page can not be :action by robots
     *
     * @param string $action
     */
    public function thePageCanNotBeActionByRobots($action)
    {
        $action = strtolower($action);

        switch ($action) {
            case 'indexed':
                $directive = "noindex";
                break;
            case 'followed':
                $directive = "nofollow";
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Unknown robots action: %s', $action));
        }

        $this->theRobotsMetaHasDirective($directive);
    }

    /**
     * @Then the robots meta has :directive
     *
     * @param string $directive
     */
    public function theRobotsMetaHasDirective($directive)
    {
        Assert::assertContains($directive, $this->getRobotDirectives());
    }

    /**
     * @Then the meta-description should not contain anything
     */
    public function theMetaDescriptionShouldBeEmpty()
    {
        $element = $this->getMetaTag('description');

        Assert::assertEmpty($element->getAttribute('content'));
    }

    /**
     * @Then the meta-description should be :expected
     *
     * @param string $expected
     */
    public function theMetaDescriptionShouldBe($expected)
    {
        $element = $this->getMetaTag('description');
        $actual = $element->getAttribute('content');

        Assert::assertEquals($expected, $actual, 'The expected and actual meta-descriptions should match');
    }

    /**
     * @Then the meta-description should contain :expected
     *
     * @param string $expected
     */
    public function theMetaDescriptionShouldContain($expected)
    {
        $element = $this->getMetaTag('description');
        $actual = $element->getAttribute('content');

        Assert::assertContains($expected, $actual, 'The actual meta-descriptions should contain expected value');
    }

    /**
     * @Then robots will ignore ODP
     *
     * @see http://www.metatags.nl/google_meta_name_noodp
     */
    public function robotsWillIgnoreOdp()
    {
        Assert::assertContains('noodp', $this->getRobotDirectives());
    }

    /**
     * @Then the canonical path should be :path
     *
     * @param string $path
     */
    public function theCanonicalPathShouldBe($path)
    {
        $element = $this->assertSession()->elementExists('css', 'link[rel=canonical]');
        $actual = $element->getAttribute('href');
        $parsed = parse_url($actual);

        $expected = sprintf('%s://%s%s', $parsed['scheme'], $parsed['host'], $path);
        if (array_key_exists('query', $parsed)) {
            $expected .= '?' . $parsed['query'];
        }

        Assert::assertEquals($expected, $actual, 'The expected and actual canonical paths should match');
    }

    /**
     * @param string $name
     *
     * @return NodeElement
     */
    protected function getMetaTag($name)
    {
        return $this->assertSession()->elementExists('css', sprintf('meta[name="%s"]', $name));
    }

    /**
     * @param string $property
     *
     * @return NodeElement
     */
    protected function getMetaTagByProperty($property)
    {
        return $this->assertSession()->elementExists('css', sprintf('meta[property="%s"]', $property));
    }

    /**
     * @return array
     */
    protected function getRobotDirectives()
    {
        /* @var NodeElement[] $metaElements */
        $metaElements = $this->getSession()->getPage()->findAll('css', 'meta[name=robots]');

        $directives = [];
        foreach ($metaElements as $el) {
            $directives = array_merge(
                $directives,
                array_map('trim', explode(',', mb_strtolower($el->getAttribute('content'))))
            );
        }

        return $directives;
    }
}
