/**
 * Campanita de notificaciones (estilo Facebook) + websocket Echo/Reverb.
 */
(function initNotificationBell() {
    const root = document.querySelector('[data-notif-bell]');
    if (!root) return;

    const toggle = root.querySelector('[data-notif-toggle]');
    const panel = root.querySelector('[data-notif-panel]');
    const list = root.querySelector('[data-notif-list]');
    const badge = root.querySelector('[data-notif-badge]');
    const markAll = root.querySelector('[data-notif-mark-all]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const feedUrl = '/operacion/inbox';
    const userId = root.dataset.userId;

    let open = false;
    let items = [];

    const setBadge = (count) => {
        const n = Number(count) || 0;
        if (!badge) return;
        badge.hidden = n <= 0;
        badge.textContent = n > 99 ? '99+' : String(n);
    };

    const render = () => {
        if (!list) return;
        if (!items.length) {
            list.innerHTML = '<p class="stc-notif-bell__empty">No tenés notificaciones nuevas.</p>';
            return;
        }

        list.innerHTML = items.map((item) => `
            <a class="stc-notif-bell__item${item.read ? '' : ' is-unread'}" href="${item.url || '#'}" data-notif-id="${item.id}">
                <strong>${escapeHtml(item.title || 'Aviso')}</strong>
                <span>${escapeHtml(item.body || '')}</span>
                <small>${escapeHtml(item.sent_label || '')}</small>
            </a>
        `).join('');
    };

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const load = async () => {
        try {
            const response = await fetch(feedUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const json = await response.json();
            const data = json.data || {};
            items = Array.isArray(data.items) ? data.items : [];
            setBadge(data.unread || 0);
            if (open) render();
        } catch (_) {
            // silencioso
        }
    };

    const prependItem = (payload) => {
        items = [
            {
                id: payload.id,
                title: payload.title,
                body: payload.body,
                sent_at: payload.sent_at,
                sent_label: 'Ahora',
                read: false,
                url: payload.url || '#',
            },
            ...items.filter((row) => row.id !== payload.id),
        ].slice(0, 25);
        const current = Number(badge?.dataset.count || badge?.textContent || 0) || items.filter((i) => !i.read).length;
        setBadge(Math.max(current, items.filter((i) => !i.read).length));
        if (badge) badge.dataset.count = String(items.filter((i) => !i.read).length);
        if (open) render();
        root.classList.add('has-pulse');
        window.setTimeout(() => root.classList.remove('has-pulse'), 1200);
    };

    toggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        open = !open;
        if (panel) panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            render();
            load();
        }
    });

    document.addEventListener('click', (event) => {
        if (!open) return;
        if (root.contains(event.target)) return;
        open = false;
        if (panel) panel.hidden = true;
        toggle?.setAttribute('aria-expanded', 'false');
    });

    list?.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-notif-id]');
        if (!link) return;
        const id = link.getAttribute('data-notif-id');
        try {
            await fetch(`${feedUrl}/${id}/read`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
        } catch (_) {}
    });

    markAll?.addEventListener('click', async (event) => {
        event.preventDefault();
        try {
            await fetch(`${feedUrl}/read-all`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            items = items.map((item) => ({ ...item, read: true }));
            setBadge(0);
            render();
        } catch (_) {}
    });

    const connectEcho = () => {
        const cfg = window.StcRealtime;
        if (!cfg?.enabled || !cfg.key || !userId || !window.Echo) return;

        window.Echo.private(`App.Models.User.${userId}`)
            .listen('.notification.pushed', (payload) => {
                prependItem(payload || {});
            });
    };

    load();
    // Fallback suave si el websocket no conecta (cada 45s, no cada 5s)
    window.setInterval(() => {
        if (!document.hidden) load();
    }, 45000);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', connectEcho);
    } else {
        connectEcho();
    }
    window.addEventListener('stc:echo-ready', connectEcho);
})();
