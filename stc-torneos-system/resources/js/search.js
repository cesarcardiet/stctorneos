import $ from 'jquery';

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function escapeRegex(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlight(text, term) {
    const safe = escapeHtml(text);
    const parts = String(term || '')
        .trim()
        .split(/\s+/)
        .filter((part) => part.length > 1);

    if (!parts.length) {
        return safe;
    }

    const pattern = new RegExp(`(${parts.map(escapeRegex).join('|')})`, 'ig');

    return safe.replace(pattern, '<mark>$1</mark>');
}

function bindSearch($form) {
    const $input = $form.find('input[name="search"]');
    const $list = $form.find('.stc-suggest');
    const suggestUrl = $form.data('suggest-url');
    const scope = $form.data('scope') || '';
    let timer = null;
    let request = null;
    let items = [];
    let active = -1;

    if (!$input.length || !suggestUrl) {
        return;
    }

    function close() {
        $list.attr('hidden', true).empty();
        active = -1;
        items = [];
    }

    function setActive(index) {
        active = index;
        $list.find('.stc-suggest-item').removeClass('is-active');
        if (index >= 0) {
            $list.find('.stc-suggest-item').eq(index).addClass('is-active');
        }
    }

    function render(term, suggestions) {
        items = suggestions || [];
        active = items.length ? 0 : -1;

        if (!items.length) {
            $list.html('<div class="stc-suggest-empty">No hay coincidencias todavía</div>').removeAttr('hidden');
            return;
        }

        const html = items
            .map(
                (item, index) => `
            <button type="button" class="stc-suggest-item${index === 0 ? ' is-active' : ''}" data-index="${index}" role="option">
                <span>
                    <strong>${highlight(item.title, term)}</strong>
                    <small>${escapeHtml(item.meta)}</small>
                </span>
                <em>${escapeHtml(item.group)}</em>
            </button>`
            )
            .join('');

        $list.html(`${html}<button type="submit" class="stc-suggest-all">Ver todos los resultados</button>`).removeAttr('hidden');
    }

    function fetchSuggestions(term) {
        if (request) {
            request.abort();
        }

        request = $.ajax({
            url: suggestUrl,
            method: 'GET',
            dataType: 'json',
            data: { q: term, scope },
        })
            .done((payload) => {
                if ($input.val().trim() !== term) {
                    return;
                }
                render(term, payload.suggestions || []);
            })
            .fail((_, status) => {
                if (status !== 'abort') {
                    close();
                }
            });
    }

    $input.on('input', function () {
        const term = String($(this).val() || '').trim();
        window.clearTimeout(timer);

        if (term.length < 2) {
            close();
            return;
        }

        timer = window.setTimeout(() => fetchSuggestions(term), 180);
    });

    $input.on('keydown', function (event) {
        if ($list.is('[hidden]')) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(Math.min(active + 1, items.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(Math.max(active - 1, 0));
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'Enter' && active >= 0 && items[active]) {
            event.preventDefault();
            window.location.href = items[active].href;
        }
    });

    $list.on('mousedown', '.stc-suggest-item', function (event) {
        event.preventDefault();
        const item = items[Number($(this).data('index'))];
        if (item?.href) {
            window.location.href = item.href;
        }
    });

    $input.on('blur', function () {
        window.setTimeout(close, 120);
    });
}

$(function () {
    $('.stc-search[data-suggest-url]').each(function () {
        bindSearch($(this));
    });

    bindLiveFilters();
});

function bindLiveFilters() {
    $('[data-live-filter]').each(function () {
        const $root = $(this);

        const apply = () => {
            const query = String($root.find('[data-live-search]').val() || '')
                .trim()
                .toLowerCase();
            const letter = String(
                $root.find('[data-ws-cat-az] .is-on, [data-live-az] .is-on').first().attr('data-letter') || ''
            );
            let visible = 0;

            $root.find('[data-live-item], [data-ws-cat-item]').each(function () {
                const $item = $(this);
                const haystack = String($item.attr('data-search') || $item.text() || '').toLowerCase();
                const itemLetter = String($item.attr('data-letter') || '');
                const matches = (query === '' || haystack.includes(query)) && (letter === '' || itemLetter === letter);
                $item.toggleClass('is-live-hidden', !matches);
                if (matches) {
                    visible += 1;
                }
            });

            $root.toggleClass('is-filtering', query !== '' || letter !== '');
            $root.find('[data-live-empty]').prop('hidden', visible !== 0 || (query === '' && letter === ''));

            const $count = $root.find('[data-live-count]');
            if ($count.length) {
                const total = $root.find('[data-live-item], [data-ws-cat-item]').length;
                const noun = total === 1 ? 'jugador' : 'jugadores';
                $count.text(
                    query === '' && letter === ''
                        ? `${total} ${noun}`
                        : `${visible} de ${total} ${noun}`
                );
            }
        };

        $root.on('input', '[data-live-search]', apply);
        $root.on('click', '[data-ws-cat-az] [data-letter], [data-live-az] [data-letter]', function (event) {
            event.preventDefault();
            const $button = $(this);
            $button.closest('[data-ws-cat-az], [data-live-az]').find('[data-letter]').removeClass('is-on');
            $button.addClass('is-on');
            apply();
        });
    });
}
