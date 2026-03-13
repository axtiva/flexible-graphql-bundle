<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Tests\DependencyInjection;

use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilder;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderAmphp;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderAmphpV2;
use Axtiva\FlexibleGraphql\Builder\Foundation\Psr\Container\TypeRegistryGeneratorBuilderFederated;
use Axtiva\FlexibleGraphql\Builder\TypeRegistryGeneratorBuilderInterface;
use Axtiva\FlexibleGraphqlBundle\Builder\ScopedTypeRegistryGeneratorBuilder;
use Axtiva\FlexibleGraphqlBundle\DependencyInjection\FlexibleGraphqlExtension;
use Axtiva\FlexibleGraphqlBundle\Resolver\DefaultResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class FlexibleGraphqlExtensionTest extends TestCase
{
    #[DataProvider('provideBuilderVariants')]
    public function testTypeRegistryBuilderVariantSelection(
        string $schemaType,
        string $executor,
        string $expectedBaseBuilder,
        ?string $expectedSelectedBuilder
    ): void {
        $container = $this->loadExtension($schemaType, $executor);

        $baseDefinition = $container->getDefinition('flexible_graphql.type_registry_generator.base_builder');
        self::assertSame($expectedBaseBuilder, $baseDefinition->getClass());
        self::assertDefaultResolverConfiguredOnBaseBuilder($baseDefinition->getMethodCalls());

        if ($expectedSelectedBuilder === null) {
            self::assertTrue($container->hasAlias('flexible_graphql.type_registry_generator.selected_builder'));
            self::assertSame(
                'flexible_graphql.type_registry_generator.base_builder',
                (string) $container->getAlias('flexible_graphql.type_registry_generator.selected_builder')
            );
        } else {
            $selectedDefinition = $container->getDefinition('flexible_graphql.type_registry_generator.selected_builder');
            self::assertSame($expectedSelectedBuilder, $selectedDefinition->getClass());
            $selectedBaseBuilderArgument = $selectedDefinition->getArgument('$baseBuilder');
            self::assertInstanceOf(Reference::class, $selectedBaseBuilderArgument);
            self::assertSame('flexible_graphql.type_registry_generator.base_builder', (string) $selectedBaseBuilderArgument);
        }

        $scopedBuilderDefinition = $container->getDefinition(TypeRegistryGeneratorBuilderInterface::class);
        self::assertSame(ScopedTypeRegistryGeneratorBuilder::class, $scopedBuilderDefinition->getClass());
        $builderArgument = $scopedBuilderDefinition->getArgument('$baseBuilder');
        self::assertInstanceOf(Reference::class, $builderArgument);
        self::assertSame('flexible_graphql.type_registry_generator.selected_builder', (string) $builderArgument);
    }

    public function testTypeRegistryScopedLocatorWiring(): void
    {
        $container = $this->loadExtension('graphql', 'sync');

        $locatorDefinition = $container->getDefinition('flexible_graphql.type_registry.service_locator');
        self::assertSame('Symfony\\Component\\DependencyInjection\\ServiceLocator', $locatorDefinition->getClass());

        $locatorArgument = $locatorDefinition->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $locatorArgument);
        $taggedArgument = $locatorArgument;
        self::assertSame(FlexibleGraphqlExtension::TYPE_REGISTRY_SERVICE_TAG, $taggedArgument->getTag());
        self::assertSame('service_id', $taggedArgument->getIndexAttribute());

        $defaultResolverDefinition = $container->getDefinition(DefaultResolver::class);
        self::assertArrayHasKey(FlexibleGraphqlExtension::TYPE_REGISTRY_SERVICE_TAG, $defaultResolverDefinition->getTags());
    }

    /**
     * @return iterable<string, array{string, string, string, string|null}>
     */
    public static function provideBuilderVariants(): iterable
    {
        yield 'sync graphql' => ['graphql', 'sync', TypeRegistryGeneratorBuilder::class, null];
        yield 'amphp v2 graphql' => ['graphql', 'amphp_v2', TypeRegistryGeneratorBuilder::class, TypeRegistryGeneratorBuilderAmphpV2::class];
        yield 'amphp v3 federation' => ['federation', 'amphp_v3', TypeRegistryGeneratorBuilderFederated::class, TypeRegistryGeneratorBuilderAmphp::class];
    }

    private function loadExtension(string $schemaType, string $executor): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new FlexibleGraphqlExtension();
        $extension->load([
            [
                'namespace' => 'App\\GraphQL',
                'dir' => sys_get_temp_dir() . '/fgb-tests-' . uniqid('', true) . '/',
                'schema_type' => $schemaType,
                'schema_files' => dirname(__DIR__) . '/fixture-app/config/graphql/*.graphql',
                'executor' => $executor,
                'enable_preload' => false,
                'default_resolver' => FlexibleGraphqlExtension::DEFAULT_RESOLVER_ID,
                'template_language_version' => '8.3',
            ],
        ], $container);

        return $container;
    }

    /**
     * @param array<int, array{string, array<int, string>}> $methodCalls
     */
    private static function assertDefaultResolverConfiguredOnBaseBuilder(array $methodCalls): void
    {
        foreach ($methodCalls as [$methodName, $arguments]) {
            if ($methodName === 'setDefaultFieldResolverServiceName' && ($arguments[0] ?? null) === DefaultResolver::class) {
                return;
            }
        }

        self::fail('Default resolver service was not configured on type-registry builder.');
    }
}
