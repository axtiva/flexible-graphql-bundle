<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $schemaType = getenv('FLEXIBLE_GRAPHQL_SCHEMA_TYPE') ?: ($_SERVER['FLEXIBLE_GRAPHQL_SCHEMA_TYPE'] ?? $_ENV['FLEXIBLE_GRAPHQL_SCHEMA_TYPE'] ?? 'graphql');
    $executor = getenv('FLEXIBLE_GRAPHQL_EXECUTOR') ?: ($_SERVER['FLEXIBLE_GRAPHQL_EXECUTOR'] ?? $_ENV['FLEXIBLE_GRAPHQL_EXECUTOR'] ?? 'sync');

    $container->extension('flexible_graphql', [
        'namespace' => 'App\\GraphQL',
        'dir' => '%kernel.project_dir%/var/generated',
        'schema_type' => $schemaType,
        'schema_files' => '%kernel.project_dir%/config/graphql/*.graphql',
        'executor' => $executor,
        'enable_preload' => false,
        'default_resolver' => 'flexible_graphql.default_resolver',
        'template_language_version' => '8.3',
    ]);
};
