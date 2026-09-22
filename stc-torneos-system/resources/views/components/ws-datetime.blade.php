@props([
    'name' => 'scheduled_at',
    'label' => 'Día y hora',
    'value' => null,
    'required' => true,
])

@php
    $date = trim((string) old($name.'_date', ''));
    $time = trim((string) old($name.'_time', ''));
    $raw = old($name, $value);

    if ($date === '' || $time === '') {
        if ($raw instanceof \DateTimeInterface) {
            $date = $date !== '' ? $date : $raw->format('Y-m-d');
            $time = $time !== '' ? $time : $raw->format('H:i');
        } elseif (is_string($raw) && trim($raw) !== '') {
            $normalized = str_replace('T', ' ', trim($raw));
            if ($date === '') {
                $date = substr($normalized, 0, 10);
            }
            if ($time === '' && preg_match('/\d{2}:\d{2}/', $normalized, $found)) {
                $time = $found[0];
            }
        }
    }

    if ($time === '') {
        $time = '15:00';
    }
@endphp

<label {{ $attributes->class('ws-datetime') }}>
    {{ $label }}
    <span class="ws-datetime-fields">
        <input type="date" class="ws-datetime-date" name="{{ $name }}_date" value="{{ $date }}" min="1900-01-01" max="2100-12-31" @required($required) lang="es-AR" autocomplete="off">
        <input type="time" class="ws-datetime-time" name="{{ $name }}_time" value="{{ $time }}" @required($required) step="60" lang="es-AR" autocomplete="off">
    </span>
</label>
