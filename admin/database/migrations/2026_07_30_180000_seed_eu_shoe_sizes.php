<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'] as $index => $name) {
            $exists = DB::table('sizes')->where('name', $name)->exists();
            if ($exists) {
                DB::table('sizes')->where('name', $name)->update([
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('sizes')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep size rows — products may already reference them.
    }
};
