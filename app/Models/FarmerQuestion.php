<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FarmerQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'author_name',
        'author_location',
        'view_count',
        'likes_count',
        'has_image',
        'image_url',
        'has_audio',
        'audio_url',
        'status',
    ];

    protected $casts = [
        'view_count' => 'integer',
        'likes_count' => 'integer',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($question) {
            if (empty($question->author_name) && $question->author_id) {
                $author = User::find($question->author_id);
                if ($author) {
                    $question->author_name = $author->name;
                    
                    // Set location from user's district
                    if ($author->district_id) {
                        $district = \App\Models\Location::find($author->district_id);
                        if ($district) {
                            $question->author_location = $district->name;
                        }
                    }
                }
            }
        });
    }

    /**
     * Get the author of this question
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get all answers for this question
     */
    public function answers()
    {
        return $this->hasMany(FarmerQuestionAnswer::class, 'question_id');
    }

    /**
     * Get approved answers
     */
    public function approvedAnswers()
    {
        return $this->answers()->where('is_approved', 'Yes')->where('status', 'Published');
    }

    /**
     * Get accepted answer
     */
    public function acceptedAnswer()
    {
        return $this->answers()->where('is_accepted', 'Yes')->first();
    }

    /**
     * Get answers count
     */
    public function getAnswersCountAttribute()
    {
        return $this->answers()->where('is_approved', 'Yes')->count();
    }

    /**
     * Scope to get open questions
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'Open');
    }

    /**
     * Scope to get answered questions
     */
    public function scopeAnswered($query)
    {
        return $query->where('status', 'Answered');
    }

    /**
     * Scope to search by title or content
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%");
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

    /**
     * Update status to Answered if has approved answers
     */
    public function updateStatus()
    {
        $approvedCount = $this->answers()->where('is_approved', 'Yes')->count();
        
        if ($approvedCount > 0 && $this->status != 'Closed') {
            $this->status = 'Answered';
            $this->save();
        }
    }
}
