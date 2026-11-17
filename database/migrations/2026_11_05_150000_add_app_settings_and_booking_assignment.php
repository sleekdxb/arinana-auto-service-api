<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('team_id')->nullable();
                $table->timestamps();
            });
        }

        $columns = [
            'estimated_completion_at' => fn(Blueprint $table) => $table->timestamp('estimated_completion_at')->nullable(),
            'technician_id' => fn(Blueprint $table) => $table->string('technician_id')->nullable()->index(),
            'reminder_sent_at' => fn(Blueprint $table) => $table->timestamp('reminder_sent_at')->nullable(),
        ];

        foreach ($columns as $column => $add) {
            if (!Schema::hasColumn('bookings', $column)) {
                Schema::table('bookings', $add);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
