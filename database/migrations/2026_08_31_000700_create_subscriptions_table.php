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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->enum('status', ['pending', 'active', 'expired', 'canceled'])->default('pending');
            $table->decimal('price', 10, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
