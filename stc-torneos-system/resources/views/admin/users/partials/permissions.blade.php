@foreach ($capabilities as $capability)
    <article class="users-permission-row is-{{ $capability['state'] }}">
        <div>
            <strong>{{ $capability['label'] }}</strong>
            <small>{{ $capability['state_label'] }}</small>
        </div>
        <b>{{ $capability['state_label'] }}</b>
    </article>
@endforeach
