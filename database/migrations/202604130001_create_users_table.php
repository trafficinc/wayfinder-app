<?php

declare(strict_types=1);

use Wayfinder\Database\Database;
use Wayfinder\Database\Migration;

return new class implements Migration
{
    public function up(Database $database): void
    {
        $database->statement(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT,
                is_admin INTEGER NOT NULL DEFAULT 0
            )
        SQL);
    }

    public function down(Database $database): void
    {
        $database->statement('DROP TABLE IF EXISTS users');
    }
};
