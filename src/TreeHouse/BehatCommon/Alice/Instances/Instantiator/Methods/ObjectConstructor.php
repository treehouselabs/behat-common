<?php

namespace TreeHouse\BehatCommon\Alice\Instances\Instantiator\Methods;

use Nelmio\Alice\Fixtures\Fixture;
use Nelmio\Alice\Instances\Instantiator\Methods\MethodInterface;

/**
 * Custom method to allow a simple class to be used as the base
 * for loading fixtures instead of domain-specific objects (entities)
 */
class ObjectConstructor implements MethodInterface
{
    /**
     * {@inheritDoc}
     */
    public function canInstantiate(Fixture $fixture)
    {
        return is_array($fixture->getProperties());
    }

    /**
     * {@inheritDoc}
     *
     * @return object
     */
    public function instantiate(Fixture $fixture)
    {
        return (object) $fixture->getProperties();
    }
}
