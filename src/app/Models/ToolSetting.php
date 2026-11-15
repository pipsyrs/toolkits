<?php

namespace App\Models;

use App\Enums\ToolStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ToolSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'status',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'status' => ToolStatus::class,
        ];
    }
}
