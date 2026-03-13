<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Tests\E2e;

use App\Kernel;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

final class FixtureAppHttpGraphqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runConsoleCommand('cache:clear');
    }

    #[RunInSeparateProcess]
    public function testGraphqlHttpHandlesQueryMutationAndUnionUsingScopedLocatorResolvers(): void
    {
        $kernel = $this->bootFixtureKernel();
        $container = $kernel->getContainer();
        $locator = $container->get('flexible_graphql.type_registry.service_locator');

        self::assertInstanceOf(ContainerInterface::class, $locator);
        self::assertTrue($locator->has('App\\GraphQL\\Resolver\\Query\\GreetResolver'));
        self::assertTrue($locator->has('App\\GraphQL\\Resolver\\Query\\SearchResolver'));
        self::assertTrue($locator->has('App\\GraphQL\\Resolver\\Mutation\\CreateGroupAccountResolver'));
        self::assertTrue($locator->has('App\\GraphQL\\Directive\\UpperDirective'));
        self::assertTrue($locator->has('App\\GraphQL\\UnionResolveType\\SearchResultTypeResolver'));

        $greetResponse = $this->graphqlRequest($kernel, <<<'GRAPHQL'
query($name: String!, $input: GreetingInput!) {
  greet(name: $name, input: $input)
}
GRAPHQL, [
            'name' => 'alice',
            'input' => [
                'prefix' => 'hello',
                'suffix' => '!',
            ],
        ]);

        self::assertArrayNotHasKey('errors', $greetResponse, json_encode($greetResponse));
        self::assertSame('HELLO ALICE!', $greetResponse['data']['greet'] ?? null);

        $unionResponse = $this->graphqlRequest($kernel, <<<'GRAPHQL'
query($kind: String!) {
  search(kind: $kind) {
    __typename
    ... on GroupSearchResult {
      title
    }
    ... on UserSearchResult {
      username
    }
  }
}
GRAPHQL, [
            'kind' => 'group',
        ]);

        self::assertArrayNotHasKey('errors', $unionResponse, json_encode($unionResponse));
        self::assertSame('GroupSearchResult', $unionResponse['data']['search']['__typename'] ?? null);
        self::assertSame('Core Team', $unionResponse['data']['search']['title'] ?? null);

        $mutationResponse = $this->graphqlRequest($kernel, <<<'GRAPHQL'
mutation($input: CreateGroupAccountInput!) {
  createGroupAccount(input: $input) {
    id
    title
  }
}
GRAPHQL, [
            'input' => [
                'title' => 'Team Rocket',
            ],
        ]);

        self::assertArrayNotHasKey('errors', $mutationResponse, json_encode($mutationResponse));
        self::assertSame('grp-created-1', $mutationResponse['data']['createGroupAccount']['id'] ?? null);
        self::assertSame('Team Rocket', $mutationResponse['data']['createGroupAccount']['title'] ?? null);

        $kernel->shutdown();
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function graphqlRequest(Kernel $kernel, string $query, array $variables = []): array
    {
        $request = Request::create('/graphql', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode([
            'query' => $query,
            'variables' => $variables,
        ]));

        $response = $kernel->handle($request);
        self::assertSame(200, $response->getStatusCode(), $response->getContent());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload, $response->getContent());

        return $payload;
    }

    private function bootFixtureKernel(): Kernel
    {
        require_once self::fixtureAppDir() . '/autoload.php';
        require_once self::fixtureAppDir() . '/src/Kernel.php';

        $kernel = new Kernel('test', false);
        $kernel->boot();

        return $kernel;
    }

    private static function fixtureAppDir(): string
    {
        return dirname(__DIR__) . '/fixture-app';
    }

    private function runConsoleCommand(string $command): void
    {
        $fixtureAppDir = self::fixtureAppDir();
        self::removeDirectory($fixtureAppDir . '/var/cache/test');

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
