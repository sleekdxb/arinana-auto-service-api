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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique()->index();
            $table->string('email')->unique()->index();
            $table->string('hashed_email')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('account_type')->index();
            $table->string('business_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string(column: 'phone')->unique()->index();
            $table->string('state_id')->index();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });


        Schema::create('clients_statuses', function (Blueprint $table) {
            $table->id(); // auto increment
            $table->string('client_id')->index();
            $table->string('team_id')->nullable()->index();
            $table->string('state_id')->unique();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'code']);
        });




        Schema::create('client_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('client_id')->index();
            $table->string('session_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->timestamp('expires_at')->nullable();
            $table->integer('last_activity')->index();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('client_sessions');
        Schema::dropIfExists('clients_statuses');
    }
};
