<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Command;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\Utils\SchemaBuilder;
use GraphQL\Type\Definition\CustomScalarType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'flexible_graphql:generate-scalar-resolver')]
class GenerateScalarResolverCommand extends Command
{
    private string $schemaFiles;
    private CodeGeneratorBuilderInterface $codeGeneratorBuilder;

    public function __construct(
        string $schemaFiles,
        CodeGeneratorBuilderInterface $codeGeneratorBuilder
    ) {
        parent::__construct();
        $this->schemaFiles = $schemaFiles;
        $this->codeGeneratorBuilder = $codeGeneratorBuilder;
    }

    protected function configure()
    {
        $this
            ->setDescription('generate custom scalar resolver')
            ->addArgument('custom_scalar_name', InputArgument::REQUIRED, 'name of custom scalar in sdl schema')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Read schema SDL from ' . $this->schemaFiles);
        $schema = SchemaBuilder::build($this->schemaFiles);
        $codeGenerator = $this->codeGeneratorBuilder->build();
        $scalarName = $input->getArgument('custom_scalar_name');
        $scalar = $schema->getType($scalarName);
        if (! $scalar instanceof CustomScalarType) {
            $io->error('Scalar did not found in schema ' . $scalarName);
            return Command::FAILURE;
        }
        $code = $codeGenerator->generateScalarResolver($scalar, $schema);
        $io->success('Scalar resolver generated');
        $io->writeln($code->getFilename());

        return Command::SUCCESS;
    }
}
