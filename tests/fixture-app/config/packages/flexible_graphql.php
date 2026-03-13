<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('flexible_graphql', [
        'namespace' => 'App\\GraphQL',
        'dir' => '%kernel.project_dir%/var/generated',
        'schema_type' => 'graphql',
        'schema_files' => '%kernel.project_dir%/config/graphql/*.graphql',
        'enable_preload' => false,
        'default_resolver' => 'flexible_graphql.default_resolver',
        'template_language_version' => '8.3',
    ]);
};
