<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Resolver;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use ArrayAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class DefaultResolver implements ResolverInterface
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function __invoke(mixed $rootValue, array|ArrayAccess|null $args, mixed $context, ResolveInfo $info): mixed
    {
        $property = Executor::defaultFieldResolver($rootValue, $args, $context, $info);

        if ($property === null) {
            return $this->propertyAccessor->getValue($rootValue, $info->fieldName);
        }

        return $property;
    }
}
