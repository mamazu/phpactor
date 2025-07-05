<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Phpactor\Indexer\Model\MemberReference;
use Phpactor\Indexer\Model\Record\ConstantRecord;
use Phpactor\Indexer\Model\Record\FileRecord;
use Phpactor\Indexer\Model\Record\FunctionRecord;
use Phpactor\TextDocument\TextDocumentUri;
use Psr\Log\LoggerInterface;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\Record;
use SplFileInfo;
use SQLite3;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Webmozart\Assert\Assert;
use Phpactor\Indexer\Model\Record\MemberRecord;
use Phpactor\TextDocument\ByteOffset;
use RuntimeException;
use DateTimeImmutable;
use InvalidArgumentException;

class SqliteIndex implements Index
{
    use SqliteHelper;
    public const CLASS_TABLE_NAME = 'class_index';
    public const MEMBER_TABLE_NAME = 'member_index';
    public const CONST_TABLE_NAME = 'constant_index';
    public const FUNCTION_TABLE_NAME = 'function_index';
    public const FILE_TABLE_NAME = 'file_index';
    public const FILE_REFERENCE_TABLE_NAME = 'file_refence_index';

    private array $records = [];

    public function __construct(
        private SQLite3 $db,
        private string $path,
        private LoggerInterface $logger,
    ) {
        $this->createTables();
    }

    public function get(Record $record): Record
    {
        if ($record instanceof ClassRecord) {
            $tableName = self::CLASS_TABLE_NAME;
            $sql = "SELECT * FROM {$tableName} WHERE fqn = :fqn";
            $args =[':fqn', $record->fqn()];

            if ($record->type() !== null) {
                $sql .= ' AND type = :type';
                $args[':type'] = $record->type();
            }

            $result = $this->queryArrayPrepared($sql .';', $args);
            if ($result !== false) {
                $record->setType($result['type']);
                $record->setStart(ByteOffset::fromInt($result['start']));
                $record->setEnd(ByteOffset::fromInt($result['end']));
                $record->setFilePath(TextDocumentUri::fromString($result['file_path']));
                $record->setFlags($result['flags']);
            }

            return $record;
        }

        if ($record instanceof FileRecord) {
            $tableName = self::FILE_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT * FROM ${tableName} WHERE file_path = :filePath;",
                [ ':filePath' => $record->filePath()],
            );

            if ($result !== false) {
                $record->setFilePath(TextDocumentUri::fromString($result['file_path']));
            }

            return $record;
        }

        if ($record instanceof MemberRecord) {
            $tableName = self::MEMBER_TABLE_NAME;

            $result = $this->queryArrayPrepared(
                "SELECT * FROM ${tableName} WHERE type = :type AND member_name = :memberName;",
                [':type' => $record->type(), ':member_name' => $record->memberName()]
            );

            if ($result !== false) {
                dump($result);
                dd('Mapping member');
            }
            return $record;
        }

        if ($record instanceof ConstantRecord) {
            $tableName = self::CONST_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT * FROM ${tableName} WHERE fqn = :fqn;",
                [':fqn'=> $record->fqn()],
            );

            if ($result !== false) {
                $record->setStart(ByteOffset::fromInt($result['start']));
                $record->setEnd(ByteOffset::fromInt($result['end']));
                $record->setFilePath(TextDocumentUri::fromString($result['file_path']));
            }
            return $record;
        }

        if ($record instanceof FunctionRecord) {
            $tableName = self::FUNCTION_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT * FROM ${tableName} WHERE fqn = :fqn;",
                [':fqn'=> $record->fqn()],
            );
            if ($result !== false) {
                $record->setStart(ByteOffset::fromInt($result['start']));
                $record->setEnd(ByteOffset::fromInt($result['end']));
                if ($result['file_path'] !== null) {
                    $record->setFilePath(TextDocumentUri::fromString($result['file_path']));
                }
            }
            return $record;
        }

        dd('Unknown record type: ' . $record::class);
        return $record;
    }

    public function has(Record $record): bool
    {
        if ($record instanceof ClassRecord) {
            $tableName = self::CLASS_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT COUNT(*) as amount FROM ${tableName} WHERE type = :type AND fqn = :fqn;",
                [
                    ':type' => $record->type(),
                    ':fqn' => $record->fqn()
                ]
            );

            return $result['amount'] > 0;
        }

        if ($record instanceof FileRecord) {
            $tableName = self::FILE_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT COUNT(*) as amount FROM ${tableName} WHERE file_path = :filePath;",
                [':filePath' => $record->filePath()]
            );

            return $result['amount'] > 0;
        }

        if ($record instanceof MemberRecord) {
            $tableName = self::MEMBER_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT COUNT(*) as amount FROM ${tableName} WHERE type = :type and member_name = :memberName;",
                [
                    ':type' => $record->type(),
                    ':memberName' => $record->shortName()
                ]
            );

            return $result['amount'] > 0;
        }

        if ($record instanceof FunctionRecord) {
            $tableName = self::FUNCTION_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT COUNT(*) as amount FROM ${tableName} WHERE fqn = :fqn;",
                [':fqn'=> $record->fqn()],
            );
            return $result['amount'] > 0;
        }

        if ($record instanceof ConstantRecord) {
            $tableName = self::CONST_TABLE_NAME;
            $result = $this->queryArrayPrepared(
                "SELECT COUNT(*) as amount FROM ${tableName} WHERE fqn = :fqn;",
                [':fqn'=> $record->fqn()],
            );
            return $result['amount'] > 0;
        }

        throw new InvalidArgumentException('Unknown Record class: ' . $record::class);
    }

    public function lastUpdate(): int
    {
        return time();
    }

    public function write(Record $record): void
    {
        if ($record instanceof ClassRecord) {
            // Do not index partial records
            if ($record->type() === null) {
                return;
            }
            $tableName = self::CLASS_TABLE_NAME;
            $statement = $this->db->prepare(
                "INSERT INTO {$tableName} VALUES (:type, :fqn, :start, :end, :path, :flags)
                ON CONFLICT DO UPDATE SET type = :type, fqn = :fqn, start = :start, end = :end, file_path = :path, flags = :flags WHERE fqn = :fqn AND type = :type",
            );
            $statement->bindValue(':type', $record->type());
            $statement->bindValue(':fqn', $record->fqn());
            $statement->bindValue(':start', $record->start()->toInt());
            $statement->bindValue(':end', $record->end()->toInt());
            $statement->bindValue(':path', $record->filePath() ?? '');
            $statement->bindValue(':flags', $record->flags());

            Assert::notFalse($statement->execute());
            return;
        }
        if ($record instanceof FileRecord) {
            $tableName = self::FILE_TABLE_NAME;
            $statement = $this->db->prepare("INSERT OR IGNORE INTO {$tableName} VALUES (:filePath, datetime('now'))");
            $statement->bindValue(':filePath', $record->filePath());

            //todo: references
            //if (count($record->references()->toArray()) > 0) {
            //dump($record->references()->toArray());
            //dd('References detected');
            //}
            Assert::notFalse($statement->execute());

            return;
        }

        if ($record instanceof MemberRecord) {
            $tableName = self::MEMBER_TABLE_NAME;
            $statement = $this->db->prepare(
                "INSERT INTO {$tableName} VALUES (:type, :member_name, :container_type)
                ON CONFLICT DO UPDATE SET type = :type, member_name = :member_name, container_type = :container_type WHERE type = :type AND member_name = :member_name",
            );
            $statement->bindValue(':type', $record->type());
            $statement->bindValue(':member_name', $record->memberName());
            $statement->bindValue(':container_type', $record->containerType());

            Assert::notFalse($statement->execute());
            return;
        }

        if ($record instanceof FunctionRecord) {
            $tableName = self::FUNCTION_TABLE_NAME;
            $statement = $this->db->prepare(
                "INSERT INTO {$tableName} VALUES (:fqn, :start, :end, :filePath)
                ON CONFLICT DO UPDATE SET start = :start, end = :end, file_path = :filePath WHERE fqn = :fqn",
            );
            $statement->bindValue(':fqn', $record->fqn());
            $statement->bindValue(':start', $record->start()?->toInt());
            $statement->bindValue(':end', $record->end()?->toInt());
            $statement->bindValue(':filePath', $record->filePath());

            Assert::notFalse($statement->execute());
            return;
        }

        if ($record instanceof ConstantRecord) {
            $tableName = self::CONST_TABLE_NAME;
            $statement = $this->db->prepare(
                "INSERT INTO {$tableName}(fqn, start, end, file_path) VALUES (:fqn, :start, :end, :filePath)
                ON CONFLICT DO UPDATE SET start = :start, end = :end, file_path = :filePath WHERE fqn = :fqn",
            );
            $statement->bindValue(':fqn', $record->fqn());
            $statement->bindValue(':start', $record->start()?->toInt());
            $statement->bindValue(':end', $record->end()?->toInt());
            $statement->bindValue(':filePath', $record->filePath());

            Assert::notFalse($statement->execute());
            return;
        }

        Assert::true(false, 'No writer defined for records of type: ' . $record->recordType());
    }

    public function isFresh(SplFileInfo $fileInfo): bool
    {
        $tableName = self::FILE_TABLE_NAME;
        $statement = $this->db->prepare("SELECT updated_at FROM ${tableName} WHERE file_path = :filePath;");
        $statement->bindValue(':filePath', 'file://'.$fileInfo->getRealPath());

        $result = $statement->execute()->fetchArray(\SQLITE3_ASSOC);
        if ($result === false) {
            return false;
        }

        try {
            $mtime = $fileInfo->getCTime();
        } catch (RuntimeException) {
            // file likely doesn't exist
            return false;
        }

        return new DateTimeImmutable($result['updated_at'])->getTimestamp() > $mtime;
    }

    public function reset(): void
    {
        $tablesToClear = [
            self::CLASS_TABLE_NAME ,
            self::MEMBER_TABLE_NAME ,
            self::CONST_TABLE_NAME,
            self::FUNCTION_TABLE_NAME ,
            self::FILE_TABLE_NAME ,
            self::FILE_REFERENCE_TABLE_NAME ,
        ];
        foreach ($tablesToClear as $table) {
            $this->db->exec("DROP TABLE ${table}");
        }

        $this->createTables();
    }

    public function exists(): bool
    {
        return true;
    }

    public function done(): void
    {
        $this->db->close();
        $this->db->open($this->path);
    }

    private function createTables(): void
    {
        // Create class table
        $tableName = self::CLASS_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                type TEXT,
                fqn TEXT,
                start INTEGER,
                end INTEGER NULL,
                file_path TEXT,
                flags INTEGER
            );
            CREATE INDEX ${tableName}_type ON ${tableName} (type);
            CREATE INDEX ${tableName}_fqn ON ${tableName} (fqn);
            CREATE UNIQUE INDEX ${tableName}_type_fqn ON ${tableName} (type, fqn);
            ");
        }

        // Create file table
        $tableName = self::FILE_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                file_path TEXT UNIQUE NOT NULL,
                updated_at DATETIME NOT NULL
            );
            ");
        }

        // Create file reference table
        $tableName = self::FILE_REFERENCE_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                file_id INTEGER,
                reference_type TEXT,
                reference_identifier TEXT,
                reference_start INTEGER NOT NULL,
                reference_container_type TEXT,
                reference_flags INTEGER,
                reference_end INTEGER
            );
            ");
        }

        // Create member table
        $tableName = self::MEMBER_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                type TEXT,
                member_name TEXT,
                container_type TEXT NULLALBE
            );
            CREATE INDEX ${tableName}_type ON ${tableName} (type);
            CREATE INDEX ${tableName}_member_name ON ${tableName} (member_name);
            CREATE UNIQUE INDEX ${tableName}_type_fqn ON ${tableName} (type, member_name);
            ");
        }

        // Create constant table
        $tableName = self::CONST_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                fqn TEXT,
                file_path TEXT,
                start INTEGER,
                end INTEGER NULLABLE
            );
            CREATE UNIQUE INDEX ${tableName}_fqn ON ${tableName} (fqn);
            ");
        }

        // Create function table
        $tableName = self::FUNCTION_TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (
                fqn TEXT,
                start INTEGER NULLABLE,
                end INTEGER NULLABLE,
                file_path TEXT NULLABLE
            );
            CREATE UNIQUE INDEX ${tableName}_fqn ON ${tableName} (fqn);
            ");
        }
    }
}
