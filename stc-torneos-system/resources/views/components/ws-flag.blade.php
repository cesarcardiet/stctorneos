@props(['team'])

@if ($team?->flagUrl())
    <img {{ $attributes->class('ws-flag') }} src="{{ $team->flagUrl() }}" alt="{{ $team->countryName() }}" title="{{ $team->countryName() }}">
@endif
