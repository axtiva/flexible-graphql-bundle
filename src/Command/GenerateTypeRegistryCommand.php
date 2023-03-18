<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Command;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Utils\SchemaBuilder;
use Axtiva\FlexibleGraphql\Builder\TypeRegistryGeneratorBuilderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateTypeRegistryCommand extends Command
{
    protected static $defaultName = 'flexible_graphql:generate-type-registry';
    private string $schemaFiles;
    private string $schemaType;
    private TypeRegistryGeneratorBuilderInterface $typeRegistryGeneratorBuilder;
    private CodeGeneratorBuilderInterface $codeGeneratorBuilder;

    public function __construct(
        string $schemaFiles,
        string $schemaType,
        TypeRegistryGeneratorBuilderInterface $typeRegistryGeneratorBuilder,
        CodeGeneratorBuilderInterface $codeGeneratorBuilder
    ) {
        parent::__construct();
        $this->schemaFiles = $schemaFiles;
        $this->schemaType = $schemaType;
        $this->typeRegistryGeneratorBuilder = $typeRegistryGeneratorBuilder;
        $this->codeGeneratorBuilder = $codeGeneratorBuilder;
    }

    protected function configure()
    {
        $this
            ->setDescription('generate type registry class for lazy load schema')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Read schema SDL from ' . $this->schemaFiles);
        $schema = SchemaBuilder::build($this->schemaFiles);
        $codeGenerator = $this->codeGeneratorBuilder->build();
        foreach ($codeGenerator->generateAllTypes($schema) as $code) {
            $io->writeln($code->getFilename());
        }

        $registryGenerator = $this->typeRegistryGeneratorBuilder->build();
        $registryFilename = $registryGenerator->getConfig()->getTypeRegistryClassFileName();
        file_put_contents($registryFilename, $registryGenerator->generate($schema));
        $io->success('TypeRegistry generated and all types successfully');
        $io->writeln($registryFilename);

        return Command::SUCCESS;
    }
}
