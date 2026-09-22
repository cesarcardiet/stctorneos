<x-layouts.stc
    title="Nueva notificación | STC Torneos"
    active="Comunicaciones"
    heading="Crear comunicación"
    subheading="Definí canal, audiencia y si se envía ahora o queda programada."
>
    <a class="back-link" href="{{ route('admin.communications.notifications') }}">← Volver a notificaciones</a>

    @if ($errors->any())
        <div class="system-alert">Revisá la notificación antes de guardar.</div>
    @endif

    <form class="ficha-review-panel admin-figma-form" method="post" action="{{ $url }}">
        @csrf
        @if (($method ?? 'POST') === 'PUT')
            @method('PUT')
        @endif

        <h3>Datos de la notificación</h3>
        <div class="ficha-row">
            <label class="span-2">Título <input name="title" value="{{ old('title', $notification->title) }}" placeholder="Cambio de horario Cancha 3"></label>
            <label>Canal
                <select name="channel">
                    @foreach (\App\Models\AppNotification::channelLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('channel', $notification->channel) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Audiencia
                <select name="audience">
                    @foreach (\App\Models\AppNotification::audienceLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience', $notification->audience) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="ficha-row">
            <label>Torneo
                <select name="tournament_id">
                    <option value="">Todos</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) old('tournament_id', $notification->tournament_id) === (int) $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Contenido vinculado
                <select name="content_post_id">
                    <option value="">Ninguno</option>
                    @foreach ($posts as $post)
                        <option value="{{ $post->id }}" @selected((int) old('content_post_id', $notification->content_post_id) === (int) $post->id)>{{ $post->title }}</option>
                    @endforeach
                </select>
            </label>
            <label class="span-2">Programar envío <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $notification->scheduled_at?->format('Y-m-d\TH:i')) }}"></label>
        </div>

        <h3>Mensaje</h3>
        <div class="ficha-row">
            <label class="span-4"><textarea name="body" rows="6" placeholder="Texto corto para push y bandeja de la app">{{ old('body', $notification->body) }}</textarea></label>
        </div>

        <div class="admin-figma-form-actions">
            <a class="admin-figma-cancel" href="{{ route('admin.communications.notifications') }}">CANCELAR</a>
            <div>
                <button class="ficha-save" type="submit">Guardar</button>
                <button class="ficha-save" type="submit" name="send_now" value="1">Enviar ahora</button>
            </div>
        </div>
    </form>
</x-layouts.stc>
