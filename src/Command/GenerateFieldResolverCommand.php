<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Command;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Utils\SchemaBuilder;
use GraphQL\Type\Definition\ObjectType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'flexible_graphql:generate-field-resolver')]
class GenerateFieldResolverCommand extends Command
{
    protected static $defaultName = 'flexible_graphql:generate-field-resolver';
    private string $schemaFiles;
    private CodeGeneratorBuilderInterface $codeGeneratorBuilder;

    public function __construct(
        string $schemaFiles,
        string $schemaType,
        CodeGeneratorBuilderInterface $codeGeneratorBuilder
    ) {
        parent::__construct();
        $this->schemaFiles = $schemaFiles;
        $this->schemaType = $schemaType;
        $this->codeGeneratorBuilder = $codeGeneratorBuilder;
    }

    protected function configure()
    {
        $this
            ->setDescription('generate field resolver')
            ->addArgument('type_name', InputArgument::REQUIRED, 'name of type in sdl schema')
            ->addArgument('field_name', InputArgument::REQUIRED, 'name of type field in sdl schema')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Read schema SDL from ' . $this->schemaFiles);
        $schema = SchemaBuilder::build($this->schemaFiles);
        $codeGenerator = $this->codeGeneratorBuilder->build();
        $typeName = $input->getArgument('type_name');
        $fieldName = $input->getArgument('field_name');
        $type = $schema->getType($typeName);
        if (empty($type) || ! $type instanceof ObjectType) {
            $io->error('Type did not found in schema ' . $typeName);
            return Command::FAILURE;
        }
        $field = $type->getField($fieldName);
        if (empty($field)) {
            $io->error('Field did not found in type ' . $fieldName);
            return Command::FAILURE;
        }

        $io->success('Field resolver generated');
        foreach ($codeGenerator->generateFieldResolver($type, $field, $schema) as $code) {
            $io->writeln($code->getFilename());
        }

        return Command::SUCCESS;
    }
}
