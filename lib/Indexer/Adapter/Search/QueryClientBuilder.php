<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Search;

use Phpactor\Indexer\Model\RecordReferenceEnhancer;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\QueryClient;

class QueryClientBuilder
{
    public function __construct(private RecordReferenceEnhancer $enhancer)
    {
    }

    public function build(Index $index): QueryClient
    {
        return new QueryClient($index, $this->enhancer);
    }
}
