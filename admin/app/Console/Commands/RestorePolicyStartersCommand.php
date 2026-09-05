<?php

namespace App\Console\Commands;

use App\Support\HtmlSanitizer;
use App\Support\StorePolicies;
use Illuminate\Console\Command;

/**
 * Push starter policy HTML into the pages table (Admin → Policies source of truth).
 * Use after deploys when storefront was previously serving divergent hardcoded copies.
 */
class RestorePolicyStartersCommand extends Command
{
    protected $signature = 'policies:restore-starters
                            {slug? : Specific policy slug (terms-of-service, privacy-policy, …)}
                            {--only-overview : Only replace the known short Terms "1.OVERVIEW" paste}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Restore canonical starter HTML for storefront legal policies';

    public function handle(): int
    {
        $slugs = $this->argument('slug')
            ? [$this->argument('slug')]
            : StorePolicies::slugs();

        $dry = (bool) $this->option('dry-run');
        $onlyOverview = (bool) $this->option('only-overview');
        $changed = 0;

        foreach ($slugs as $slug) {
            if (! StorePolicies::isPolicySlug($slug)) {
                $this->error("Unknown policy slug: {$slug}");

                return self::FAILURE;
            }

            $page = StorePolicies::ensure($slug);
            $current = (string) $page->content;
            $isOverviewPaste = str_contains($current, '1.OVERVIEW')
                || str_contains($current, '1. OVERVIEW');

            if ($onlyOverview && $slug === 'terms-of-service' && ! $isOverviewPaste) {
                $this->line("skip {$slug} — not the short OVERVIEW paste");
                continue;
            }

            if ($onlyOverview && $slug !== 'terms-of-service') {
                $this->line("skip {$slug} — --only-overview targets terms-of-service");
                continue;
            }

            $starter = HtmlSanitizer::clean(StorePolicies::starterHtml($slug));
            $def = StorePolicies::definition($slug);

            if ($dry) {
                $this->info("[dry-run] would restore {$slug} (".strlen($current).' → '.strlen((string) $starter).' bytes)');
                $changed++;
                continue;
            }

            $page->update([
                'title' => $def['title'],
                'content' => $starter,
                'meta_title' => $def['title'],
                'meta_description' => $def['meta_description'],
                'published' => true,
                'slug' => $slug,
            ]);

            $this->info("restored {$slug}");
            $changed++;
        }

        $this->line($dry
            ? "Dry run complete ({$changed} would change)."
            : "Done ({$changed} policies updated). Storefront will refresh via revalidate.");

        return self::SUCCESS;
    }
}
