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
    public const NAME = 'flexible_graphql';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::NAME, 'array');

        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->schemaType())
                ->append($this->schemaFile())
                ->append($this->enablePreload())
                ->append($this->defaultResolver())
                ->append($this->addRequiredScalar(
                    'namespace',
                    'App\GraphQL',
                    'Root namespace for generated code'
                ))
                ->append($this->addRequiredScalar(
                    'dir',
                    '%kernel.project_dir%/src/GraphQL/',
                    'Root dir for generated code'
                ))
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

    private function addRequiredScalar(string $name, $default = null, string $info = null): ScalarNodeDefinition
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

        $node
            ->isRequired()
        ->end();

        return $node;
    }
}
