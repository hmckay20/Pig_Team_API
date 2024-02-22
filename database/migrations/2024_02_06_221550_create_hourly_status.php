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
        Schema::create('status_hourly', function (Blueprint $table) {
            $table->id();
            $table->dateTime('time_of_status'); // Time of the status
            $table->bigInteger('total_reads_today'); // Total reads today
            // Placeholder for reads for every reader. Consider using a separate table or a JSON column.
            $table->json('reads_for_every_reader')->nullable();
            $table->decimal('memory_usage', 5, 2); // Memory usage
            $table->decimal('storage_usage', 5, 2); // Storage usage
            $table->decimal('diskstation_use', 5, 2); // Diskstation use
            $table->timestamps(); // Optional: if you want to track when records are created or updated
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_hourly');
    }
};
