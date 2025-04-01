<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Search;

use Phpactor\Indexer\Adapter\Sqlite\SqliteSearchIndex;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\SearchIndex;
use Psr\Log\LoggerInterface;
use SQLite3;

class SqliteSearchIndexBuilder implements SearchIndexBuilderInterface
{
    public function __construct(
        private string $path,
        private LoggerInterface $logger,
    ) {
    }

    public function build(Index $index): SearchIndex
    {
        $sqlite = new SQLite3($this->path);
        $sqlite->enableExceptions(true);
        $sqlite->enableExtendedResultCodes(true);

        return new SqliteSearchIndex(
            $sqlite,
            $this->logger,
        );
    }
}
