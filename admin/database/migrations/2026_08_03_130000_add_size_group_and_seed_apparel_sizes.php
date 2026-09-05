<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->string('size_group', 32)->default('shoe')->after('name');
            $table->index('size_group');
        });

        $now = now();

        // Existing numeric EU rows stay in the shoe group.
        DB::table('sizes')
            ->whereIn('name', ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'])
            ->update(['size_group' => 'shoe', 'updated_at' => $now]);

        $apparel = [
            'XS' => 210,
            'S' => 220,
            'M' => 230,
            'L' => 240,
            'XL' => 250,
            'XXL' => 260,
            'XXXL' => 270,
            'One Size' => 280,
        ];

        foreach ($apparel as $name => $sortOrder) {
            $exists = DB::table('sizes')->where('name', $name)->exists();
            if ($exists) {
                DB::table('sizes')->where('name', $name)->update([
                    'size_group' => 'apparel',
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('sizes')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'size_group' => 'apparel',
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sizes', function (Blueprint $table) {
            $table->dropIndex(['size_group']);
            $table->dropColumn('size_group');
        });
    }
};
