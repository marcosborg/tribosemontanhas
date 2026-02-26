<?php

namespace App\Console\Commands;

use App\Support\DatabaseProfileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;

class DbSwitchProfile extends Command
{
    protected $signature = 'db:switch
                            {target : Target profile (sandbox|production)}
                            {--no-clear : Do not clear config cache after switching}';

    protected $description = 'Switch active DB_* settings in .env between sandbox and production profiles.';

    public function handle(DatabaseProfileManager $profileManager): int
    {
        $target = strtolower((string) $this->argument('target'));

        try {
            $missing = $profileManager->validateProfile($target);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($missing !== []) {
            $this->error('Profile [' . $target . '] is incomplete. Missing: ' . implode(', ', $missing));
            return self::FAILURE;
        }

        $profileManager->applyProfileToEnv($target, base_path('.env'));

        if (!$this->option('no-clear')) {
            Artisan::call('config:clear');
        }

        $config = $profileManager->getProfileConfig($target);

        $this->info('Database switched to [' . $target . '].');
        $this->line('DB_HOST=' . $config['host']);
        $this->line('DB_PORT=' . $config['port']);
        $this->line('DB_DATABASE=' . $config['database']);
        $this->line('DB_USERNAME=' . $config['username']);

        if (!$this->option('no-clear')) {
            $this->line('Config cache cleared.');
        }

        return self::SUCCESS;
    }
}
