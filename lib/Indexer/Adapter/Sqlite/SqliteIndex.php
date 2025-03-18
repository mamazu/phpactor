<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\Record;
use SplFileInfo;
use SQLite3;

class SqliteIndex implements Index
{
    private const TABLE_NAME = 'search_index';

    private array $records = [];

    public function __construct(private SQLite3 $db)
    {
    }

    public function get(Record $record): Record
    {
        $tableName = self::TABLE_NAME;
        $result = $this->sqlite->prepare("SELECT record_type, identifier, type, flags FROM ${tableName} WHERE record_type = :record_type;");
        $result->bindValue(':record_type', $record->recordType());
        dd();
    }

    public function has(Record $record): bool
    {
        $tableName = self::TABLE_NAME;
        $result = $this->sqlite->prepare("SELECT record_type, identifier, type, flags FROM ${tableName} WHERE record_type = :record_type;");
        $result->bindValue(':record_type', $record->recordType());
        dd();
    }

    public function lastUpdate(): int
    {
        return time();
    }

    public function write(Record $record): void
    {
    }

    public function isFresh(SplFileInfo $fileInfo): bool
    {
        return count($this->records);
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
        return;
    }
}
