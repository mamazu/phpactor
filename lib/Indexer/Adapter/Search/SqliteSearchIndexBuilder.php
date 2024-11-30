<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Search;

use Phpactor\Indexer\Adapter\Php\SqliteSearchIndex;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\SearchIndex;
use SQLite3;

class SqliteSearchIndexBuilder implements SearchIndexBuilderInterface
{
    public function __construct(private string $path)
    {
        $this->path = $path;
    }

    public function build(Index $index): SearchIndex
    {
        $sqlite = new SQLite3($this->path);
        $sqlite->enableExceptions(true);
        $sqlite->enableExtendedResultCodes(true);

        return new SqliteSearchIndex($sqlite);
    }
}
