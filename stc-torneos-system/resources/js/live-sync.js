/**
 * Auto-refresh (polling) for live match scores and notification badges.
 * Updates DOM without full page reload.
 */
(function initLiveSync() {
    const INTERVAL_MS = 5000;
    const root = document.documentElement;
    const apiBase = root.dataset.apiBase || '/api/v1';
    let revision = 0;
    let timer = null;
    let busy = false;

    const liveNodes = () => Array.from(document.querySelectorAll('[data-live-match-id]'));
    const scoreNodes = () => Array.from(document.querySelectorAll('[data-live-score]'));
    const listRoots = () => Array.from(document.querySelectorAll('[data-live-list]'));
    const badgeNodes = () => Array.from(document.querySelectorAll('[data-notif-badge]'));

    const shouldRun = () =>
        liveNodes().length > 0
        || scoreNodes().length > 0
        || listRoots().length > 0
        || badgeNodes().length > 0
        || document.querySelector('[data-live-sync="1"]');

    const applyMatch = (match) => {
        const id = String(match.id);
        const scoreText = `${match.home_score ?? 0} - ${match.away_score ?? 0}`;
        const colonScore = `${match.home_score ?? 0} : ${match.away_score ?? 0}`;

        document.querySelectorAll(`[data-live-score="${id}"]`).forEach((el) => {
            const format = el.dataset.scoreFormat || 'dash';
            el.textContent = format === 'colon' ? colonScore : scoreText;
        });

        document.querySelectorAll(`[data-live-minute="${id}"]`).forEach((el) => {
            if (match.minute != null && match.minute !== '') {
                el.textContent = String(match.minute);
            }
        });

        document.querySelectorAll(`[data-live-status="${id}"]`).forEach((el) => {
            el.textContent = match.status_label || match.status || '';
            el.className = el.className.replace(/\bis-\w+/g, '');
            if (match.status) {
                el.classList.add('is-' + String(match.status).replace(/_/g, '-'));
            }
        });

        document.querySelectorAll(`[data-live-match-id="${id}"]`).forEach((el) => {
            el.classList.toggle('is-live', match.status === 'live');
        });
    };

    const renderLiveList = (matches) => {
        listRoots().forEach((list) => {
            const liveOnly = matches.filter((m) => m.status === 'live');
            const emptyHtml = list.dataset.emptyHtml || '<p class="schedule-empty">No hay partidos en vivo ahora.</p>';
            const canLink = list.dataset.canFixture === '1';
            const baseUrl = list.dataset.fixtureBase || '/admin/fixture';

            if (liveOnly.length === 0) {
                list.innerHTML = emptyHtml;
                return;
            }

            list.innerHTML = liveOnly.map((match) => {
                const home = match.home?.name || 'Local';
                const away = match.away?.name || 'Visitante';
                const score = `${match.home_score ?? 0} - ${match.away_score ?? 0}`;
                const minute = match.minute != null ? String(match.minute) : '';
                const inner = `
                    <span>${home} vs ${away}</span>
                    <strong data-live-score="${match.id}">${score}</strong>
                    <small data-live-minute="${match.id}">${minute}</small>
                `;
                if (canLink) {
                    return `<a class="match-row is-live" data-live-match-id="${match.id}" href="${baseUrl}/${match.id}">${inner}</a>`;
                }
                return `<div class="match-row is-live" data-live-match-id="${match.id}">${inner}</div>`;
            }).join('');
        });
    };

    const updateBadges = (unread) => {
        badgeNodes().forEach((el) => {
            const count = Number(unread) || 0;
            el.hidden = count <= 0;
            el.textContent = count > 99 ? '99+' : String(count);
            el.setAttribute('data-count', String(count));
        });
    };

    const pollMatches = async () => {
        const params = new URLSearchParams();
        if (revision) {
            params.set('since', String(revision));
        }
        const categoryId = document.querySelector('[data-live-category-id]')?.dataset.liveCategoryId;
        const tournamentId = document.querySelector('[data-live-tournament-id]')?.dataset.liveTournamentId;
        if (categoryId) params.set('category_id', categoryId);
        if (tournamentId) params.set('tournament_id', tournamentId);

        const url = `${apiBase}/live/sync${params.toString() ? `?${params}` : ''}`;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const json = await response.json();
        const data = json.data || json;
        if (!data || data.changed === false) {
            if (data?.revision) revision = data.revision;
            return;
        }
        revision = data.revision || revision;
        const matches = Array.isArray(data.matches) ? data.matches : [];
        matches.forEach(applyMatch);
        if (listRoots().length) {
            renderLiveList(matches);
        }
    };

    const pollNotifications = async () => {
        if (!badgeNodes().length && !document.querySelector('[data-notif-sync="1"]')) {
            return;
        }
        const response = await fetch(`${apiBase}/notifications/sync`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (response.status === 401) return;
        if (!response.ok) return;
        const json = await response.json();
        const data = json.data || json;
        if (data && typeof data.unread !== 'undefined') {
            updateBadges(data.unread);
        }
    };

    const tick = async () => {
        if (busy || document.hidden) return;
        busy = true;
        try {
            await Promise.allSettled([pollMatches(), pollNotifications()]);
        } finally {
            busy = false;
        }
    };

    const start = () => {
        if (timer || !shouldRun()) return;
        tick();
        timer = window.setInterval(tick, INTERVAL_MS);
    };

    const stop = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
