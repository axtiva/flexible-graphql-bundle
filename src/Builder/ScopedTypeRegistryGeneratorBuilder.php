<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Builder;

use Axtiva\FlexibleGraphql\Builder\TypeRegistryGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Generator\TypeRegistry\TypeRegistryGeneratorInterface;
use Axtiva\FlexibleGraphqlBundle\Generator\ScopedServiceCollectionTypeRegistryGenerator;

final class ScopedTypeRegistryGeneratorBuilder implements TypeRegistryGeneratorBuilderInterface
{
    public function __construct(
        private readonly TypeRegistryGeneratorBuilderInterface $baseBuilder,
    ) {
    }

    public function build(): TypeRegistryGeneratorInterface
    {
        return new ScopedServiceCollectionTypeRegistryGenerator($this->baseBuilder->build());
    }
}
