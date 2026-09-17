<?php

namespace App\Console\Commands;

use App\Services\Branding\RootkPlatformBrandSyncService;
use Illuminate\Console\Command;

/**
 * Persist ROOTK runtime branding into platform_brands.
 */
class RootkSyncBrandingCommand extends Command
{
    protected $signature = 'rootk:sync-branding';

    protected $description = 'Sync .rootk branding / ROOTK_TENANT_* env into platform_brands';

    public function handle(RootkPlatformBrandSyncService $sync): int
    {
        $result = $sync->sync();

        if (! $result['synced']) {
            $this->components->warn('ROOTK branding sync skipped (not managed or no overrides).');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'ROOTK branding synced → platform_brands slug=%s domain=%s',
            $result['slug'] ?? '—',
            $result['domain'] ?? '—'
        ));

        return self::SUCCESS;
    }
}
