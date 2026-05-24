<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

class ClientInstall extends Command
{
    protected $signature = 'client:install
        {--client= : Override APP_CLIENT for this run (useful in testing)}';

    protected $description = 'Idempotently seed branding settings for the active client. Safe to run on every deploy.';

    public function handle(): int
    {
        // Note: --client only affects the branding_seed lookup. It does not
        // re-run ClientServiceProvider or swap the active client's bindings.
        $client = $this->option('client') ?? config('app.client', 'directonderweg');
        $seed   = config("clients.{$client}.branding_seed", []);

        if (empty($seed)) {
            $this->warn("No branding_seed found for client [{$client}]. Nothing to do.");
            return self::SUCCESS;
        }

        $this->info("Installing branding for client [{$client}] (parent_id = 1) …");

        $seeded  = [];
        $skipped = [];

        foreach ($seed as $key => $value) {
            // parent_id = 1 is the super-admin row created on first install.
            // Settings are scoped by owner; all global/admin settings live under id 1.
            // 'type' is intentionally null — the settings() helper reads all rows with
            // matching parent_id regardless of type; branding keys are untyped.
            $setting = Setting::firstOrCreate(
                ['name' => $key, 'parent_id' => 1],
                ['value' => $value],
            );

            if ($setting->wasRecentlyCreated) {
                $seeded[] = $key;
            } else {
                $skipped[] = $key;
            }
        }

        if ($seeded) {
            $this->line('  <fg=green>Seeded:</>  ' . implode(', ', $seeded));
        }
        if ($skipped) {
            $this->line('  <fg=yellow>Skipped</> (already set): ' . implode(', ', $skipped));
        }

        $this->info(sprintf(
            'Done — %d seeded, %d already set.',
            count($seeded),
            count($skipped),
        ));

        return self::SUCCESS;
    }
}
