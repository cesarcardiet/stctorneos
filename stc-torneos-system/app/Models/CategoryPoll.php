<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryPoll extends Model
{
    protected $fillable = [
        'category_id',
        'created_by',
        'question',
        'is_visible',
        'show_results',
        'voting_open',
        'allow_multiple',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'show_results' => 'boolean',
            'voting_open' => 'boolean',
            'allow_multiple' => 'boolean',
        ];
    }

    public static function voterKey(Request $request): string
    {
        if ($request->user()) {
            return 'user:'.$request->user()->id;
        }

        return 'guest:'.$request->session()->getId();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(CategoryPollOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CategoryPollVote::class);
    }

    public function hasVoted(string $voterKey): bool
    {
        return $this->votes()->where('voter_key', $voterKey)->exists();
    }

    /**
     * @return list<array{option_id: int, label: string, count: int, percent: float}>
     */
    public function resultBreakdown(): array
    {
        $counts = $this->votes()
            ->selectRaw('category_poll_option_id, count(*) as total')
            ->groupBy('category_poll_option_id')
            ->pluck('total', 'category_poll_option_id');

        $options = $this->relationLoaded('options')
            ? $this->options
            : $this->options()->get();

        $totalVotes = (int) $counts->sum();
        $rows = [];

        foreach ($options as $option) {
            $count = (int) ($counts[$option->id] ?? 0);
            $rows[] = [
                'option_id' => $option->id,
                'label' => $option->label,
                'count' => $count,
                'percent' => $totalVotes > 0 ? round(($count / $totalVotes) * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    public function totalVotes(): int
    {
        return $this->votes()->distinct('voter_key')->count('voter_key');
    }

    /**
     * @param  list<string>  $labels
     */
    public function syncOptions(array $labels): void
    {
        $labels = collect($labels)
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->values();

        $this->options()->delete();

        foreach ($labels as $index => $label) {
            $this->options()->create([
                'label' => $label,
                'sort_order' => $index,
            ]);
        }
    }

    public function statusBadges(): Collection
    {
        return collect([
            $this->is_visible ? 'Visible' : 'Oculta',
            $this->voting_open ? 'Votación abierta' : 'Votación cerrada',
            $this->show_results ? 'Muestra resultados' : 'Sin resultados',
            $this->allow_multiple ? 'Varias opciones' : 'Una opción',
        ]);
    }
}
