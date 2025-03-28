<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Generator;
use Phpactor\Filesystem\Domain\Exception\NotSupported;
use Phpactor\Indexer\Model\Query\Criteria;
use Phpactor\Indexer\Model\Query\Criteria\AndCriteria;
use Phpactor\Indexer\Model\Query\Criteria\ExactShortName;
use Phpactor\Indexer\Model\Query\Criteria\IsClass;
use Phpactor\Indexer\Model\Query\Criteria\IsFunction;
use Phpactor\Indexer\Model\Query\Criteria\IsConstant;
use Phpactor\Indexer\Model\Query\Criteria\ShortNameBeginsWith;
use Phpactor\Indexer\Model\Record;
use Phpactor\Indexer\Model\RecordFactory;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\Record\HasFlags;
use Phpactor\Indexer\Model\SearchIndex;
use SQLite3;
use Webmozart\Assert\Assert;

class SqliteSearchIndex implements SearchIndex
{
    /**
     * Flush database after BATCH_SIZE updates
     */
    private const BATCH_SIZE = 1_000;
    private const TABLE_NAME = 'search_index';

    /** @var array<Record> */
    private array $subjects = [];

    private int $subjectCount = 0;

    private bool $dirty = false;

    public function __construct(private SQLite3 $sqlite)
    {
        // Check to see what tables exist
        $tableName = self::TABLE_NAME;
        if ($this->sqlite->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name ='${tableName}';") === null) {
            $this->sqlite->exec("CREATE TABLE ${tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_type TEXT,
                identifier TEXT,
                type TEXT,
                flags INTEGER
            );
            CREATE UNIQUE INDEX ${tableName}_idx ON ${tableName} (record_type, identifier);
            CREATE INDEX ${tableName}_record_type ON ${tableName} (record_type);
            CREATE INDEX ${tableName}_identifier ON ${tableName} (identifier);
            ");
        }
    }

    public function search(Criteria $criteria): Generator
    {
        if ($this->subjectCount > 0) {
            $this->flush();
        }

        [$condition, $parameters] = self::convertToCriteriaToCriteria($criteria);
        if ($condition !== '') {
            $condition = ' WHERE '.$condition;
        }

        $tableName = self::TABLE_NAME;
        $statement = $this->sqlite->prepare("SELECT record_type, identifier, type, flags FROM ${tableName} ". $condition);
        Assert::object($statement, 'Could not prepare sqlite search query.');

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement = $statement->execute();
        $row = $statement->fetchArray();
        while ($row) {
            [ $recordType, $identifier, $type, $flags ] = $row;
            $record = RecordFactory::create($recordType, $identifier);
            if ($record instanceof ClassRecord) {
                $record = $record->withType($type);
                $record->setFlags((int)$flags);
            }

            if (false === $criteria->isSatisfiedBy($record)) {
                var_dump($row);
                continue;
            }

            yield $record;
            $row = $statement->fetchArray();
        }
    }

    public function write(Record $record): void
    {
        $this->subjects[] = $record;
        $this->subjectCount++;

        if ($this->subjectCount > self::BATCH_SIZE) {
            $this->flush();
        }
    }

    public function remove(Record $record): void
    {
        $tableName = self::TABLE_NAME;
        $query = $this->sqlite->prepare("DELETE FROM ${tableName}
            WHERE record_type = :record_type AND identifier = :identifier AND type = :type");
        Assert::object($query, 'Could not prepare sqlite remove query.');

        $query->bindValue(':record_type', $record->recordType());
        $query->bindValue(':identifier', $record->identifier());
        $query->bindValue(':type', $record instanceof ClassRecord? $record->type() : '');
        $query->execute();
    }

    public function flush(): void
    {
        $tableName = self::TABLE_NAME;
        $query = $this->sqlite->prepare("INSERT OR IGNORE INTO ${tableName}
            (record_type, identifier, type, flags) VALUES
            (:record_type, :identifier, :type, :flags)");
        Assert::object($query, 'Could not prepare sqlite flush query.');

        $this->sqlite->exec('begin transaction');
        foreach ($this->subjects as $record) {
            $query->bindValue(':record_type', $record->recordType());
            $query->bindValue(':identifier', $record->identifier());
            $query->bindValue(':type', $record instanceof ClassRecord ? $record->type() : '');
            $query->bindValue(':flags', $record instanceof HasFlags ? $record->flags() : 0);
            $query->execute();
        }
        $this->sqlite->exec('commit');
        $this->subjects = [];
        $this->subjectCount = 0;
    }

    public function reset(): void
    {
        $tableName = self::TABLE_NAME;

        $this->sqlite->exec("DELETE FROM ${tableName} WHERE record_type = 'class'");
    }

    /** @return array{string, array<string, string>} */
    private function convertToCriteriaToCriteria(Criteria $criteria): array
    {
        $condition = '';
        $parameters = [];

        if ($criteria instanceof ExactShortName) {
            return [
                'identifier = :identifier',
                [':identifier' => $criteria->name()]
            ];
        } elseif ($criteria instanceof AndCriteria) {
            for ($i = 0; $i < count($criteria->criterias()); $i++) {
                $one = $criteria->criterias()[$i];
                [$conditionPart, $parameterParts] = self::convertToCriteriaToCriteria($one);
                if ($i !== count($criteria->criterias()) - 1) {
                    $condition.='AND ';
                }
                $condition.='('.$conditionPart.')';
                $parameters = array_merge($parameters, $parameterParts);
            }
            return [$condition, $parameters];
        } elseif ($criteria instanceof IsConstant) {
            return ['record_type = :record_type', [':record_type' => 'constant']];
        } elseif ($criteria instanceof IsClass) {
            return ['record_type = :record_type', [':record_type' => 'class']];
        } elseif ($criteria instanceof IsFunction) {
            return ['record_type = :record_type', [':record_type' => 'function']];
        } elseif ($criteria instanceof ShortNameBeginsWith) {
            return [
                'identifier GLOB \''.$criteria->name().'*\'',
                []
            ];
        } else {
            throw new NotSupported('Criteria "'.$criteria::class.'" not supported by SQLite');
        }
    }

}
