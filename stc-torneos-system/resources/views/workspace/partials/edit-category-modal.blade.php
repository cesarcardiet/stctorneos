@php
    $modalities = $modalities ?? \App\Models\Category::modalities();
    $formats = $formats ?? \App\Models\Category::competitionFormats();
@endphp

<x-ws-modal id="edit-category" title="Editar categoría">
    <form class="ws-form" method="post" action="#" enctype="multipart/form-data" data-ws-edit-cat-form>
        @csrf
        @method('PATCH')
        <p class="ws-setup-kicker">Imagen</p>
        @include('workspace.partials.category-banner-field', [
            'src' => $defaultBanner ?? asset('images/stc-logo.png'),
            'logos' => $bannerLogos ?? [],
            'currentPath' => '',
            'compact' => true,
        ])
        <p class="ws-setup-kicker">Datos de la categoría</p>
        <div class="ws-setup-grid">
            <label>Nombre <input type="text" name="name" required></label>
            <label>Años / edad <input type="text" name="birth_year" required></label>
            <label>Rama
                <select name="branch">
                    <option>Masculina</option>
                    <option>Femenina</option>
                    <option>Mixta</option>
                </select>
            </label>
            <label>Modalidad
                <select name="modality">
                    @foreach ($modalities as $modality)
                        <option value="{{ $modality }}">{{ $modality }}</option>
                    @endforeach
                </select>
            </label>
            <label>Formato
                <select name="competition_format">
                    @foreach ($formats as $format)
                        <option value="{{ $format }}">{{ $format }}</option>
                    @endforeach
                </select>
            </label>
            <label>Grupos <input type="number" name="groups_count" min="0" max="8"></label>
            <label>Estado
                <select name="status">
                    @foreach (\App\Models\Category::statusLabels() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Victoria <input type="number" name="points_win" min="0" max="10"></label>
            <label>Empate <input type="number" name="points_draw" min="0" max="10"></label>
            <label>Derrota <input type="number" name="points_loss" min="0" max="10"></label>
        </div>
        <label>Descripción <textarea name="description" rows="2"></textarea></label>
        <div class="ws-form-actions">
            <button type="button" class="ws-btn ghost" data-ws-close>Cancelar</button>
            <button type="submit" class="ws-btn">Guardar</button>
        </div>
    </form>
</x-ws-modal>
