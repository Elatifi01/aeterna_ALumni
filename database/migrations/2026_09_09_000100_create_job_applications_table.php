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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();

            $table->string('external_id', 100);

            $table->string('title', 200);
            $table->string('company', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->string('source', 100)->nullable();
            $table->text('link');

            $table->enum('status', ['saved', 'applied'])->default('saved');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['alumni_id', 'external_id']);
            $table->index(['organization_id', 'alumni_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
