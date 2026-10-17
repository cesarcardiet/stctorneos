import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

const realtime = window.StcRealtime;
if (realtime?.enabled && realtime.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: realtime.key,
        wsHost: realtime.host,
        wsPort: realtime.port ?? 80,
        wssPort: realtime.port ?? 443,
        forceTLS: (realtime.scheme || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });
    window.dispatchEvent(new Event('stc:echo-ready'));
}
