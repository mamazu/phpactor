<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Psr\Log\LoggerInterface;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\Record;
use SplFileInfo;
use SQLite3;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Webmozart\Assert\Assert;

class SqliteIndex implements Index
{
    use SqliteHelper;

    private const CLASS_TABLE_NAME = 'class_index';
    private const FILE_TABLE_NAME = 'file_index';

    private array $records = [];

    public function __construct(
        private SQLite3 $db,
        private LoggerInterface $logger,
    ) {
        // Create class table
        $tableName = self::CLASS_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                type TEXT,
                fqn TEXT,
                start INTEGER,
                end INTEGER,
                file_path TEXT,
                flags INTEGER
            );
            CREATE INDEX ${tableName}_type ON ${tableName} (type);
            CREATE INDEX ${tableName}_fqn ON ${tableName} (fqn);
            CREATE UNIQUE INDEX ${tableName}_type_fqn ON ${tableName} (type, fqn);
            ");
        }

        $tableName = self::FILE_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                filePath TEXT UNIQUE
            );
            ");
        }
    }

    public function get(Record $record): Record
    {
        dump('Gettting', $record);
        if ($record instanceof ClassRecord) {
            $tableName = self::CLASS_TABLE_NAME;
            $statement = $this->db->prepare("SELECT * FROM {$tableName} WHERE fqn = :fqn AND type = :type;");
            $statement->bindValue(':type', $record->type());
            $statement->bindValue(':fqn', $record->fqn());

            $result = $statement->execute()->fetchArray();
            if ($result !== false) {
                dd('Mapping');
            } else {
                dump('Found nothing');
            }
            return $record;
        }

        //$statement = $this->db->prepare("SELECT record_type, identifier, type, flags FROM ${tableName} WHERE record_type = :record_type AND identifier = :identifier;");
        //$statement->bindValue(':record_type', $record->recordType());
        //$statement->bindValue(':identifier', $record->identifier());

        return $record;
    }

    public function has(Record $record): bool
    {
        $tableName = self::TABLE_NAME;
        $statement = $this->db->prepare("SELECT COUNT(*) FROM ${tableName} WHERE record_type = :record_type;");
        $statement->bindValue(':record_type', $record->recordType());
        $result = $statement->execute();
        $count = $result->fetchArray(\SQLITE3_NUM)[0];

        return $count > 0;
    }

    public function lastUpdate(): int
    {
        return time();
    }

    public function write(Record $record): void
    {
        dump('Writing', $record);
        if ($record instanceof ClassRecord) {
            $tableName = self::CLASS_TABLE_NAME;
            $statement = $this->db->prepare(
                "INSERT INTO {$tableName} VALUES (:type, :fqn, :start, :end, :path, :flags) ON CONFLICT DO UPDATE SET type = :type, fqn = :fqn, start = :start, end = :end, file_path = :path, flags = :flags WHERE fqn = :fqn AND type = :type",
            );
            $statement->bindValue(':type', $record->type());
            $statement->bindValue(':fqn', $record->fqn());
            $statement->bindValue(':start', $record->start()->toInt());
            $statement->bindValue(':end', $record->end()->toInt());
            $statement->bindValue(':path', $record->filePath() ?? '');
            $statement->bindValue(':flags', $record->flags());

            $result = $statement->execute();
        } else {
            Assert::true(false, 'No writer defined for records of type: ' . $record->recordType());
        }
    }

    public function isFresh(SplFileInfo $fileInfo): bool
    {
        return false;
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
