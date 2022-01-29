# Axtiva Flexible Graphql Bundle

Symfony bundle for Flexible Graphql PHP

- Support symfony native opcache preload file generation

# Setup

Composer install:

```
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

Generate models and type registry:

```shell
bin/console flexible_graphql:generate-type-registry
```

Generate Directive resolver for executable directives:

```shell
bin/console flexible_graphql:generate-directive-resolver directive_name
```

Generate Field Resolver:

```shell
bin/console flexible_graphql:generate-field-resolver type_name field_name
```

Generate Scalar Resolver:

```shell
bin/console flexible_graphql:generate-scalar-resolver scalar_name
```