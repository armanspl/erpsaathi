<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'category', 'name', 'render_mode', 'is_default', 'is_favorite',
        'page_width_mm', 'page_height_mm', 'background_color', 'background_image_path', 'elements', 'raw_html',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_favorite' => 'boolean',
            'page_width_mm' => 'float',
            'page_height_mm' => 'float',
            'elements' => 'array',
        ];
    }
}
