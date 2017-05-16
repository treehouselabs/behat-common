<?php

declare(strict_types=1);

namespace TreeHouse\BehatCommon;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit\Framework\Assert;

class CookieContext extends RawMinkContext
{
    /**
     * @Then a cookie named :name should have been added to the response
     * @Then a cookie named :name should have been added to the response with a lifetime of :lifetimeCount :lifetimePeriod
     *
     * @param      $name
     * @param null $lifetimeCount
     * @param null $lifetimePeriod
     */
    public function aCookieNamedShouldHaveBeenAddedToTheResponseWithALifeTimeOf(
        $name,
        $lifetimeCount = null,
        $lifetimePeriod = null
    ) {
        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof BrowserKitDriver) {
            throw new UnsupportedDriverActionException('This step is only supported by the BrowserKitDriver', $driver);
        }

        $cookie = $driver->getClient()->getCookieJar()->get($name);

        Assert::assertNotNull($cookie, sprintf('A cookie should have been made named %s', $name));

        if ($lifetimeCount !== null && $lifetimePeriod !== null) {
            $this->assertLifetime($lifetimeCount, $lifetimePeriod, $cookie);
        }
    }

    /**
     * @param $lifetimeCount
     * @param $lifetimePeriod
     * @param $cookie
     */
    private function assertLifetime($lifetimeCount, $lifetimePeriod, $cookie)
    {
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
