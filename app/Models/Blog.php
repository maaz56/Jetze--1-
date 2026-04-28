<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'user_id',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * SEO meta for this blog
     */
    public function seo()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * Author (optional)
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seoTitle()
    {
        return $this->seo->meta_title ?? $this->title;
    }

    public function seoDescription()
    {
        return $this->seo->meta_description ?? $this->excerpt;
    }

    public function canonicalUrl()
    {
        return $this->seo->canonical_url ?? route('blog.show', $this->slug);
    }


    /**
     * Automatically generate slug if not set
     */
    // protected static function booted()
    // {
    //     static::creating(function ($blog) {
    //         if (empty($blog->slug)) {
    //             $blog->slug = str()->slug($blog->title);
    //         }
    //     });
    // }
}
