import $ from 'jquery';

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then((regs) => {
        regs.forEach((reg) => reg.unregister());
    }).catch(() => {});
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
});

function wrapWideTables() {
    document.querySelectorAll('.stc-main table').forEach((table) => {
        if (table.closest('.stc-table-scroll, .standings-scroll, .ws-table-swipe')) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'stc-table-scroll';
        table.parentNode.insertBefore(wrap, table);
        wrap.appendChild(table);
    });
}

$(function () {
    const $body = $('.stc-body');
    wrapWideTables();

    if (!$body.length) {
        return;
    }

    function closeNav() {
        $body.removeClass('nav-open');
        $('.stc-menu-btn').attr('aria-expanded', 'false');
    }

    function openNav() {
        $body.addClass('nav-open');
        $('.stc-menu-btn').attr('aria-expanded', 'true');
    }

    $('[data-open-menu]').on('click', function (event) {
        event.preventDefault();
        openNav();
    });

    $('[data-close-menu]').on('click', function (event) {
        event.preventDefault();
        closeNav();
    });

    $('.stc-nav a').on('click', closeNav);

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNav();
        }
    });

    const sidebar = document.getElementById('stc-sidebar');
    if (sidebar) {
        sidebar.addEventListener('wheel', (event) => {
            if (!window.matchMedia('(min-width: 981px)').matches || $body.hasClass('nav-open')) {
                return;
            }

            event.preventDefault();
            window.scrollBy(0, event.deltaY);
        }, { passive: false });
    }
});
