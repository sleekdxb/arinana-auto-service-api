<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vin', 64)->nullable()->change();
        });

        DB::table('vehicles')->where('vin', '')->update(['vin' => null]);
    }

    public function down(): void
    {
        DB::table('vehicles')->whereNull('vin')->update(['vin' => DB::raw('veh_id')]);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vin', 64)->nullable(false)->change();
        });
    }
};
