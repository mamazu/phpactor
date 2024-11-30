<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Search;

use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\SearchIndex;

interface SearchIndexBuilderInterface
{
    public function build(Index $index): SearchIndex;
}
