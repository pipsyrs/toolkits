<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Display category of tools; tools reference it by slug (config default or tool_settings override).
class ToolCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
