<?php

namespace TreeHouse\BehatCommon;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use PHPUnit_Framework_Assert as Assert;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SecurityContext extends RawMinkContext implements KernelAwareContext
{
    use KernelAwareTrait;

    /**
     * @var UserProviderInterface
     */
    private $userProvider;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var SessionInterface
     */
    private $symfonySession;

    /**
     * @var string
     */
    private $defaultLoginProperty;

    /**
     * @var string
     */
    private $defaultProviderKey;

    /**
     * @param UserProviderInterface $user_provider
     * @param TokenStorageInterface $token_storage
     * @param SessionInterface      $session
     * @param string                $default_login_property
     * @param string                $default_provider_key
     */
    public function __construct(
        UserProviderInterface $user_provider,
        TokenStorageInterface $token_storage = null,
        SessionInterface $session = null,
        $default_login_property = 'username',
        $default_provider_key = 'main'
    ) {
        $this->userProvider = $user_provider;
        $this->tokenStorage = $token_storage;
        $this->symfonySession = $session;
        $this->defaultLoginProperty = $default_login_property;
        $this->defaultProviderKey = $default_provider_key;
    }

    /**
     * @Given I am logged in as :value
     */
    public function iAmLoggedInAs($value)
    {
        $this->loginAs($value);
    }

    /**
     * @Given I am logged in as :value on :providerKey
     */
    public function iAmLoggedInAsOn($value, $providerKey)
    {
        $this->loginAs($value, $providerKey);
    }

    /**
     * @Given I am not logged in
     * @Given I am not logged in on :providerKey
     */
    public function iAmNotLoggedInOn($providerKey = null)
    {
        $this->logout($providerKey);
    }

    /**
     * @Then I should be logged in as :value
     * @Then I should be logged in with :key :value
     *
     * @param string|null $keyOrValue
     * @param string|null $value
     */
    public function iShouldBeLoggedInAs($keyOrValue = null, $value = null)
    {
        if ($keyOrValue !== null && $value !== null) {
            $property = $keyOrValue;
        } else {
            $property = $this->defaultLoginProperty;
        }

        Assert::assertNotNull($this->getCurrentUser(), 'There should be a current user');
        Assert::assertEquals(
            $value,
            $this->getObjectValue($this->getCurrentUser(), $property),
            sprintf('The logged in %s should match the expected %s', $property, $this->defaultLoginProperty)
        );
    }

    /**
     * @Then I should not be logged in
     * @Then I should not be logged in as :value
     * @Then I should not be logged in with :key :value
     *
     * @param string|null $keyOrValue
     * @param string|null $value
     */
    public function iShouldNotBeLoggedIn($keyOrValue = null, $value = null)
    {
        if ($keyOrValue !== null && $value !== null) {
            $property = $keyOrValue;
        } else {
            $property = $this->defaultLoginProperty;
        }

        if ($value === null) {
            Assert::assertNull($this->getCurrentUser(true), 'There should not be a logged in user at this point');
        } elseif ($this->getCurrentUser(true)) {
            Assert::assertNotEquals(
                $value,
                $this->getObjectValue($this->getCurrentUser(true), $property),
                'The logged in e-mail should not match the expected e-mail'
            );
        }
    }

    /**
     * Get current user instance, if logged in
     *
     * @param bool $silentFailure
     *
     * @return UserInterface|null
     *
     * @throws \RuntimeException
     */
    protected function getCurrentUser($silentFailure = false)
    {
        $token = $this->getTokenStorage()->getToken();

        if (null === $token) {
            if ($silentFailure) {
                return null;
            }

            throw new \RuntimeException('No token found in security context.');
        }

        if (!($token->getUser() instanceof UserInterface)) {
            if ($silentFailure) {
                return null;
            }

            throw new \RuntimeException(sprintf(
                'User in token is not an instance of %s, got: %s',
                UserInterface::class,
                var_export($token->getUser(), true))
            );
        }

        return $token->getUser();
    }

    /**
     * Login as the user with the given email
     *
     * @param string      $value
     * @param string|null $providerKey Name of the providerkey (firewall) to use
     *
     * @throws UnsupportedDriverActionException
     */
    protected function loginAs($value, $providerKey = null)
    {
        $providerKey = $providerKey ?: $this->defaultProviderKey;
        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof BrowserKitDriver) {
            throw new UnsupportedDriverActionException('This step is only supported by the BrowserKitDriver', $driver);
        }

        $client = $driver->getClient();
        $client->getCookieJar()->set(new Cookie(session_name(), true));

        $session = $this->getSymfonySession();

        $user = $this->findUser($value);

        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $session->set('_security_'.$providerKey, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);
    }

    /**
     * @param string|null $providerKey
     *
     * @throws UnsupportedDriverActionException
     */
    protected function logout($providerKey = null)
    {
        $providerKey = $providerKey ?: $this->defaultProviderKey;

        if (!$this->getCurrentUser(true)) {
            // already logged out
            return;
        }

        $driver = $this->getSession()->getDriver();
        if (!$driver instanceof BrowserKitDriver) {
            throw new UnsupportedDriverActionException('This functionality is only supported by the BrowserKitDriver', $driver);
        }

        $client = $driver->getClient();

        $session = $this->getSymfonySession();

        $session->remove('_security_'.$providerKey);
        $session->save();

        $client->getCookieJar()->expire($session->getName());
    }

    /**
     * @param string $value
     *
     * @return UserInterface
     *
     * @throws \RuntimeException
     */
    protected function findUser($value)
    {
        try {
            $user = $this->getUserProvider()->loadUserByUsername($value);
        } catch (UsernameNotFoundException $e) {
            throw new \RuntimeException(sprintf(
                'Could not find a user through your UserProvider\'s `loadUserByUsername` method (class "%s" using argument: "%s")',
                get_class($this->getUserProvider()),
                $value
            ));
        }

        return $user;
    }

    /**
     * @return UserProviderInterface
     */
    protected function getUserProvider()
    {
        return $this->userProvider;
    }

    /**
     * @return TokenStorageInterface
     */
    protected function getTokenStorage()
    {
        if (!$this->tokenStorage) {
            return $this->getContainer()->get('security.token_storage');
        }

        return $this->tokenStorage;
    }

    /**
     * @return SessionInterface
     */
    protected function getSymfonySession()
    {
        if (!$this->symfonySession) {
            return $this->getContainer()->get('session');
        }

        return $this->symfonySession;
    }

    /**
     * @param object $object
     * @param string $property
     *
     * @return mixed
     */
    protected function getObjectValue($object, $property)
    {
        $propertyAccessor = new PropertyAccessor();
        $value = $propertyAccessor->getValue($object, $property);

        return $value;
    }
}
