<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        $bundles = require $this->getProjectDir() . '/config/bundles.php';

        foreach ($bundles as $class => $envs) {
            if (($envs[$this->environment] ?? $envs['all'] ?? false) === true) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/packages/*.php');
        $container->import('../config/services.php');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
