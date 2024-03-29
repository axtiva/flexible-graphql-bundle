<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const SCHEMA_TYPE_FEDERATION = 'federation';
    public const SCHEMA_TYPE_GRAPHQL = 'graphql';
    public const EXECUTOR_TYPE_SYNC = 'sync';
    public const EXECUTOR_TYPE_ASYNC_AMPHPV2 = 'amphp_v2';
    public const EXECUTOR_TYPE_ASYNC_AMPHPV3 = 'amphp_v3';
    public const NAME = 'flexible_graphql';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::NAME, 'array');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->schemaType())
                ->append($this->schemaFile())
                ->append($this->operationType())
                ->append($this->enablePreload())
                ->append($this->defaultResolver())
                ->append($this->addScalar(
                    'namespace',
                    'App\GraphQL',
                    'Root namespace for generated code'
                )->isRequired())
                ->append($this->addScalar(
                    'template_language_version',
                    '7.4',
                    'PHP version of templates for php code'
                ))
                ->append($this->addScalar(
                    'dir',
                    '%kernel.project_dir%/src/GraphQL/',
                    'Root dir for generated code'
                )->isRequired())
            ->end();

        return $treeBuilder;
    }

    private function schemaType(): ScalarNodeDefinition
    {
        $treeBuilder = new TreeBuilder('schema_type', 'enum');

        /** @var EnumNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->values([self::SCHEMA_TYPE_GRAPHQL, self::SCHEMA_TYPE_FEDERATION])
            ->info('Select what type of schema use, common or federated')
            ->defaultValue(self::SCHEMA_TYPE_GRAPHQL)
        ->end();

        return $node;
    }

    private function operationType(): ScalarNodeDefinition
    {
        $treeBuilder = new TreeBuilder('executor', 'enum');

        /** @var EnumNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->values([self::EXECUTOR_TYPE_SYNC, self::EXECUTOR_TYPE_ASYNC_AMPHPV2, self::EXECUTOR_TYPE_ASYNC_AMPHPV3])
            ->info('Select executor type for graphql operations')
            ->defaultValue(self::EXECUTOR_TYPE_SYNC)
        ->end();

        return $node;
    }

    private function enablePreload(): ScalarNodeDefinition
    {
        $treeBuilder = new TreeBuilder('enable_preload', 'boolean');

        /** @var BooleanNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->info('Enable usage type registry on opcache.preload file')
            ->defaultTrue()
        ->end();

        return $node;
    }

    private function schemaFile(): ScalarNodeDefinition
    {
        $treeBuilder = new TreeBuilder('schema_files', 'scalar');

        /** @var ScalarNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->info('Full path to schema sdl files like /path/to/schema.graphql or glob template')
            ->defaultValue('%kernel.project_dir%/config/graphql/*.graphql')
            ->isRequired()
        ->end();

        return $node;
    }

    private function defaultResolver(): ScalarNodeDefinition
    {
        $treeBuilder = new TreeBuilder('default_resolver', 'scalar');
        /** @var ScalarNodeDefinition $node */
        $node = $treeBuilder->getRootNode();

        $node
            ->info('Default resolver for common cases')
            ->defaultValue(FlexibleGraphqlExtension::DEFAULT_RESOLVER_ID)
        ->end();

        return $node;
    }

    private function addScalar(string $name, $default = null, string $info = null): ScalarNodeDefinition
    {
        $builder = new TreeBuilder($name, 'scalar');

        /** @var ScalarNodeDefinition $node */
        $node = $builder->getRootNode();

        if (isset($info)) {
            $node->info($info);
        }

        if (isset($default)) {
            $node->defaultValue($default);
        }

        return $node;
    }
}
