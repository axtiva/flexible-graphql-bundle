<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Generator;

use Axtiva\FlexibleGraphql\Generator\Config\TypeRegistryGeneratorConfigInterface;
use Axtiva\FlexibleGraphql\Generator\TypeRegistry\TypeRegistryGeneratorInterface;
use GraphQL\Type\Schema;

final class ScopedServiceCollectionTypeRegistryGenerator implements TypeRegistryGeneratorInterface
{
    public function __construct(
        private readonly TypeRegistryGeneratorInterface $baseGenerator,
    ) {
    }

    public function getConfig(): TypeRegistryGeneratorConfigInterface
    {
        return $this->baseGenerator->getConfig();
    }

    public function generate(Schema $schema): string
    {
        $generatedCode = $this->baseGenerator->generate($schema);
        $generatedCode = str_replace(
            'use Psr\\Container\\ContainerInterface;',
            'use Symfony\\Contracts\\Service\\ServiceCollectionInterface;',
            $generatedCode
        );

        $generatedCode = str_replace(
            'private ContainerInterface $container;',
            'private readonly ServiceCollectionInterface $locator;',
            $generatedCode
        );

        $generatedCode = str_replace(
            'public function __construct(ContainerInterface $container)',
            'public function __construct(ServiceCollectionInterface $locator)',
            $generatedCode
        );

        $generatedCode = str_replace(
            '$this->container = $container;',
            '$this->locator = $locator;',
            $generatedCode
        );

        return str_replace(
            '$service = $this->container->get($id);',
            '$service = $this->locator->get($id);',
            $generatedCode
        );
    }
}
