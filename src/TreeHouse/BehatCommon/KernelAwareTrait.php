<?php

namespace TreeHouse\BehatCommon;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Base context providing access to the symfony kernel
 */
trait KernelAwareTrait
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * {@inheritdoc}
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return KernelInterface
     */
    protected function getKernel()
    {
        return $this->kernel;
    }

    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }
}
