@php
    $results = $poll->results ?? null;
    $canVote = ($poll->voting_open ?? false) && ! ($poll->has_voted ?? false);
    $inputType = ($poll->allow_multiple ?? false) ? 'checkbox' : 'radio';
    $inputName = ($poll->allow_multiple ?? false) ? 'option_ids[]' : 'option_ids[]';
@endphp

<article @class(['ws-card', 'ws-poll-card', 'is-hidden' => ! ($poll->is_visible ?? true) && ! empty($canEdit)])>
    <header class="ws-poll-head">
        <div>
            <p class="stc-eyebrow">Encuesta</p>
            <h3>{{ $poll->question }}</h3>
            @if (! ($poll->is_visible ?? true) && ! empty($canEdit))
                <span class="ws-poll-badge is-hidden">Oculta</span>
            @endif
        </div>
        @if (! empty($canEdit))
            <details class="ws-poll-admin">
                <summary class="ws-btn ghost">Editar</summary>
                <form class="ws-form ws-poll-form" method="post" action="{{ route('workspace.categories.polls.update', [$category, $poll]) }}">
                    @csrf
                    @method('PATCH')
                    <label>Pregunta
                        <input type="text" name="question" value="{{ $poll->question }}" required maxlength="500">
                    </label>
                    <label>Opciones (una por línea)
                        <textarea name="options" rows="4" required @disabled($poll->votes_count > 0)>{{ $poll->options->pluck('label')->implode("\n") }}</textarea>
                    </label>
                    @if ($poll->votes_count > 0)
                        <p class="ws-muted">Ya hay votos: no podés cambiar las opciones, solo la configuración.</p>
                    @endif
                    <div class="ws-poll-flags">
                        <label><input type="checkbox" name="is_visible" value="1" @checked($poll->is_visible)> Visible</label>
                        <label><input type="checkbox" name="show_results" value="1" @checked($poll->show_results)> Mostrar resultado</label>
                        <label><input type="checkbox" name="voting_open" value="1" @checked($poll->voting_open)> Votación abierta</label>
                        <label><input type="checkbox" name="allow_multiple" value="1" @checked($poll->allow_multiple)> Elija más de 1</label>
                    </div>
                    <div class="ws-form-actions">
                        <button type="submit" class="ws-btn">Guardar</button>
                    </div>
                </form>
                <form method="post" action="{{ route('workspace.categories.polls.destroy', [$category, $poll]) }}" data-confirm="¿Eliminar esta encuesta?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ws-btn danger">Eliminar encuesta</button>
                </form>
                <p class="ws-muted ws-poll-votes-meta">{{ $poll->voter_count ?? 0 }} votantes · {{ $poll->votes_count ?? 0 }} selecciones</p>
            </details>
        @endif
    </header>

    @if ($canVote)
        <form class="ws-poll-vote" method="post" action="{{ route('workspace.categories.polls.vote', [$category, $poll]) }}">
            @csrf
            <ul class="ws-poll-options">
                @foreach ($poll->options as $option)
                    <li>
                        <label>
                            <input type="{{ $inputType }}" name="option_ids[]" value="{{ $option->id }}">
                            <span>{{ $option->label }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
            <button type="submit" class="ws-btn">Votar</button>
        </form>
    @elseif ($poll->has_voted ?? false)
        <p class="ws-muted ws-poll-voted">Ya votaste en esta encuesta.</p>
    @elseif (! ($poll->voting_open ?? false))
        <p class="ws-muted ws-poll-closed">Votación cerrada.</p>
    @endif

    @if ($results)
        <div class="ws-poll-results">
            <p class="stc-eyebrow">Resultados</p>
            <ul>
                @foreach ($results as $row)
                    <li>
                        <div class="ws-poll-result-row">
                            <strong>{{ $row['label'] }}</strong>
                            <span>{{ $row['count'] }} · {{ $row['percent'] }}%</span>
                        </div>
                        <div class="ws-poll-bar" style="--pct: {{ $row['percent'] }}%;"></div>
                    </li>
                @endforeach
            </ul>
            <p class="ws-muted">{{ $poll->voter_count ?? 0 }} votantes</p>
        </div>
    @endif
</article>
