<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Encore\Admin\Auth\Database\Administrator;

class ImportTask extends Model
{
    protected $fillable = [
        'task_name',
        'type',
        'file_path',
        'status',
        'message',
        'initiated_by',
        'mapping',
        'total_rows',
        'imported_rows',
        'failed_rows',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'mapping' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'initiated_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
