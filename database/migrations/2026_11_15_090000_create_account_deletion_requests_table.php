<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_deletion_requests')) {
            return;
        }

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('client_id')->index();
            $table->string('client_name')->nullable();
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->string('account_type')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->index();
            $table->string('team_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
