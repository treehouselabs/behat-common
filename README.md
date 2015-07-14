# Behat Common

Collection of contexts and traits for writing Behat features in your Symfony projects.


## Quick install:

### 1. Install the package through composer

Just run `composer require --dev treehouselabs/behat-common-symfony`


### 2. Create a base context (extending `AbstractSymfonyContext`)
```php
<?php

namespace AppBundle\Behat;

use TreeHouse\BehatCommon\AbstractBaseContext;
use TreeHouse\BehatCommon\CookieTrait;

class FeatureContext extends AbstractSymfonyContext
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
              - AppBundle\Behat\FeatureContext
              # Optionally, you could also include any of the following (and more!)...
              - TreeHouse\BehatCommon\CookieContext
              - TreeHouse\BehatCommon\MetaContext
              - TreeHouse\BehatCommon\SeoContext
              # Since you are using symfony you probably also need to persist some data with doctrine...?
              - TreeHouse\BehatCommon\DoctrineContext
              # You don't have to include the MinkContext class here anymore because
              # if any of the included contexts extend AbstractBaseContext it will be
              # registered for you automatically
              # - Behat\MinkExtension\Context\MinkContext

    extensions:
      Behat\Symfony2Extension: ~
      Behat\MinkExtension:
        base_url:         https://www.acme.dev
        sessions:
          default:
            symfony2:     ~
```

### 4. Run behat!

```sh
bin/behat features/blog.feature
```
