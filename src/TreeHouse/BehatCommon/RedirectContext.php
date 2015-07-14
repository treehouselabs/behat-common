<?php

namespace TreeHouse\BehatCommon;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit_Framework_Assert as Assert;
use Symfony\Component\BrowserKit\Client;

class RedirectContext extends RawMinkContext
{
    /**
     * @AfterScenario
     */
    public function afterScenario($event)
    {
        if ($this->getSession()->getDriver() instanceof BrowserKitDriver) {
            $this->getClient()->followRedirects(true);
        }
    }

    /**
     * @When I do not follow redirects
     */
    public function iDoNotFollowRedirects()
    {
        $this->getClient()->followRedirects(false);
    }

    /**
     * @Then I should be redirected to :url
     */
    public function iAmRedirected($url)
    {
        $headers = $this->getSession()->getResponseHeaders();

        Assert::assertArrayHasKey('location', $headers, 'The response contains a "location" header');

        Assert::assertEquals($url, $headers['location'][0], 'The "location" header does not point to the correct URI');

        $client = $this->getClient();
        $client->followRedirects(true);
        $client->followRedirect();
    }

    /**
     * @return Client
     *
     * @throws UnsupportedDriverActionException
     */
    protected function getClient()
    {
        $driver = $this->getSession()->getDriver();

        if (!$driver instanceof BrowserKitDriver) {
            $message = 'This step is only supported by the browserkit drivers';

            throw new UnsupportedDriverActionException($message, $driver);
        }

        return $driver->getClient();
    }
}
