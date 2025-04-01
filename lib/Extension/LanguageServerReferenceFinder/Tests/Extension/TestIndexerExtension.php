<?php

namespace Phpactor\Extension\LanguageServerReferenceFinder\Tests\Extension;

use Phpactor\Container\ContainerBuilder;
use Phpactor\Container\Extension;
use Phpactor\Indexer\IndexAgentBuilder;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\MapResolver\Resolver;

class TestIndexerExtension implements Extension
{
    public function load(ContainerBuilder $container): void
    {
        $container->register(Indexer::class, function () {
            $indexPath = __DIR__ . '/../Workspace';
            return IndexAgentBuilder::create(
                $indexPath,
                $indexPath,
            )->buildTestAgent()->indexer();
        });
    }


    public function configure(Resolver $schema): void
    {
    }
}
