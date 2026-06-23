<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Check for duplicate usernames before applying constraints
        $duplicates = DB::table('users')
            ->select('username', DB::raw('COUNT(*) as count'))
            ->groupBy('username')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $duplicateUsernames = $duplicates->pluck('username')->toArray();
            $errorMessage = "Migration aborted: Duplicate usernames found. Please resolve them before running this migration. Duplicated Usernames: " . implode(', ', $duplicateUsernames);
            
            // Log the error and throw an exception to halt the migration
            Log::error($errorMessage);
            throw new \Exception($errorMessage);
        }

        // 2. Apply Schema Changes
        Schema::table('users', function (Blueprint $table) {
            // Remove the unique constraint from the email column.
            // Using array syntax ['email'] lets Laravel automatically resolve the default constraint name (users_email_unique)
            $table->dropUnique(['email']);

            // Add the unique constraint to the username column
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the unique constraint from the username column
            $table->dropUnique(['username']);

            // Restore the unique constraint on the email column
            $table->unique('email');
            
            
        });
    }
};
