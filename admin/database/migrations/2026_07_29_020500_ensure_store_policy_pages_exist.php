<?php

use App\Support\StorePolicies;
use Illuminate\Database\Migrations\Migration;

/**
 * Guarantee every canonical legal policy exists as a pages row.
 * Starter HTML is used only when the row is missing — never overwrites edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        StorePolicies::ensureAll();
    }

    public function down(): void
    {
        // Intentional no-op: do not delete live legal pages on rollback.
    }
};
