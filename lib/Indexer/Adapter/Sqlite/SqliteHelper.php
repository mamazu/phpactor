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

    /**
    * @param array<string, mixed> $params
    */
    public function queryArrayPrepared(
        string $query,
        array $params
    ): array|false {
        $statement = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }

        return $statement->execute()->fetchArray(\SQLITE3_ASSOC);

    }
}
