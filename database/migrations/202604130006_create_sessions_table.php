<?php

declare(strict_types=1);

use Wayfinder\Database\Database;
use Wayfinder\Database\Migration;

return new class implements Migration
{
    public function up(Database $database): void
    {
        $database->statement(<<<'SQL'
            CREATE TABLE sessions (
                id TEXT PRIMARY KEY,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            )
        SQL);
    }

    public function down(Database $database): void
    {
        $database->statement('DROP TABLE IF EXISTS sessions');
    }
};
