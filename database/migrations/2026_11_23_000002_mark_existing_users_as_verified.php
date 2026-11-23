<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email verification became mandatory for the SPA and the API in this
 * release. Accounts that already exist when this migration runs were never
 * asked to verify their address, so they are marked as verified now (with
 * the time this migration ran) to avoid locking every existing user out
 * after an upgrade. On a fresh install the users table is empty and nothing
 * changes. Accounts registered after this migration must verify their email.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * Not reversible: after this migration runs there is no record of which
     * accounts it changed, so rolling back leaves every account as it is.
     */
    public function down(): void
    {
        //
    }
};
