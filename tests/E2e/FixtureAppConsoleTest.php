<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Tests\E2e;

use Psr\Container\NotFoundExceptionInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Service\ServiceCollectionInterface;

final class FixtureAppConsoleTest extends TestCase
{
    public function testFixtureAppGenerationFlows(): void
    {
        $fixtureAppDir = self::fixtureAppDir();
        $generatedDir = $fixtureAppDir . '/var/generated';
        $registryFile = $generatedDir . '/TypeRegistry.php';

        if (!is_dir($generatedDir)) {
            mkdir($generatedDir, 0755, true);
        }

        if (is_file($registryFile)) {
            unlink($registryFile);
        }

        $this->runConsoleCommand('cache:clear');
        self::assertFileExists($registryFile, 'cache:clear must trigger schema cache warmer generation');

        unlink($registryFile);

        $output = $this->runConsoleCommand('flexible_graphql:generate-type-registry');
        self::assertStringContainsString($registryFile, $output);
        self::assertFileExists($registryFile);

        $registryCode = (string) file_get_contents($registryFile);
        self::assertStringContainsString('ServiceCollectionInterface', $registryCode);
        self::assertStringNotContainsString('ContainerInterface $container', $registryCode);
    }

    public function testFixtureAppGenerationSupportsConfigurationVariantsAndFederation(): void
    {
        $registryFile = self::fixtureAppDir() . '/var/generated/TypeRegistry.php';

        $this->runConsoleCommand('flexible_graphql:generate-type-registry', [
            'FLEXIBLE_GRAPHQL_EXECUTOR' => 'amphp_v2',
            'FLEXIBLE_GRAPHQL_SCHEMA_TYPE' => 'graphql',
        ]);
        self::assertFileExists($registryFile);

        $this->runConsoleCommand('cache:clear', [
            'FLEXIBLE_GRAPHQL_EXECUTOR' => 'sync',
            'FLEXIBLE_GRAPHQL_SCHEMA_TYPE' => 'federation',
        ]);
        self::assertFileExists($registryFile);

        $registryCode = (string) file_get_contents($registryFile);
        self::assertStringContainsString('_Service(): ObjectType', $registryCode);
        self::assertStringContainsString('directive_federation__shareable', $registryCode);
    }

    public function testGeneratedTypeRegistryUsesScopedLocatorOnly(): void
    {
        $registryFile = self::fixtureAppDir() . '/var/generated/TypeRegistry.php';
        $this->runConsoleCommand('flexible_graphql:generate-type-registry');

        require_once $registryFile;

        $registryClass = 'App\\GraphQL\\TypeRegistry';
        self::assertTrue(class_exists($registryClass));

        $serviceId = 'flexible_graphql.default_resolver';
        $locator = new ServiceLocator([
            $serviceId => static fn (): object => new \stdClass(),
        ]);
        $registry = new $registryClass($locator);

        $constructor = new ReflectionMethod($registryClass, '__construct');
        $constructorType = $constructor->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $constructorType);
        self::assertSame(ServiceCollectionInterface::class, $constructorType->getName());

        $getService = new ReflectionMethod($registryClass, 'getService');
        $getService->setAccessible(true);

        self::assertIsObject($getService->invoke($registry, $serviceId));

        $this->expectException(NotFoundExceptionInterface::class);
        $getService->invoke($registry, 'missing.resolver.service');
    }

    private static function fixtureAppDir(): string
    {
        return dirname(__DIR__) . '/fixture-app';
    }

    /**
     * @param array<string, string> $env
     */
    private function runConsoleCommand(string $command, array $env = []): string
    {
        $fixtureAppDir = self::fixtureAppDir();
        self::removeDirectory($fixtureAppDir . '/var/cache/test');

        $consolePath = $fixtureAppDir . '/bin/console';
        $envPrefix = '';
        foreach ($env as $name => $value) {
            $envPrefix .= sprintf('%s=%s ', $name, escapeshellarg($value));
        }

        $execCommand = sprintf(
            '%sphp %s --env=test --no-interaction %s 2>&1',
            $envPrefix,
            escapeshellarg($consolePath),
            escapeshellarg($command)
        );

        $output = [];
        $exitCode = 1;
        exec($execCommand, $output, $exitCode);

        self::assertSame(0, $exitCode, implode(PHP_EOL, $output));

        return implode(PHP_EOL, $output);
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
