<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // Accounts are provisioned explicitly; database seeding never creates
        // a privileged account with a shared, publicly known password.
    }
}
