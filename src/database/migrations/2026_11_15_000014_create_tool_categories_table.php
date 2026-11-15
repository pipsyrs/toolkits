<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Categories used by config('toolkits.tools') at the time of this migration.
    private const DEFAULTS = ['link' => 'Link', 'image' => 'Gambar', 'pdf' => 'PDF'];

    public function up(): void
    {
        Schema::create('tool_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 30)->unique();
            $table->string('name', 40);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        $sort = 0;

        DB::table('tool_categories')->insert(array_map(function (string $slug, string $name) use ($now, &$sort) {
            return [
                'id' => (string) Str::uuid7(),
                'slug' => $slug,
                'name' => $name,
                'sort' => ++$sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, array_keys(self::DEFAULTS), self::DEFAULTS));
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_categories');
    }
};
