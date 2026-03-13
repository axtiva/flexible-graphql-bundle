<?php

declare(strict_types=1);

namespace App\Controller;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ServiceCollectionInterface;

final class GraphqlController
{
    /**
     * @param ServiceCollectionInterface<object> $locator
     */
    public function __construct(
        private readonly ServiceCollectionInterface $locator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!class_exists('App\\GraphQL\\TypeRegistry', false)) {
            $registryFile = dirname(__DIR__, 2) . '/var/generated/TypeRegistry.php';
            if (is_file($registryFile)) {
                require_once $registryFile;
            }
        }

        $registryClass = 'App\\GraphQL\\TypeRegistry';
        $typeRegistry = new $registryClass($this->locator);
        $schema = new Schema([
            'query' => $typeRegistry->getType('Query'),
            'mutation' => $typeRegistry->getType('Mutation'),
            'directives' => $typeRegistry->getDirectives(),
        ]);

        $validationRules = array_merge(
            GraphQL::getStandardValidationRules(),
            [
                new Rules\QueryComplexity(PHP_INT_MAX),
            ]
        );

        $debugFlag = DebugFlag::INCLUDE_DEBUG_MESSAGE
            | DebugFlag::INCLUDE_TRACE
            | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS
            | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS;

        $rawBody = $request->getContent();
        $body = [];
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $executionResult = GraphQL::executeQuery(
            $schema,
            (string) ($body['query'] ?? ''),
            null,
            [],
            (array) ($body['variables'] ?? []),
            is_string($body['operationName'] ?? null) ? $body['operationName'] : null,
            null,
            $validationRules
        );

        return new JsonResponse($executionResult->toArray($debugFlag));
    }
}
