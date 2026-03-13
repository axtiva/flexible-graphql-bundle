<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver\Mutation;

use ArrayAccess;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;
use stdClass;

final class CreateGroupAccountResolver implements ResolverInterface
{
    public function __invoke(mixed $rootValue, array|ArrayAccess|null $args, mixed $context, ResolveInfo $info): mixed
    {
        $input = $this->readValue($args, 'input', []);
        $title = (string) $this->readValue($input, 'title', 'Untitled');

        $result = new stdClass();
        $result->id = 'grp-created-1';
        $result->title = $title;

        return $result;
    }

    private function readValue(mixed $source, string $key, mixed $default): mixed
    {
        if ($source instanceof ArrayAccess && $source->offsetExists($key)) {
            return $source[$key];
        }

        if (is_array($source) && array_key_exists($key, $source)) {
            return $source[$key];
        }

        return $default;
    }
}
