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
        Schema::create('alumni_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->unsignedInteger('size_bytes')->default(0);

            $table->enum('status', ['pending', 'imported', 'undone'])->default('pending');

            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->unsignedInteger('rows_invalid')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_over_limit')->default(0);

            $table->json('errors')->nullable();

            $table->timestamp('imported_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::table('alumni', function (Blueprint $table) {
            $table->foreignId('import_id')->nullable()->after('organization_id')
                ->constrained('alumni_imports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_id');
        });

        Schema::dropIfExists('alumni_imports');
    }
};
