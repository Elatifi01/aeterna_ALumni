<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SLUG = 'assistant.use';

    /**
     * Run the migration.
     */
    public function up(): void
    {
        Permission::where('slug', self::SLUG)->update(['name' => 'Utiliser Aeterna Assistant']);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Permission::where('slug', self::SLUG)->update(['name' => 'Utiliser Aeterna AI']);
    }
};
