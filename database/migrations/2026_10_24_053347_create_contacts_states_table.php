<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts_states', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key
            $table->string('state_id');
            $table->string('con_id');
            $table->string('name');
            $table->string('code');
            $table->string('team_id');
            $table->timestamps(); // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts_states');
    }
};
