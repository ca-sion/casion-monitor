<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'notification_days' => 'array',
    ];

    /**
     * Get the parent notifiable model (athlete or trainer).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
