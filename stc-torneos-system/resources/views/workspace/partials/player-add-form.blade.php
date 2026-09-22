@props([
    'category',
    'team',
])

<form method="post" action="{{ route('workspace.categories.players.store', $category) }}">
    @csrf
    <input type="hidden" name="team_id" value="{{ $team->id }}">

    <div class="ws-inline-add ws-roster-add">
        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Apellido" required>
        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="Nombre" required>
        <input type="text" name="document_number" value="{{ old('document_number') }}" placeholder="DNI">
        <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" placeholder="Tutor">
        <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}" placeholder="Tel. tutor">
        <input type="email" name="guardian_email" value="{{ old('guardian_email') }}" placeholder="Email tutor">
        <button type="submit" class="ws-btn">Añadir</button>
    </div>
</form>
