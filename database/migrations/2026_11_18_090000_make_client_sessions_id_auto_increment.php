<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $type = strtolower(Schema::getColumnType('client_sessions', 'id'));

        if (str_contains($type, 'int') || !in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE client_sessions DROP PRIMARY KEY, DROP COLUMN id');
        DB::statement('ALTER TABLE client_sessions ADD id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    }

    public function down(): void
    {
    }
};
