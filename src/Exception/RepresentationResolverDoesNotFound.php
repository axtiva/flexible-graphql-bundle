<?php
declare(strict_types=1);

namespace Axtiva\FlexibleGraphqlBundle\Exception;

use Axtiva\FlexibleGraphqlBundle\ApolloFederation\Representation;
use RuntimeException;
use Throwable;

class RepresentationResolverDoesNotFound extends RuntimeException
{
    public function __construct(Representation $representation)
    {
        parent::__construct(sprintf('Representation for %s does not found', $representation->getTypename()));
    }
}