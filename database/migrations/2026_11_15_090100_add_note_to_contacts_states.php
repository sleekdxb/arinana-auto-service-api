<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contacts_states', 'note')) {
            Schema::table('contacts_states', function (Blueprint $table) {
                $table->text('note')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contacts_states', 'note')) {
            Schema::table('contacts_states', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
