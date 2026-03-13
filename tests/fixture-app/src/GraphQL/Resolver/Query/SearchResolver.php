<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver\Query;

use ArrayAccess;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;
use stdClass;

final class SearchResolver implements ResolverInterface
{
    public function __invoke(mixed $rootValue, array|ArrayAccess|null $args, mixed $context, ResolveInfo $info): mixed
    {
        $kind = (string) $this->readValue($args, 'kind', 'user');

        $result = new stdClass();
        if ($kind === 'group') {
            $result->__typename = 'GroupSearchResult';
            $result->id = 'group-1';
            $result->title = 'Core Team';

            return $result;
        }

        $result->__typename = 'UserSearchResult';
        $result->id = 'user-1';
        $result->username = 'alice';

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
