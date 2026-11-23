<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the roles, role_user and social_accounts tables. The
 * 2019_12_09_* migrations created them for features that were never
 * finished, and no code reads or writes them.
 *
 * There are no foreign keys between them (role_user only stores role and
 * user ids), so the order only matters for readability: the pivot table is
 * dropped first.
 *
 * down() creates the three tables again, empty, exactly as the original
 * migrations defined them. Rows that were in them are not restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('roles');
    }

    public function down(): void
    {
        // 2019_12_09_044534_create_roles_table
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('description');
            $table->timestamps();
        });

        // 2019_12_09_082630_create_role_user_table
        Schema::create('role_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('role_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->timestamps();
        });

        // 2019_12_09_094511_create_social_accounts_table
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->integer('user_id');
            $table->string('provider_user_id');
            $table->string('provider');
            $table->timestamps();
        });
    }
};
