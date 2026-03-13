<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver\Query;

use ArrayAccess;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

final class GreetResolver implements ResolverInterface
{
    public function __invoke(mixed $rootValue, array|ArrayAccess|null $args, mixed $context, ResolveInfo $info): mixed
    {
        $name = (string) $this->readValue($args, 'name', '');
        $input = $this->readValue($args, 'input', []);

        $prefix = (string) $this->readValue($input, 'prefix', '');
        $suffix = (string) $this->readValue($input, 'suffix', '');

        return trim(sprintf('%s %s%s', $prefix, $name, $suffix));
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
