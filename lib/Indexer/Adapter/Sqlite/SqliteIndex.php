<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\Record;
use SplFileInfo;
use SQLite3;

class SqliteIndex implements Index
{
    private array $records = [];

    public function __construct(private SQLite3 $db)
    {
    }

    public function get(Record $record): Record
    {
    }

    public function has(Record $record): bool
    {
    }

    public function lastUpdate(): int
    {
    }

    public function write(Record $record): void
    {
    }

    public function isFresh(SplFileInfo $fileInfo): bool
    {
    }

    public function reset(): void
    {
        // Delete all indexing tables
    }

    public function exists(): bool
    {
    }

    public function done(): void
    {
    }
}
