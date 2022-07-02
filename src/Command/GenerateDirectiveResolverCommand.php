<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Command;

use Axtiva\FlexibleGraphql\Builder\CodeGeneratorBuilderInterface;
use Axtiva\FlexibleGraphql\FederationExtension\FederationSchemaExtender;
use Axtiva\FlexibleGraphql\Utils\SchemaBuilder;
use GraphQL\Type\Definition\Directive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateDirectiveResolverCommand extends Command
{
    protected static $defaultName = 'flexible_graphql:generate-directive-resolver';
    private string $schemaFiles;
    private string $schemaType;
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
            ->setDescription('generate executive directive resolver')
            ->addArgument('directive_name', InputArgument::REQUIRED, 'name of directive in sdl schema')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Read schema SDL from ' . $this->schemaFiles);
        $schema = SchemaBuilder::build($this->schemaFiles);
        if ($this->schemaType === 'federation') {
            $schema = FederationSchemaExtender::build($schema);
        }
        $codeGenerator = $this->codeGeneratorBuilder->build();
        /** @var Directive $directive */
        $directiveName = $input->getArgument('directive_name');
        $directive = $schema->getDirective($directiveName);
        if (empty($directive)) {
            $io->error('Directive did not found in schema ' . $directiveName);
            return Command::FAILURE;
        }

        $io->success('Directive resolver generated');
        foreach($codeGenerator->generateDirectiveResolver($directive, $schema) as $code) {
            $io->writeln($code->getFilename());
        }

        return Command::SUCCESS;
    }
}
