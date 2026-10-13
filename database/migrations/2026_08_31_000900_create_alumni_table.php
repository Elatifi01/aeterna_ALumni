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
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('degree', 120)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('company', 120)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('city', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'graduation_year']);
            $table->index(['organization_id', 'company']);
            $table->index(['organization_id', 'last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumni');
    }
};
