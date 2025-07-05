<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Tests\Adapter\Sqlite;

use Phpactor\Indexer\Model\Record\ConstantRecord;
use Phpactor\Indexer\Model\Record\FunctionRecord;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\TestUtils\PHPUnit\TestCase;
use Psr\Log\NullLogger;
use SQLite3;
use Phpactor\Indexer\Adapter\Sqlite\SqliteIndex;

class SqliteIndexTest extends TestCase
{
    private SqliteIndex $index;

    private SQLite3 $db;

    public function setUp():void
    {
        $sqlite = __DIR__.'/testIndex.sqlite';
        file_put_contents(__DIR__.'/testIndex.sqlite', '');

        $this->db = new SQLite3($sqlite);

        $this->index = new SqliteIndex(
            $this->db,
            $sqlite,
            new NullLogger(),
        );
    }

    public function tearDown(): void
    {
        unlink(__DIR__.'/testIndex.sqlite');
    }

    public function testClass(): void
    {
        $record = ClassRecord::fromName('SomeClass');
        $record->setType('class');
        self::setPosition($record, '/testing.php', 10, 30);
        $record->setFlags(ClassRecord::FLAG_ATTRIBUTE);

        // Write index
        $this->index->write($record);

        // Expect entry in DB
        $this->assertEntryCount(SqliteIndex::CLASS_TABLE_NAME, 1);

        // Create query object
        $readingRecord = ClassRecord::fromName('SomeClass');
        $readingRecord = $this->index->get($readingRecord);

        // Assert result is correct
        $this->assertInstanceOf(ClassRecord::class, $readingRecord);
        $this->assertSame('class', $readingRecord->recordType());
        $this->assertSame('SomeClass', (string) $readingRecord->fqn());
        $this->assertPosition($readingRecord, 'file:///testing.php', 10, 30);
        $this->assertSame(ClassRecord::FLAG_ATTRIBUTE, $readingRecord->flags());
    }

    public function testConstant(): void
    {
        $record = ConstantRecord::fromName('CONST_NAME');
        self::setPosition($record, '/testing.php', 10, 30);

        $this->index->write($record);

        $this->assertEntryCount(SqliteIndex::CONST_TABLE_NAME, 1);

        $readingRecord = ConstantRecord::fromName('CONST_NAME');
        $readingRecord = $this->index->get($readingRecord);

        $this->assertInstanceOf(ConstantRecord::class, $readingRecord);
        $this->assertSame('CONST_NAME', (string) $readingRecord->fqn());
        $this->assertPosition($readingRecord, 'file:///testing.php', 10, 30);
    }

    public function testFunction(): void
    {
        $record = FunctionRecord::fromName('mb_string');
        self::setPosition($record, '/c_test.php', 10, 30);

        $this->index->write($record);

        $this->assertEntryCount(SqliteIndex::FUNCTION_TABLE_NAME, 1);

        $readingRecord = FunctionRecord::fromName('mb_string');
        $readingRecord = $this->index->get($readingRecord);

        $this->assertInstanceOf(FunctionRecord::class, $readingRecord);
        $this->assertSame('mb_string', (string) $readingRecord->fqn());
        $this->assertPosition($readingRecord, 'file:///c_test.php', 10, 30);
    }

    private function assertEntryCount(string $tableName, int $expectedCount): void
    {
        $this->assertSame($expectedCount, $this->db->querySingle("SELECT COUNT(*) FROM $tableName"));
    }

    private function setPosition(object $record, string $path, int $start, int $end): void
    {
        $record->setFilePath(TextDocumentUri::fromString($path));
        $record->setStart(ByteOffset::fromInt($start));
        $record->setEnd(ByteOffset::fromInt($end));
    }

    private function assertPosition(object $record, string $path, int $start, int $end): void
    {
        $this->assertSame($path, $record->filePath());
        $this->assertSame($start, $record->start()->toInt());
        $this->assertSame($end, $record->end()->toInt());
    }
}
