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
        Schema::table('alumni', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken();

            $table->boolean('must_change_password')->default(true);
            $table->timestamp('credentials_sent_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->boolean('portal_access')->default(true);

            $table->string('login_token', 64)->nullable();
            $table->timestamp('login_token_expires_at')->nullable();

            $table->index('login_token');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            $table->dropIndex(['login_token']);

            $table->dropColumn([
                'password',
                'remember_token',
                'must_change_password',
                'credentials_sent_at',
                'password_changed_at',
                'last_login_at',
                'portal_access',
                'login_token',
                'login_token_expires_at',
            ]);
        });
    }
};
