<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Sqlite;

use SQLite3;

trait SqliteHelper
{
    public function tableExists(SQLite3 $db, string $tableName): bool
    {
        return $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name ='${tableName}';") !== null;
    }
}
