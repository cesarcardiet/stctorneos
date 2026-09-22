@props([
    'value' => null,
    'disabled' => false,
])

<div class="ws-kit-sizes">
    <input type="hidden" name="kit_size" value="" class="ws-kit-size-empty">
    @foreach (\App\Models\Player::kitSizeGroups() as $group => $sizes)
        <p class="ws-kit-size-group">{{ $group }}</p>
        <div class="ws-kit-size-row">
            @foreach ($sizes as $size)
                <label class="ws-kit-size-opt">
                    <input type="radio" name="kit_size" value="{{ $size }}" @checked((string) old('kit_size', $value) === $size) @disabled($disabled)>
                    <span>{{ $size }}</span>
                </label>
            @endforeach
        </div>
    @endforeach
</div>
