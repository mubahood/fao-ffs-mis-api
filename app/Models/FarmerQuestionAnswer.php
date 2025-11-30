<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FarmerQuestionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'content',
        'author_id',
        'author_name',
        'author_location',
        'likes_count',
        'has_image',
        'image_url',
        'has_audio',
        'audio_url',
        'has_video',
        'video_url',
        'has_youtube_video',
        'youtube_video_url',
        'has_pdf',
        'pdf_url',
        'is_approved',
        'is_accepted',
        'status',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    /**
     * Boot method to set default values
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($answer) {
            if (empty($answer->author_name) && $answer->author_id) {
                $author = User::find($answer->author_id);
                if ($author) {
                    $answer->author_name = $author->name;
                    
                    // Set location from user's district
                    if ($author->district_id) {
                        $district = \App\Models\Location::find($author->district_id);
                        if ($district) {
                            $answer->author_location = $district->name;
                        }
                    }
                }
            }
        });

        static::saved(function ($answer) {
            // Update question status when answer is approved
            if ($answer->is_approved == 'Yes') {
                $question = $answer->question;
                if ($question) {
                    $question->updateStatus();
                }
            }
        });
    }

    /**
     * Get the question this answer belongs to
     */
    public function question()
    {
        return $this->belongsTo(FarmerQuestion::class, 'question_id');
    }

    /**
     * Get the author of this answer
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope to get approved answers
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', 'Yes');
    }

    /**
     * Scope to get published answers
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'Published');
    }

    /**
     * Increment likes count
     */
    public function incrementLikesCount()
    {
        $this->increment('likes_count');
    }

    /**
     * Mark as accepted answer
     */
    public function markAsAccepted()
    {
        // Unmark other answers
        self::where('question_id', $this->question_id)
            ->where('id', '!=', $this->id)
            ->update(['is_accepted' => 'No']);
        
        // Mark this as accepted
        $this->is_accepted = 'Yes';
        $this->save();
    }
}
