<?php

namespace Phpactor\Indexer\Tests\Adapter\Tolerant;

use Phpactor\Indexer\Adapter\Tolerant\TolerantCompositeIndexer;
use Phpactor\Indexer\Model\CompositeIndexer;
use Phpactor\Indexer\Model\TestIndexAgent;
use Phpactor\Indexer\Tests\IntegrationTestCase;
use Phpactor\Indexer\Adapter\Tolerant\TolerantIndexer;

class TolerantIndexerTestCase extends IntegrationTestCase
{
    protected function runSingleIndexer(TolerantIndexer $indexer, string $path): TestIndexAgent
    {
        $indexBuilder = new TolerantCompositeIndexer([$indexer]);

        // run the indexer twice - the results should not be affected
        $this->doRunIndexer($indexBuilder, $path);
        return $this->doRunIndexer($indexBuilder, $path);
    }

    protected function runIndexer(CompositeIndexer $indexBuilder, string $path): TestIndexAgent
    {
        // run the indexer twice - the results should not be affected
        $this->doRunIndexer($indexBuilder, $path);
        return $this->doRunIndexer($indexBuilder, $path);
    }

    private function doRunIndexer(CompositeIndexer $indexBuilder, string $path): TestIndexAgent
    {
        $agent = $this->indexAgentBuilder('src', $indexBuilder)
            ->buildTestAgent();

        $agent->indexer()->getJob()->run();

        return $agent;
    }
}
