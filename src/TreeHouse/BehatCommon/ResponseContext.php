<?php

namespace TreeHouse\BehatCommon;

use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit_Framework_Assert as Assert;

class ResponseContext extends RawMinkContext
{
    /**
     * @Then the response should be cached for :time
     */
    public function theResponseShouldBeCachedForTime($time)
    {
        $age = strtotime('+' . ltrim($time, '+'));

        if ($age <= 0) {
            throw new \LogicException(
                sprintf(
                    'Invalid value for cache ttl (%s given), please specify a format that strtotime understands',
                    $time
                )
            );
        }

        $ttl = strtotime('+' . $time) - time();

        $this->theResponseHeaderContains('cache-control', 'max-age=' . $ttl);
        $this->theResponseHeaderContains('cache-control', 's-maxage=' . $ttl);
    }

    /**
     * @Then the response header :header should contain :value
     * @Then the response header :header contains :value
     */
    public function theResponseHeaderContains($header, $value)
    {
        $headers = $this->getSession()->getResponseHeaders();

        Assert::assertArrayHasKey($header, $headers, sprintf('The response does not contains a "%s"-header', $header));
        Assert::assertContains($value, $headers[$header][0], sprintf('The response header "%s" does not contain "%s"', $header, $value));
    }

    /**
     * @Then the response should have content-type :contentType
     */
    public function theResponseShouldHaveContentType($contentType)
    {
        $this->theResponseHeaderContains('content-type', $contentType);
    }
}
