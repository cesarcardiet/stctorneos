@props([
    'name' => 'nationality',
    'value' => null,
    'mode' => 'name',
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
])
@php
    $mode = $mode === 'code' ? 'code' : 'name';
    $current = old($name, $value);
    $selectedCode = $mode === 'code'
        ? \App\Support\Countries::guessCode($current)
        : \App\Support\Countries::codeFromName($current);
    $flag = \App\Support\Countries::flagUrl($selectedCode);
@endphp
<div class="country-select" data-country-select>
    <img class="country-select-flag" data-country-flag src="{{ $flag ?: asset('images/stc-logo.png') }}" alt="" @if (! $flag) hidden @endif>
    <select name="{{ $name }}" data-country-select-input @required($required) @disabled($disabled) {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="" data-flag="">{{ $placeholder }}</option>
        @endif
        @foreach (\App\Support\Countries::grouped() as $region => $codes)
            <optgroup label="{{ $region }}">
                @foreach ($codes as $code)
                    @php
                        $label = \App\Support\Countries::name($code);
                        $optionValue = $mode === 'code' ? $code : $label;
                        $selected = $mode === 'code'
                            ? strtoupper((string) $current) === $code
                            : mb_strtolower((string) $current) === mb_strtolower($label);
                    @endphp
                    <option
                        value="{{ $optionValue }}"
                        data-flag="{{ \App\Support\Countries::flagUrl($code) }}"
                        @selected($selected)
                    >{{ \App\Support\Countries::emoji($code) }} {{ $label }}</option>
                @endforeach
            </optgroup>
        @endforeach
        @if ($current && ! $selectedCode)
            <option value="{{ $current }}" selected>{{ $current }}</option>
        @endif
    </select>
</div>
