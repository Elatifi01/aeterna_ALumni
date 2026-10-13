<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('alumni_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['registered', 'canceled'])->default('registered');
            $table->timestamp('registered_at');
            $table->boolean('attended')->default(false);
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->unique(['alumni_id', 'event_id']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumni_event');
    }
};
