<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdvisoryPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'content',
        'image',
        'author_id',
        'author_name',
        'published_at',
        'view_count',
        'likes_count',
        'language',
        'has_video',
        'video_url',
        'has_audio',
        'audio_url',
        'has_youtube_video',
        'youtube_video_url',
        'has_pdf',
        'pdf_url',
        'tags',
        'status',
        'featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'likes_count' => 'integer',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->author_name) && $post->author_id) {
                $author = User::find($post->author_id);
                if ($author) {
                    $post->author_name = $author->name;
                }
            }
        });
    }

    /**
     * Get the category this post belongs to
     */
    public function category()
    {
        return $this->belongsTo(AdvisoryCategory::class, 'category_id');
    }

    /**
     * Get the author of this post
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get tags as array
     */
    public function getTagsArrayAttribute()
    {
        if (empty($this->tags)) {
            return [];
        }
        return array_map('trim', explode(',', $this->tags));
    }

    /**
     * Scope to get only published posts
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'Published')
                     ->where('published_at', '<=', Carbon::now());
    }

    /**
     * Scope to get featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', 'Yes');
    }

    /**
     * Scope to search by title or tags
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%")
              ->orWhere('tags', 'like', "%{$term}%");
        });
    }

    /**
     * Increment view count
     */
    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    /**
     * Increment likes count
     */
    public function incrementLikesCount()
    {
        $this->increment('likes_count');
    }
}
