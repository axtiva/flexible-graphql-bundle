<?php

declare(strict_types=1);

namespace App\GraphQL\Directive;

use ArrayAccess;
use Axtiva\FlexibleGraphql\Resolver\DirectiveResolverInterface;
use GraphQL\Type\Definition\ResolveInfo;

final class UpperDirective implements DirectiveResolverInterface
{
    public function __invoke(callable $next, array|ArrayAccess|null $directiveArgs, mixed $rootValue, array|ArrayAccess|null $args, mixed $context, ResolveInfo $info): mixed
    {
        $value = $next($rootValue, $args, $context, $info);
        if (!is_string($value)) {
            return $value;
        }

        return mb_strtoupper($value);
    }
}
