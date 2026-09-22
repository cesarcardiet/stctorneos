<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryPollOption extends Model
{
    protected $fillable = [
        'category_poll_id',
        'label',
        'sort_order',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(CategoryPoll::class, 'category_poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CategoryPollVote::class, 'category_poll_option_id');
    }
}
