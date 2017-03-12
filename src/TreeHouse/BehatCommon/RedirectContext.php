<?php

namespace TreeHouse\BehatCommon;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
     * @When I follow the current redirect
     */
    public function iFollowTheCurrentRedirect()
    {
        $response = $this->getClient()->getResponse();

        Assert::assertInstanceOf(RedirectResponse::class, $response);

        $this->getSession()->visit($response->getTargetUrl());
    }

    /**
     * @Then I should be redirected to :url
     * @Then I should be redirected to :url with :status
     *
     * @param string $url
     * @param int    $status
     */
    public function iAmRedirected($url, $status = 302)
    {
        $headers = $this->getSession()->getResponseHeaders();

        Assert::assertEquals($status, $this->getSession()->getStatusCode());
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
