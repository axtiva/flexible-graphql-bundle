<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Resolver;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use Axtiva\FlexibleGraphql\Resolver\ResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class DefaultResolver implements ResolverInterface
{
    private PropertyAccessor $propertyAccessor;

    public function __construct(PropertyAccessor $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function __invoke($rootValue, $args, $context, ResolveInfo $info)
    {
        $property = Executor::defaultFieldResolver($rootValue, $args, $context, $info);

        if ($property === null) {
            return $this->propertyAccessor->getValue($rootValue, $info->fieldName);
        }

        return $property;
    }
}