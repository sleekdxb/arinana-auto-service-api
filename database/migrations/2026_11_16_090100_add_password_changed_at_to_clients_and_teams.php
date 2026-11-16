<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients', 'teams'] as $table) {
            if (!Schema::hasColumn($table, 'password_changed_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->timestamp('password_changed_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['clients', 'teams'] as $table) {
            if (Schema::hasColumn($table, 'password_changed_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('password_changed_at');
                });
            }
        }
    }
};
