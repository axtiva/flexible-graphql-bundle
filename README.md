# Axtiva Flexible Graphql Bundle

Symfony bundle for [Flexible Graphql PHP](https://github.com/axtiva/flexible-graphql-php) for fast implementation graphql api

## Features

- SDL first code generation
- Fast integration to any project without breaking changes
- Lazy loading on schema definition
- Apollo Federation Support
- Executable directives
- Support symfony native opcache preload file generation

# Setup

Composer install:

```shell
composer require axtiva/flexible-graphql-bundle
```

Create bundle config:

```yaml
# content of config/packages/flexible_graphql.yaml
flexible_graphql:
  namespace: App\GraphQL # namespace where store GraphQL models and resolvers
  dir: '%kernel.project_dir%/src/GraphQL/' # path where it will be they save files
  schema_type: graphql # type of schema generation. Default is `graphql` or optional is `federation` for apollo federation support 
  schema_files: '%kernel.project_dir%/config/graphql/*.graphql' # path to graphql schema SDL files
  enable_preload: false # use Symfony preload if it true
  default_resolver: flexible_graphql.default_resolver # default resolver if it does not defined
```

Run command

```shell
bin/console cache:clear
```

Look at flexible_graphql.dir created files.

## Quick install

Quick install [guide](docs/index.md)

## Example integration

Look at example project [axtiva/example-integration/FlexibleGraphqlBundle](https://github.com/axtiva/example-integration/tree/master/FlexibleGraphqlBundle)

## Supported commands

```shell
bin/console list flexible_graphql
```
