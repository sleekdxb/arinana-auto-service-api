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
        Schema::create('client_files', function (Blueprint $table) {
            $table->id(); // auto increment
            $table->string('file_id')->unique();
            $table->string('client_id')->index();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->index();
            $table->string('file_type')->index();
            $table->string('file_url');
            $table->timestamps();
            $table->index(['file_id', 'file_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_files');
    }
};
