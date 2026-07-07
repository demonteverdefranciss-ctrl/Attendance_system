<?php

namespace App\Console\Commands;

use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Console\Command;

class SeedDemoAccounts extends Command
{
    protected $signature = 'accounts:seed-demo';

    protected $description = 'Create or refresh demo admin/teacher/parent accounts for CRUD and UAT testing';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => DemoAccountsSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
