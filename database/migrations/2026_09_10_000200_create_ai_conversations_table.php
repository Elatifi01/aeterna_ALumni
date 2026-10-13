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
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->morphs('owner');
            $table->string('title', 160);
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();

            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->text('content')->nullable();

            $table->string('tool_name', 60)->nullable();
            $table->json('tool_arguments')->nullable();
            $table->json('tool_result')->nullable();

            $table->timestamps();

            $table->index(['ai_conversation_id', 'id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
