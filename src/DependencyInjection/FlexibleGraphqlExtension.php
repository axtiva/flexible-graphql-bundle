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
use Axtiva\FlexibleGraphql\Resolver\CustomScalarResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\DirectiveResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\FederationRepresentationResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\_EntitiesResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\_ServiceResolverInterface;
use Axtiva\FlexibleGraphql\Resolver\UnionResolveTypeInterface;
use Axtiva\FlexibleGraphqlBundle\Builder\ScopedTypeRegistryGeneratorBuilder;
use Axtiva\FlexibleGraphqlBundle\CacheWarmer\SchemaCacheWarmer;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateDirectiveResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateFieldResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateScalarResolverCommand;
use Axtiva\FlexibleGraphqlBundle\Command\GenerateTypeRegistryCommand;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    public const TYPE_REGISTRY_SERVICE_TAG = 'flexible_graphql.type_registry.service';

    private const BASE_TYPE_REGISTRY_BUILDER_ID = 'flexible_graphql.type_registry_generator.base_builder';
    private const SELECTED_TYPE_REGISTRY_BUILDER_ID = 'flexible_graphql.type_registry_generator.selected_builder';

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<mixed> $config
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration();
    }

    /**
     * @return void
     */
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $yamlLoader->load('services.yaml');

        $this->registerResolverAutoconfiguration($container);

        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);
        $this->config = $config;
        $this->registerConfigGenerator($this->config, $container);
        $this->registerCodeGenerator($this->config, $container);
        $this->registerTypeRegistryGenerator($this->config, $container);

        $this->setCompilerCacheWarmer($config, $container);
        $this->registerCommands($config, $container);
    }

    /**
     * @return void
     */
    public function process(ContainerBuilder $container): void
    {
        $this->ensureOutputDir($this->config);
        if ($this->config['schema_type'] === Configuration::SCHEMA_TYPE_FEDERATION) {
            $this->registerRepresentationResolver($this->config, $container);
        }
    }

    public function getAlias(): string
    {
        return Configuration::NAME;
    }

    private function registerResolverAutoconfiguration(ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(ResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::RESOLVER_TAG)
            ->addTag(self::TYPE_REGISTRY_SERVICE_TAG);

        $container
            ->registerForAutoconfiguration(DirectiveResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::DIRECTIVE_RESOLVER_TAG)
            ->addTag(self::TYPE_REGISTRY_SERVICE_TAG);

        $container
            ->registerForAutoconfiguration(CustomScalarResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::SCALAR_RESOLVER_TAG)
            ->addTag(self::TYPE_REGISTRY_SERVICE_TAG);

        $container
            ->registerForAutoconfiguration(UnionResolveTypeInterface::class)
            ->setPublic(true)
            ->addTag(self::UNION_TYPE_RESOLVER_TAG)
            ->addTag(self::TYPE_REGISTRY_SERVICE_TAG);

        $container
            ->registerForAutoconfiguration(FederationRepresentationResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::FEDERATION_REPRESENTATION_RESOLVER_TAG);

        $container
            ->registerForAutoconfiguration(_ServiceResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::_SERVICE_RESOLVER_TAG);

        $container
            ->registerForAutoconfiguration(_EntitiesResolverInterface::class)
            ->setPublic(true)
            ->addTag(self::_ENTITIES_RESOLVER_TAG);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function ensureOutputDir(array $config): void
    {
        if (!file_exists($config['dir'])) {
            mkdir($config['dir'], 0755, true);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
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

    /**
     * @param array<string, mixed> $config
     */
    private function registerConfigGenerator(array $config, ContainerBuilder $container): void
    {
        $container->register(CodeGeneratorConfigInterface::class)
            ->setClass(CodeGeneratorConfig::class)
            ->setArgument('$dir', $config['dir'])
            ->setArgument('$phpVersion', $config['template_language_version'])
            ->setArgument('$namespace', $config['namespace']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerTypeRegistryGenerator(array $config, ContainerBuilder $container): void
    {
        $baseTypeRegistryClass = $config['schema_type'] === Configuration::SCHEMA_TYPE_FEDERATION
            ? TypeRegistryGeneratorBuilderFederated::class
            : TypeRegistryGeneratorBuilder::class
        ;

        $defaultResolverServiceId = (string) $config['default_resolver'];
        if ($container->hasAlias($defaultResolverServiceId)) {
            $defaultResolverServiceId = (string) $container->getAlias($defaultResolverServiceId);
        }

        $container->register(self::BASE_TYPE_REGISTRY_BUILDER_ID)
            ->setClass($baseTypeRegistryClass)
            ->setArgument('$config', new Reference(CodeGeneratorConfigInterface::class));

        if ($container->hasDefinition($defaultResolverServiceId)) {
            $container
                ->getDefinition(self::BASE_TYPE_REGISTRY_BUILDER_ID)
                ->addMethodCall('setDefaultFieldResolverServiceName', [$defaultResolverServiceId]);

            $container
                ->getDefinition($defaultResolverServiceId)
                ->addTag(self::TYPE_REGISTRY_SERVICE_TAG);
        }

        if ($config['executor'] === Configuration::EXECUTOR_TYPE_SYNC) {
            $container->setAlias(
                self::SELECTED_TYPE_REGISTRY_BUILDER_ID,
                self::BASE_TYPE_REGISTRY_BUILDER_ID
            );
        } elseif($config['executor'] === Configuration::EXECUTOR_TYPE_ASYNC_AMPHPV2) {
            $container->register(self::SELECTED_TYPE_REGISTRY_BUILDER_ID)
                ->setClass(TypeRegistryGeneratorBuilderAmphpV2::class)
                ->setArgument('$baseBuilder', new Reference(self::BASE_TYPE_REGISTRY_BUILDER_ID));
        } elseif($config['executor'] === Configuration::EXECUTOR_TYPE_ASYNC_AMPHPV3) {
            $container->register(self::SELECTED_TYPE_REGISTRY_BUILDER_ID)
                ->setClass(TypeRegistryGeneratorBuilderAmphp::class)
                ->setArgument('$baseBuilder', new Reference(self::BASE_TYPE_REGISTRY_BUILDER_ID));
        }

        $container->register(TypeRegistryGeneratorBuilderInterface::class)
            ->setClass(ScopedTypeRegistryGeneratorBuilder::class)
            ->setArgument('$baseBuilder', new Reference(self::SELECTED_TYPE_REGISTRY_BUILDER_ID));

        $container->setAlias(
            'flexible_graphql.type_registry_generator.builder',
            TypeRegistryGeneratorBuilderInterface::class
        );
    }

    /**
     * @param array<string, mixed> $config
     */
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

    /**
     * @param array<string, mixed> $config
     */
    private function registerCommands(array $config, ContainerBuilder $container): void
    {
        $container->register(GenerateTypeRegistryCommand::class)
            ->setArguments([
                $config['schema_files'],
                new Reference(TypeRegistryGeneratorBuilderInterface::class),
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateTypeRegistryCommand::getDefaultName()]);

        $container->register(GenerateDirectiveResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateDirectiveResolverCommand::getDefaultName()]);

        $container->register(GenerateFieldResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateFieldResolverCommand::getDefaultName()]);

        $container->register(GenerateScalarResolverCommand::class)
            ->setArguments([
                $config['schema_files'],
                new Reference(CodeGeneratorBuilderInterface::class),
            ])
            ->addTag('console.command', ['command' => GenerateScalarResolverCommand::getDefaultName()]);
    }

    /**
     * @param array<string, mixed> $config
     */
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
                            $content = file_get_contents($fsElement);
                            if ($content !== false) {
                                $schema .= $content . PHP_EOL;
                            }
                        }
                    }
                    $definition->setArgument('$graphqlSchemaSDL', $schema);
                }
            }
        }
    }
}
