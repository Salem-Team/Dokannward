<?php

namespace App\Console\Commands;

use App\Support\Branding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * ROOTK post-deploy installer — migrations + platform brand seed without breaking injected branding.
 */
class RootkInstallCommand extends Command
{
    protected $signature = 'rootk:install
                            {--skip-migrate : Skip database migrations}
                            {--skip-seed : Skip platform brand seeder}
                            {--skip-brand-sync : Skip syncing .rootk branding into platform_brands}';

    protected $description = 'ROOTK product install: migrate, seed platform branding row, clear caches (no config:cache)';

    public function handle(): int
    {
        $this->components->info('ROOTK install starting…');

        if (! $this->option('skip-migrate')) {
            $this->components->task('Running migrations', function () {
                Artisan::call('migrate', ['--force' => true]);
                $this->line(Artisan::output());

                return true;
            });
        }

        if (! $this->option('skip-seed')) {
            $this->components->task('Seeding platform brand profile', function () {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\PlatformBrandSeeder',
                    '--force' => true,
                ]);
                $this->line(Artisan::output());

                return true;
            });
        }

        if (! $this->option('skip-brand-sync')) {
            $this->components->task('Syncing ROOTK branding into platform_brands', function () {
                Artisan::call('rootk:sync-branding');
                $this->line(Artisan::output());

                return true;
            });
        }

        $this->components->task('Clearing config cache (live ROOTK branding)', function () {
            Artisan::call('config:clear');

            return true;
        });

        $this->components->task('Clearing view / route caches', function () {
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            return true;
        });

        $this->components->task('Clearing application cache', function () {
            Artisan::call('cache:clear');

            return true;
        });

        Branding::bustCache();

        $this->newLine();
        $this->components->info('ROOTK install complete.');
        $this->line('Branding is read live from .rootk/* and ROOTK_TENANT_* env — do not run config:cache on managed tenants.');

        return self::SUCCESS;
    }
}
