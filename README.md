# Behat Common

Collection of contexts and traits for writing Behat features in your Symfony projects.


## Quick install:

### 1. Install the package through composer

Just run `composer require --dev clippings/behat-common`


### 2. Create a feature context like normal (extending `RawMinkContext`)
```php
<?php

namespace AppBundle\Behat;

use TreeHouse\BehatCommon\AbstractBaseContext;
use TreeHouse\BehatCommon\CookieTrait;

class FeatureContext extends RawMinkContext
{
    // write your own step definitions here...
}
```

### 3. Configure your `behat.yml` to use these contexts:
```
default:
    # ...
    suites:
        acme:
            # ...
            contexts:
              - Behat\MinkExtension\Context\MinkContext
              - AppBundle\Behat\FeatureContext
              # Optionally, you could also include any of the following (and more!)...
              - TreeHouse\BehatCommon\CookieContext
              - TreeHouse\BehatCommon\FormContext:
                  field_container_class: my_field_container_class # defaults to 'controls' (symfony default)
              - TreeHouse\BehatCommon\MetaContext
              - TreeHouse\BehatCommon\SeoContext
              - TreeHouse\BehatCommon\SwiftmailerContext
              # Since you are using symfony you probably also need to persist some data with doctrine...?
              - TreeHouse\BehatCommon\DoctrineOrmContext:
                  default_prefix: AcmeBundle # defaults to 'AppBundle'
    extensions:
      Behat\Symfony2Extension: ~
      Behat\MinkExtension:
        base_url:         https://www.acme.dev
        sessions:
          default:
            symfony2:     ~
```

### 4. Write some scenarios:
Using the `DoctrineOrmContext` and `SwiftmailerContext` to test a sign-up scenario:

```gherkin
Scenario: Register an account
  When I go to "/sign-up/"
  And I fill in the following:
    | registration[email]    | foo@example.com |
    | registration[password] | 1234            |
  And I select "1" from "registration[date_of_birth][day]"
  And I select "jan." from "registration[date_of_birth][month]"
  And I select "1987" from "registration[date_of_birth][year]"
  # needed to track mails sent (see SwiftmailerContext)
  And I do not follow redirects
  And I press "Sign me up!"
  Then an email with subject "Please confirm your account" should have been sent to "foo@example.com"
  And the email body should contain "Before you can log-in, you need to confirm your e-mailaddress by clicking on the link below"
  And I should be redirected to "/login/"
  And the response status code should be 200
  And I should see "Your data has been processed, you should now click on the link in the e-mail we sent you to confirm your e-mailaddress."
  And the following user should exist:
    | email           | confirmed | profile.date_of_birth |
    | foo@example.com | 0         | 1987-01-01            |
```


**NOTE:** Both `DoctrineOrmContext` and `PDOContext` support the use of fakers in your fixtures (see https://github.com/fzaninotto/Faker), like so:

```gherkin
Scenario: Login with a valid email/password combination
  Given the following users exist:
    | email     | password | confirmed | first_name    | last_name    |
    | <email()> | 1234     | 1         | <firstName()> | <lastName()> |
  # ...
```

### 5. Run behat!

```sh
bin/behat features/blog.feature
```
