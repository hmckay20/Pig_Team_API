<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ComputerStatus', function (Blueprint $table) {
            $table->id();
            $table->dateTime('CheckTime');
            $table->decimal('RAMUsage', 5, 2);
            $table->decimal('StorageUsage', 5, 2);
            $table->timestamps(); // This will add both created_at and updated_at columns
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('computer_status');
    }
};
