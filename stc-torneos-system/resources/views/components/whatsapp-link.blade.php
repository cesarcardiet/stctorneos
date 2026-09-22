@props([
    'phone' => null,
    'text' => null,
    'url' => null,
    'label' => 'WhatsApp',
])
@php $url = $url ?: \App\Support\WhatsApp::url($phone, $text); @endphp
@if ($url)
    <a {{ $attributes->class('wa-share') }} href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
@endif
