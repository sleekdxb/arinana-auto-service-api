<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicle_files', function (Blueprint $table) {
            $table->id(); // auto increment
            $table->string('veh_id')->unique();
            $table->string('client_id')->index();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->index();
            $table->string('file_type')->index();
            $table->string('file_url');
            $table->timestamps();
            $table->index(['veh_id', 'file_type']);
        });


        Schema::create('vehicle_file_states', function (Blueprint $table) {
            $table->id();
            $table->string('file_id')->index();
            $table->string('team_id')->nullable()->index();
            $table->string('state');
            $table->string('code')->nullable();
            $table->timestamp('create_at')->nullable();
            $table->timestamp('update_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_files');
        Schema::dropIfExists('vehicle_file_states');

    }
};
