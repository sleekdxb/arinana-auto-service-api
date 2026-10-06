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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id(); // auto increment
            $table->string('client_id')->index();
            $table->string('book_id')->index();
            $table->text('vehicle_ids')->nullable();
            $table->string('booking_ref')->unique();
            $table->string('service')->nullable();
            $table->string('service_type')->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->text('note')->nullable();
            $table->string('state_id')->nullable();
            $table->timestamps();
        });


        Schema::create('booking_statuses', function (Blueprint $table) {
            $table->id(); // auto increment
            $table->string('book_id')->index();
            $table->string('team_id')->nullable()->index();
            $table->string('state_id')->unique();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'code']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_statuses');
    }
};
