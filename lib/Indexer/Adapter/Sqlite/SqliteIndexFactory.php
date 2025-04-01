<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\IndexFactoryInterface;
use SQLite3;
use Psr\Log\LoggerInterface;

class SqliteIndexFactory implements IndexFactoryInterface
{
    public function __construct(
        private string $path,
        private LoggerInterface $logger,
    ) {
    }

    public function create(): Index
    {
        $sqlite = new SQLite3($this->path);
        $sqlite->enableExceptions(true);
        $sqlite->enableExtendedResultCodes(true);

        return new SqliteIndex($sqlite, $this->logger);
    }
}
