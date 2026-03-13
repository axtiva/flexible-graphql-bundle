<?php

declare(strict_types=1);

namespace App\GraphQL\UnionResolveType;

use Axtiva\FlexibleGraphql\Resolver\UnionResolveTypeInterface;
use GraphQL\Type\Definition\ResolveInfo;

final class SearchResultTypeResolver implements UnionResolveTypeInterface
{
    public function __invoke(mixed $model, mixed $context, ResolveInfo $info): mixed
    {
        if (is_object($model) && isset($model->__typename)) {
            return $info->schema->getType((string) $model->__typename);
        }

        return null;
    }
}
