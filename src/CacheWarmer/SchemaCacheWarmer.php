<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\CacheWarmer;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Utils\FederationV22SchemaExtender;
use Axtiva\FlexibleGraphql\Utils\SchemaBuilder;
use Axtiva\FlexibleGraphql\Builder\TypeRegistryGeneratorBuilderInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class SchemaCacheWarmer implements CacheWarmerInterface
{
    private TypeRegistryGeneratorBuilderInterface $typeRegistryGeneratorBuilder;
    private string $schemaFiles;
    private string $schemaType;
    private CodeGeneratorBuilderInterface $codeGeneratorBuilder;

    public function __construct(
        string $schemaFiles,
        string $schemaType,
        TypeRegistryGeneratorBuilderInterface $typeRegistryGeneratorBuilder,
        CodeGeneratorBuilderInterface $codeGeneratorBuilder
    ) {
        $this->typeRegistryGeneratorBuilder = $typeRegistryGeneratorBuilder;
        $this->schemaFiles = $schemaFiles;
        $this->schemaType = $schemaType;
        $this->codeGeneratorBuilder = $codeGeneratorBuilder;
    }

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $classes = [];
        $schema = SchemaBuilder::build($this->schemaFiles);
        if ($this->schemaType === 'federation') {
            foreach (SchemaBuilder::getSchemaAst($this->schemaFiles) as $ast) {
                $schema = FederationV22SchemaExtender::build($schema, $ast);
            }
        }
        $codeGenerator = $this->codeGeneratorBuilder->build();
        foreach ($codeGenerator->generateAllTypes($schema) as $code){
            $classes[] = $code->getClassname();
        }

        $registryGenerator = $this->typeRegistryGeneratorBuilder->build();
        $registryFilename = $registryGenerator->getConfig()->getTypeRegistryClassFileName();
        file_put_contents($registryFilename, $registryGenerator->generate($schema));
        $classes[] = $registryGenerator->getConfig()->getTypeRegistryFullClassName();

        return $classes;
    }
}
