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

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            // Unique vehicle identifier
            $table->string('veh_id')->unique();

            // Vehicle information
            $table->string('make')->index();
            $table->string('model')->index();
            $table->unsignedSmallInteger('year')->index();

            // Identification
            $table->string('vin', 17)->unique();
            $table->string('plate_number')->index();

            // Vehicle details
            $table->unsignedBigInteger('mileage')->default(0)->index();
            $table->string('color')->index();

            $table->timestamps();

            // Composite indexes for common filtering
            $table->index(['make', 'model']);
            $table->index(['make', 'model', 'year']);
            $table->index(['plate_number', 'make']);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
