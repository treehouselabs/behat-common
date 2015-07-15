<?php

namespace TreeHouse\BehatCommon;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit_Framework_Assert as Assert;

class CookieContext extends RawMinkContext
{
    /**
     * @Given a cookie named :name should have been added to the response with a lifetime of :lifetimeCount :lifetimePeriod
     */
    public function aCookieNamedShouldHaveBeenAddedToTheResponseWithALifeTimeOf(
        $name,
        $lifetimeCount,
        $lifetimePeriod
    ) {
        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof BrowserKitDriver) {
            throw new UnsupportedDriverActionException('This step is only supported by the BrowserKitDriver', $driver);
        }

        $cookie = $driver->getClient()->getCookieJar()->get($name);

        Assert::assertNotNull($cookie, sprintf('A cookie should have been made named %s', $name));

        $lifetime = $lifetimeCount . ' ' . $lifetimePeriod;
        $expectedExpiresTime = strtotime($lifetime);
        $actualExpiresTime = $cookie->getExpiresTime();

        // allow few seconds of difference due to time spent testing
        $matches = $actualExpiresTime > ($expectedExpiresTime - 5) && $actualExpiresTime <= $expectedExpiresTime;

        Assert::assertTrue(
            $matches,
            sprintf('The cookie should expire between %s and %s', $expectedExpiresTime - 5, $expectedExpiresTime)
        );
    }
}
