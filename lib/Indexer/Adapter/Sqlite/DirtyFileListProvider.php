<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use Generator;
use Phpactor\Indexer\Model\DirtyDocumentTracker;
use Phpactor\Indexer\Model\FileList;
use Phpactor\Indexer\Model\FileListProvider;
use Phpactor\Indexer\Model\Index;
use Phpactor\TextDocument\TextDocumentUri;
use SQLite3;
use SplFileInfo;

class DirtyFileListProvider implements FileListProvider, DirtyDocumentTracker
{
    use SqliteHelper;
    const TABLE_NAME = 'dirty_files';

    /**
     * @var array<string, bool>
     */
    private array $seen = [];

    public function __construct(private SQLite3 $db)
    {
        $tableName = self::TABLE_NAME;
        if (!$this->tableExists($this->db, $tableName)) {
            $this->db->exec("CREATE TABLE {$tableName} (file_path TEXT);");
        }
    }

    public function markDirty(TextDocumentUri $uri): void
    {
        if (isset($this->seen[$uri->path()])) {
            return;
        }

        $result = $this->queryArrayPrepared(
            sprintf('INSERT INTO %s VALUES (:file_name)', self::TABLE_NAME),
            ['file_name' => $uri->path()],
        );


        $this->seen[$uri->path()] = true;
    }

    public function provideFileList(Index $index, ?string $subPath = null): FileList
    {
        return FileList::fromInfoIterator($this->paths());
    }

    /**
     * @return Generator<SplFileInfo>
     */
    private function paths(): Generator
    {
        $result = $this->queryArrayPrepared(sprintf('SELECT file_path FROM %s', self::TABLE_NAME));
        if ($result === false) {
            return;
        }

        foreach ($result as $row) {
            yield $result['file_path'];
        }

        $this->db->exec(sprintf('TRUNCATE TABLE %s;', self::TABLE_NAME));
    }
}
