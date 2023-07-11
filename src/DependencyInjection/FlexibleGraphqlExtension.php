<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\DependencyInjection;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Builder\Foundation\CodeGeneratorBuilder;
use Axtiva\FlexibleGraphql\Builder\Foundation\CodeGeneratorBuilderFederated;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilder;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderAmphp;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderAmphpV2;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderFederated;
use Axtiva\FlexibleGraphql\Builder\TypeRegistryGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Generator\Config\CodeGeneratorConfigInterface;
use Axtiva\FlexibleGraphql\Generator\Config\Foundation\Psr4\CodeGeneratorConfig;
use Axtiva\FlexibleGraphql\Resolver\_EntitiesResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\_ServiceResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\CustomScalarResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\DirectiveResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\FederationRepresentationResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\UnionResolveTypeInterface;
use Axtiva\FlexibleGraphqlBundle\CacheWarmer\SchemaCacheWarmer;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateDirectiveResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateFieldResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateScalarResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateTypeRegistryCommand;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

class FlexibleGraphqlExtension extends Extension implements CompilerPassInterface
{
    public const DEFAULT_RESOLVER_ID = 'flexible_graphql.default_resolver';
    public const RESOLVER_TAG = 'flexible_graphql.resolver';
    public const DIRECTIVE_RESOLVER_TAG = 'flexible_graphql.directive_resolver';
    public const SCALAR_RESOLVER_TAG = 'flexible_graphql.scalar_resolver';
    public const UNION_TYPE_RESOLVER_TAG = 'flexible_graphql.union_type_resolver';
    public const _SERVICE_RESOLVER_TAG = 'flexible_graphql._service_resolver';
    public const _ENTITIES_RESOLVER_TAG = 'flexible_graphql._entities_resolver';
    public const FEDERATION_REPRESENTATION_RESOLVER_TAG = 'flexible_graphql.federation_representation_resolver';

    private array $config;

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $yamlLoader->load('services.yaml');
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $this->config = $config;
        $this->registerConfigGenerator($this->config, $container);
        $this->registerCodeGenerator($this->config, $container);
        $this->registerTypeRegistryGenerator($this->config, $container);

        $this->setCompilerCacheWarmer($config, $container);
        $this->registerCommands($config, $container);
    }

    public function process(ContainerBuilder $container)
    {
        $this->registerResolvers($this->config, $container);
        if ($this->config['schema_type'] === Configuration::SCHEMA_TYPE_FEDERATION) {
            $this->registerRepresentationResolver($this->config, $container);
        }
    }

    public function getAlias(): string
    {
        return Configuration::NAME;
    }

    private function registerResolvers(array $config, ContainerBuilder $container)
    {
        if (!file_exists($config['dir'])) {
            mkdir($config['dir'], 0777, true);
        }

        $defaultDefinition = new Definition();
        $defaultDefinition->setAutoconfigured(true);

        foreach ($container->getDefinitions() as $id => $definition) {
            $reflection = $container->getReflectionClass($id, false);
            if ($reflection !== null) {
                if ($reflection->isSubclassOf(ResolverInterface::class)) {
                    $definition
                        ->addTag(static::RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(DirectiveResolverInterface::class)) {
                    $definition
                        ->addTag(static::DIRECTIVE_RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(CustomScalarResolverInterface::class)) {
                    $definition
                        ->addTag(static::SCALAR_RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(UnionResolveTypeInterface::class)) {
                    $definition
                        ->addTag(static::UNION_TYPE_RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(FederationRepresentationResolverInterface::class)) {
                    $definition
                        ->addTag(static::FEDERATION_REPRESENTATION_RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(_ServiceResolverInterface::class)) {
                    $definition
                        ->addTag(static::_SERVICE_RESOLVER_TAG)
                        ->setPublic(true);
                }
                if ($reflection->isSubclassOf(_EntitiesResolverInterface::class)) {
                    $definition
                        ->addTag(static::_ENTITIES_RESOLVER_TAG)
                        ->setPublic(true);
                }
            }
        }
    }

    private function setCompilerCacheWarmer(array $config, ContainerBuilder $container): void
    {
        $container->register(SchemaCacheWarmer::class)
            ->setArguments([
                $config['schema_files'],
                $config['schema_type'],
                new Reference(TypeRegistryGeneratorBuilderInterface::class),
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('kernel.cache_warmer', ['priority' => 50]);
    }

    private function registerConfigGenerator(array $config, ContainerBuilder $container): void
    {
        $container->register(CodeGeneratorConfigInterface::class)
            ->setClass(CodeGeneratorConfig::class)
            ->setArgument('$dir', $config['dir'])
            ->setArgument('$phpVersion', $config['template_language_version'])
            ->setArgument('$namespace', $config['namespace']);
    }

    private function registerTypeRegistryGenerator(array $config, ContainerBuilder $container): void
    {
        $baseTypeRegistryClass = $config['schema_type'] === Configuration::SCHEMA_TYPE_FEDERATION
            ? TypeRegistryGeneratorBuilderFederated::class
            : TypeRegistryGeneratorBuilder::class
        ;
        $container->register($baseTypeRegistryClass)
            ->setArgument('$config', new Reference(CodeGeneratorConfigInterface::class));

        if ($config['operation'] === Configuration::OPERATION_TYPE_SYNC) {
            $container->setAlias(
                TypeRegistryGeneratorBuilderInterface::class,
                $baseTypeRegistryClass
            );
        } elseif($config['operation'] === Configuration::OPERATION_TYPE_ASYNC_AMPHPV2) {
            $container->register(TypeRegistryGeneratorBuilderInterface::class)
                ->setClass(TypeRegistryGeneratorBuilderAmphpV2::class)
                ->setArgument('$baseBuilder', new Reference($baseTypeRegistryClass));
        } elseif($config['operation'] === Configuration::OPERATION_TYPE_ASYNC_AMPHPV3) {
            $container->register(TypeRegistryGeneratorBuilderInterface::class)
                ->setClass(TypeRegistryGeneratorBuilderAmphp::class)
                ->setArgument('$baseBuilder', new Reference($baseTypeRegistryClass));
        }

        $container->setAlias(
            'flexible_graphql.type_registry_generator.builder',
            TypeRegistryGeneratorBuilderInterface::class
        );
    }

    private function registerCodeGenerator(array $config, ContainerBuilder $container): void
    {
        if ($config['schema_type'] === Configuration::SCHEMA_TYPE_FEDERATION) {
            $container->register(CodeGeneratorBuilderInterface::class)
                ->setClass(CodeGeneratorBuilderFederated::class)
                ->setArgument('$config', new Reference(CodeGeneratorConfigInterface::class));
        } else {
            $container->register(CodeGeneratorBuilderInterface::class)
                ->setClass(CodeGeneratorBuilder::class)
                ->setArgument('$config', new Reference(CodeGeneratorConfigInterface::class));
        }
    }

    private function registerCommands(array $config, ContainerBuilder $container): void
    {
        $container->register(GenerateTypeRegistryCommand::class)
            ->setArguments([
                $config['schema_files'],
                $config['schema_type'],
                new Reference(TypeRegistryGeneratorBuilderInterface::class),
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateTypeRegistryCommand::getDefaultName()]);

        $container->register(GenerateDirectiveResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                $config['schema_type'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateDirectiveResolverCommand::getDefaultName()]);

        $container->register(GenerateFieldResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                $config['schema_type'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateFieldResolverCommand::getDefaultName()]);

        $container->register(GenerateScalarResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                $config['schema_type'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateScalarResolverCommand::getDefaultName()]);
    }

    private function registerRepresentationResolver(array $config, ContainerBuilder $container): void
    {
        if ($container->findTaggedServiceIds(self::_ENTITIES_RESOLVER_TAG)) {
            $representations = [];
            $services = array_keys($container->findTaggedServiceIds(self::FEDERATION_REPRESENTATION_RESOLVER_TAG));
            foreach ($services as $serviceId) {
                $representations[] = new Reference($serviceId);
            }

            $services = array_keys($container->findTaggedServiceIds(self::_ENTITIES_RESOLVER_TAG));
            foreach ($services as $serviceId) {
                $definition = $container->getDefinition($serviceId);
                $reflection = $container->getReflectionClass($serviceId, false);
                if ($reflection && $reflection->isSubclassOf(_EntitiesResolverInterface::class)) {
                    $definition->setArguments($representations);
                }
            }
        }

        if ($container->findTaggedServiceIds(self::_SERVICE_RESOLVER_TAG)) {
            $services = array_keys($container->findTaggedServiceIds(self::_SERVICE_RESOLVER_TAG));
            foreach ($services as $serviceId) {
                $definition = $container->getDefinition($serviceId);
                $reflection = $container->getReflectionClass($serviceId, false);
                if ($reflection && $reflection->isSubclassOf(_ServiceResolverInterface::class)) {
                    $schema = '';
                    foreach (glob($config['schema_files']) as $fsElement) {
                        if (is_file($fsElement)) {
                            $schema .= file_get_contents($fsElement) . PHP_EOL;
                        }
                    }
                    $definition->setArgument('$graphqlSchemaSDL', $schema);
                }
            }
        }
    }
}
