<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\', '../src/');

    $services
        ->set(App\Controller\GraphqlController::class)
        ->public()
        ->tag('controller.service_arguments')
        ->arg('$locator', service('flexible_graphql.type_registry.service_locator'));
};
