<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryPollVote extends Model
{
    protected $fillable = [
        'category_poll_id',
        'category_poll_option_id',
        'user_id',
        'voter_key',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(CategoryPoll::class, 'category_poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(CategoryPollOption::class, 'category_poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
