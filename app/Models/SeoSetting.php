<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    protected $fillable = [
        'page', 'meta_title', 'meta_description', 'meta_keywords', 'h1',
        'canonical_url', 'robots_meta', 'og_title', 'og_description', 'og_image',
        'twitter_title', 'twitter_description', 'twitter_image', 'schema_json',
        'breadcrumb_title', 'alt_text', 'is_active',
    ];

    protected $casts = ['schema_json' => 'array', 'is_active' => 'boolean'];

    public function seoable()
    {
        return $this->morphTo();
    }
}
