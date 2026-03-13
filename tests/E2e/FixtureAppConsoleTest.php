<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Tests\E2e;

use PHPUnit\Framework\TestCase;

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
    }

    private static function fixtureAppDir(): string
    {
        return dirname(__DIR__) . '/fixture-app';
    }

    private function runConsoleCommand(string $command): string
    {
        $fixtureAppDir = self::fixtureAppDir();
        $consolePath = $fixtureAppDir . '/bin/console';
        $execCommand = sprintf(
            'php %s --env=test --no-interaction %s 2>&1',
            escapeshellarg($consolePath),
            escapeshellarg($command)
        );

        $output = [];
        $exitCode = 1;
        exec($execCommand, $output, $exitCode);

        self::assertSame(0, $exitCode, implode(PHP_EOL, $output));

        return implode(PHP_EOL, $output);
    }
}
