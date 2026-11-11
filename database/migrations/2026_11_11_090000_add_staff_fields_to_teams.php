<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'role')) {
                $table->string('role')->nullable()->after('account_type');
            }
            if (!Schema::hasColumn('teams', 'access_level')) {
                $table->string('access_level')->default('SUPER_ADMIN')->after('role');
            }
            if (!Schema::hasColumn('teams', 'employee_id')) {
                $table->string('employee_id')->nullable()->unique()->after('access_level');
            }
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->dropColumn(['role', 'access_level', 'employee_id']);
        });
    }
};
