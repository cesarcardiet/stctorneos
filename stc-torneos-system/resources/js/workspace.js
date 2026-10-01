let cropReturnModalId = null;

function closeWorkspaceModals() {
    document.querySelectorAll('[data-ws-modal]').forEach((modal) => {
        modal.hidden = true;
    });

    const frame = document.querySelector('[data-ws-doc-preview-frame]');
    if (frame) {
        frame.removeAttribute('src');
        frame.hidden = true;
    }
    const image = document.querySelector('[data-ws-doc-preview-image]');
    if (image) {
        image.removeAttribute('src');
        image.hidden = true;
    }
}

function openWorkspaceModal(id) {
    const modal = document.querySelector(`[data-ws-modal="${id}"]`);
    if (!modal) {
        return;
    }

    const current = [...document.querySelectorAll('[data-ws-modal]')].find((item) => !item.hidden);
    if (id === 'shield-crop' && current && current.getAttribute('data-ws-modal') !== 'shield-crop') {
        cropReturnModalId = current.getAttribute('data-ws-modal');
    } else if (id !== 'shield-crop') {
        cropReturnModalId = null;
    }

    closeWorkspaceModals();
    modal.hidden = false;
}

function restoreCropParent() {
    if (!cropReturnModalId) {
        return;
    }

    const back = document.querySelector(`[data-ws-modal="${cropReturnModalId}"]`);
    cropReturnModalId = null;
    if (back) {
        back.hidden = false;
    }
}

document.addEventListener('click', (event) => {
    const preview = event.target.closest('[data-ws-doc-preview]');
    if (preview) {
        event.preventDefault();
        const src = preview.getAttribute('data-preview-src') || preview.getAttribute('href');
        const title = preview.getAttribute('data-preview-title') || 'Vista previa';
        const kind = preview.getAttribute('data-preview-kind')
            || (/\.(png|jpe?g|webp|gif)(\?|$)/i.test(src || '') ? 'image' : 'file');
        const modal = document.querySelector('[data-ws-modal="doc-preview"]');
        const image = modal?.querySelector('[data-ws-doc-preview-image]');
        const frame = modal?.querySelector('[data-ws-doc-preview-frame]');
        const empty = modal?.querySelector('[data-ws-doc-preview-empty]');
        const openLink = modal?.querySelector('[data-ws-doc-preview-open]');
        const titleEl = modal?.querySelector('[data-ws-doc-preview-title]');

        if (!modal || !src) {
            return;
        }

        if (titleEl) {
            titleEl.textContent = title;
        }

        if (image) {
            image.hidden = true;
            image.removeAttribute('src');
        }
        if (frame) {
            frame.hidden = true;
            frame.removeAttribute('src');
        }
        if (empty) {
            empty.hidden = true;
        }
        if (openLink) {
            openLink.href = src;
            openLink.hidden = false;
        }

        openWorkspaceModal('doc-preview');

        if (kind === 'image' && image) {
            image.alt = title;
            image.hidden = false;
            image.src = src;
            image.onerror = () => {
                image.hidden = true;
                if (empty) {
                    empty.hidden = false;
                }
            };
        } else if (frame) {
            frame.hidden = false;
            frame.src = src;
        } else if (empty) {
            empty.hidden = false;
        }

        return;
    }

    const opener = event.target.closest('[data-ws-open]');
    if (opener) {
        event.preventDefault();
        openWorkspaceModal(opener.getAttribute('data-ws-open'));
        return;
    }

    if (event.target.closest('[data-ws-close]')) {
        const cropOpen = document.querySelector('[data-ws-modal="shield-crop"]')?.hidden === false;
        closeWorkspaceModals();
        if (cropOpen) {
            restoreCropParent();
        }
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const cropOpen = document.querySelector('[data-ws-modal="shield-crop"]')?.hidden === false;
        closeWorkspaceModals();
        if (cropOpen) {
            restoreCropParent();
        }
    }
});

document.querySelectorAll('[data-ws-sort]').forEach((list) => {
    enableListSort(list, 'li');
});

document.querySelectorAll('[data-ws-move-teams]').forEach((stack) => {
    let dragging = null;
    const url = stack.getAttribute('data-ws-teams-url');

    stack.querySelectorAll('[data-team-row]').forEach((row) => {
        row.addEventListener('mousedown', (event) => {
            row.draggable = !!event.target.closest('[data-ws-drag-team]');
        });
        row.addEventListener('dragstart', (event) => {
            dragging = row;
            row.classList.add('is-dragging');
            event.dataTransfer?.setData('text/plain', row.dataset.teamId || '');
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            row.draggable = false;
            stack.querySelectorAll('.is-drop').forEach((card) => card.classList.remove('is-drop'));
            dragging = null;
        });
    });

    stack.querySelectorAll('.standings-card').forEach((card) => {
        card.addEventListener('dragover', (event) => {
            if (!dragging) {
                return;
            }
            event.preventDefault();
            card.classList.add('is-drop');
        });
        card.addEventListener('dragleave', (event) => {
            if (!card.contains(event.relatedTarget)) {
                card.classList.remove('is-drop');
            }
        });
        card.addEventListener('drop', (event) => {
            event.preventDefault();
            card.classList.remove('is-drop');
            if (!dragging || !url) {
                return;
            }

            const from = dragging.closest('.standings-card');
            if (from === card) {
                return;
            }

            const table = card.querySelector('.standings-table');
            table?.appendChild(dragging);
            stack.querySelectorAll('.standings-card').forEach(renumberGroup);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    team_id: Number(dragging.dataset.teamId),
                    group_name: card.getAttribute('data-group-key') || '',
                }),
            });
        });
    });
});

function renumberGroup(card) {
    card.querySelectorAll('[data-team-row]').forEach((row, index) => {
        const pos = row.querySelector('span');
        if (pos) {
            pos.textContent = String(index + 1);
        }
    });
}

function enableListSort(list, itemSelector, onSorted) {
    let dragging = null;
    const items = () => [...list.querySelectorAll(itemSelector)];

    items().forEach((item) => {
        const handle = item.querySelector('[data-ws-drag]');
        if (handle) {
            item.addEventListener('mousedown', (event) => {
                item.draggable = !!event.target.closest('[data-ws-drag]');
            });
        } else {
            item.setAttribute('draggable', 'true');
        }

        item.addEventListener('dragstart', (event) => {
            if (list.closest('[data-ws-cat-board]')?.classList.contains('is-filtering')) {
                event.preventDefault();
                return;
            }
            dragging = item;
            item.classList.add('is-dragging');
        });
        item.addEventListener('dragend', () => {
            item.classList.remove('is-dragging');
            if (handle) {
                item.draggable = false;
            }
            dragging = null;
            if (typeof onSorted === 'function') {
                onSorted();
            }
        });
        item.addEventListener('dragover', (event) => {
            event.preventDefault();
            const over = event.currentTarget;
            if (!dragging || dragging === over) {
                return;
            }
            const rect = over.getBoundingClientRect();
            const before = event.clientY < rect.top + rect.height / 2;
            over.parentNode.insertBefore(dragging, before ? over : over.nextSibling);
        });
    });
}

bindCategoryBoards();
bindCategoryCardMenus();
bindSheetPlayerFilters();
bindSheetActorFields();
bindPlayerCardDownload();

function loadHtml2Canvas() {
    if (window.html2canvas) {
        return Promise.resolve(window.html2canvas);
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        script.onload = () => resolve(window.html2canvas);
        script.onerror = () => reject(new Error('html2canvas'));
        document.head.appendChild(script);
    });
}

async function drawablePhoto(photo, width, height) {
    if (!photo) {
        return null;
    }

    const src = photo.currentSrc || photo.src;
    if (!src) {
        return null;
    }

    if (photo.complete && photo.naturalWidth && photo.naturalHeight) {
        return photo;
    }

    const load = (url) => new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('photo'));
        image.src = url;
    });

    try {
        const image = await load(src);
        if (image.naturalWidth && image.naturalHeight) {
            return image;
        }
    } catch (error) {
        return null;
    }

    if (!src.includes('.svg')) {
        return null;
    }

    const response = await fetch(src);
    const text = await response.text();
    const sized = /<svg[^>]*\swidth=/.test(text)
        ? text
        : text.replace('<svg', `<svg width="${Math.max(1, Math.round(width))}" height="${Math.max(1, Math.round(height))}"`);
    const url = URL.createObjectURL(new Blob([sized], { type: 'image/svg+xml;charset=utf-8' }));
    const image = await load(url);
    image.dataset.objectUrl = url;

    return image;
}

function drawCoverImage(ctx, image, x, y, width, height) {
    const sourceWidth = image.naturalWidth || image.width;
    const sourceHeight = image.naturalHeight || image.height;
    if (!sourceWidth || !sourceHeight || !width || !height) {
        return;
    }

    const scale = Math.max(width / sourceWidth, height / sourceHeight);
    const drawnWidth = sourceWidth * scale;
    const drawnHeight = sourceHeight * scale;
    const offsetX = x + (width - drawnWidth) / 2;
    const offsetY = y + (height - drawnHeight) * 0.18;

    ctx.save();
    ctx.beginPath();
    ctx.rect(x, y, width, height);
    ctx.clip();
    ctx.drawImage(image, offsetX, offsetY, drawnWidth, drawnHeight);
    ctx.restore();
}

function bindPlayerCardDownload() {
    document.querySelectorAll('[data-pcard-download]').forEach((button) => {
        if (button.dataset.wsBound === '1') {
            return;
        }
        button.dataset.wsBound = '1';
        button.addEventListener('click', async () => {
            const card = button.closest('[data-pcard]');
            const poster = card?.querySelector('.stc-pcard-poster');
            const photo = poster?.querySelector('.stc-pcard-photo');
            if (!card || !poster) {
                return;
            }

            const original = button.textContent;
            button.disabled = true;
            button.textContent = 'Generando…';

            try {
                const html2canvas = await loadHtml2Canvas();
                const snap = await html2canvas(card, {
                    backgroundColor: null,
                    scale: 2,
                    useCORS: true,
                    ignoreElements: (el) => el.hasAttribute('data-pcard-download')
                        || el.classList.contains('stc-pcard-download')
                        || el.classList.contains('stc-pcard-photo-edit')
                        || el.classList.contains('stc-pcard-photo'),
                    onclone: (doc) => {
                        doc.querySelectorAll('.stc-pcard, .stc-pcard-poster').forEach((el) => {
                            el.style.background = 'transparent';
                        });
                        doc.querySelectorAll('.stc-pcard-number, .stc-pcard-first, .stc-pcard-last').forEach((el) => {
                            el.style.display = 'block';
                            el.style.lineHeight = '1.15';
                            el.style.letterSpacing = '0';
                        });
                        doc.querySelectorAll('.stc-pcard-first').forEach((el) => {
                            el.style.marginBottom = '6px';
                        });
                        doc.querySelectorAll('.stc-pcard-last').forEach((el) => {
                            el.style.marginTop = '0';
                            el.style.marginBottom = '18px';
                        });
                    },
                });

                const canvas = document.createElement('canvas');
                canvas.width = snap.width;
                canvas.height = snap.height;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#05070d';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                const cardRect = card.getBoundingClientRect();
                const posterRect = poster.getBoundingClientRect();
                const scaleX = snap.width / cardRect.width;
                const scaleY = snap.height / cardRect.height;
                const photoX = (posterRect.left - cardRect.left) * scaleX;
                const photoY = (posterRect.top - cardRect.top) * scaleY;
                const photoW = posterRect.width * scaleX;
                const photoH = posterRect.height * scaleY;
                const drawable = await drawablePhoto(photo, photoW, photoH);
                if (drawable) {
                    drawCoverImage(ctx, drawable, photoX, photoY, photoW, photoH);
                    if (drawable.dataset?.objectUrl) {
                        URL.revokeObjectURL(drawable.dataset.objectUrl);
                    }
                }

                ctx.drawImage(snap, 0, 0);

                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = (card.dataset.pcardName || 'player-card') + '.png';
                link.click();
            } catch (error) {
                showWorkspaceFlash('No se pudo descargar la imagen. Probá de nuevo.', true);
            } finally {
                button.disabled = false;
                button.textContent = original;
            }
        });
    });
}

function bindCategoryBoards() {
    document.querySelectorAll('[data-ws-cat-board]').forEach((board) => {
        const search = board.querySelector('[data-ws-cat-search]');
        const az = board.querySelector('[data-ws-cat-az]');
        const branchFilter = board.querySelector('[data-ws-cat-branch]');
        const countLabel = board.querySelector('[data-ws-cat-count]');
        const sortUrl = board.getAttribute('data-ws-sort-url');
        const list = board.querySelector('[data-ws-sort-cats]');
        const items = () => [...board.querySelectorAll('[data-ws-cat-item]')];

        const applyFilter = () => {
            const query = String(search?.value || '').trim().toLowerCase();
            const letter = az?.querySelector('.is-on')?.getAttribute('data-letter') || '';
            const branch = branchFilter?.querySelector('.is-on')?.getAttribute('data-branch') || '';
            let visible = 0;

            items().forEach((item) => {
                const haystack = String(item.getAttribute('data-search') || '').toLowerCase();
                const itemLetter = item.getAttribute('data-letter') || '';
                const itemBranch = item.getAttribute('data-branch') || '';
                const matchesQuery = query === '' || haystack.includes(query);
                const matchesLetter = letter === '' || itemLetter === letter;
                const matchesBranch = branch === '' || itemBranch === branch;
                const show = matchesQuery && matchesLetter && matchesBranch;
                item.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (countLabel) {
                countLabel.textContent = visible === items().length
                    ? `${visible} categorías`
                    : `${visible} de ${items().length} categorías`;
            }

            board.classList.toggle('is-filtering', query !== '' || letter !== '' || branch !== '');
        };

        search?.addEventListener('input', applyFilter);
        branchFilter?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-branch]');
            if (!button) {
                return;
            }
            event.preventDefault();
            branchFilter.querySelectorAll('[data-branch]').forEach((candidate) => {
                candidate.classList.toggle('is-on', candidate === button);
            });
            applyFilter();
        });
        az?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-letter]');
            if (!button) {
                return;
            }
            event.preventDefault();
            az.querySelectorAll('[data-letter]').forEach((candidate) => {
                candidate.classList.toggle('is-on', candidate === button);
            });
            applyFilter();
        });

        const saveOrder = () => {
            if (!sortUrl || !list || board.classList.contains('is-filtering')) {
                return;
            }

            const ids = [...list.querySelectorAll('[data-ws-cat-item]')].map((item) => Number(item.dataset.id));
            fetch(sortUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids }),
            });
        };

        if (list && sortUrl) {
            enableListSort(list, '[data-ws-cat-item]', saveOrder);
        }

        board.querySelector('[data-ws-cat-az-sort]')?.addEventListener('click', () => {
            const target = list || board.querySelector('.ws-cat-grid, .ws-dir-stack');
            if (!target) {
                return;
            }

            const nodes = [...target.querySelectorAll('[data-ws-cat-item]')];
            nodes.sort((left, right) => String(left.dataset.name || '').localeCompare(String(right.dataset.name || ''), 'es', {
                numeric: true,
                sensitivity: 'base',
            }));
            nodes.forEach((node) => target.appendChild(node));
            board.classList.remove('is-filtering');
            saveOrder();
        });
    });
}

function bindCategoryCardMenus() {
    const form = document.querySelector('[data-ws-edit-cat-form]');

    document.addEventListener('click', (event) => {
        const menu = event.target.closest('.ws-cat-menu');
        document.querySelectorAll('.ws-cat-menu[open]').forEach((openMenu) => {
            if (openMenu !== menu) {
                openMenu.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('[data-ws-edit-cat]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (!form) {
                return;
            }

            form.action = button.getAttribute('data-action') || '#';
            const fields = {
                name: button.dataset.name,
                birth_year: button.dataset.birthYear,
                branch: button.dataset.branch,
                modality: button.dataset.modality,
                competition_format: button.dataset.format,
                groups_count: button.dataset.groups,
                status: button.dataset.status,
                points_win: button.dataset.pointsWin,
                points_draw: button.dataset.pointsDraw,
                points_loss: button.dataset.pointsLoss,
                description: button.dataset.description,
            };
            Object.entries(fields).forEach(([name, value]) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field) {
                    field.value = value ?? '';
                }
            });

            const preview = form.querySelector('[data-ws-shield-preview]');
            const pathInput = form.querySelector('[data-ws-banner-path]');
            const cropData = form.querySelector('[data-ws-shield-data]');
            const fileInput = form.querySelector('input[type="file"]');
            if (preview && button.dataset.imageUrl) {
                preview.src = button.dataset.imageUrl;
            }
            if (pathInput) {
                pathInput.value = button.dataset.imagePath || '';
            }
            if (cropData) {
                cropData.value = '';
            }
            if (fileInput) {
                fileInput.value = '';
            }

            const title = document.querySelector('[data-ws-modal="edit-category"] h2');
            if (title) {
                title.textContent = 'Editar ' + (button.dataset.name || 'categoría');
            }

            button.closest('.ws-cat-menu')?.removeAttribute('open');
            openWorkspaceModal('edit-category');
        });
    });

    document.querySelectorAll('[data-ws-banner-pick]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.ws-cat-banner-field');
            const preview = field?.querySelector('[data-ws-shield-preview]');
            const pathInput = field?.querySelector('[data-ws-banner-path]');
            const cropData = field?.querySelector('[data-ws-shield-data]');
            if (preview && button.dataset.url) {
                preview.src = button.dataset.url;
            }
            if (pathInput) {
                pathInput.value = button.dataset.path || '';
            }
            if (cropData) {
                cropData.value = '';
            }
        });
    });
}

function bindSheetPlayerFilters() {
    document.querySelectorAll('[data-ws-team-filter]').forEach((teamSelect) => {
        const form = teamSelect.closest('form');
        const playerSelect = form?.querySelector('[data-ws-player-filter]');
        if (!playerSelect || playerSelect.dataset.wsBound === '1') {
            return;
        }

        playerSelect.dataset.wsBound = '1';
        const players = [...playerSelect.querySelectorAll('option[data-team-id]')].map((option) => option.cloneNode(true));
        const blank = playerSelect.querySelector('option[value=""]')?.cloneNode(true) ?? new Option('Elegí jugador', '');

        const apply = () => {
            const teamId = String(teamSelect.value);
            playerSelect.replaceChildren(blank.cloneNode(true));
            players
                .filter((option) => option.getAttribute('data-team-id') === teamId)
                .forEach((option) => playerSelect.appendChild(option.cloneNode(true)));
        };

        teamSelect.addEventListener('change', apply);
        apply();
    });
}

function bindSheetActorFields() {
    document.querySelectorAll('[data-ws-event-type]').forEach((typeSelect) => {
        const form = typeSelect.closest('form');
        const actorSelect = form?.querySelector('[data-ws-actor-kind]');
        const playerSelect = form?.querySelector('[data-ws-actor-field="player"]');
        const staffSelect = form?.querySelector('[data-ws-actor-field="staff"]');
        if (!form || typeSelect.dataset.wsActorBound === '1') {
            return;
        }

        typeSelect.dataset.wsActorBound = '1';

        const apply = () => {
            const type = typeSelect.value;
            const playerOnly = ['goal', 'assist', 'substitution'].includes(type);
            if (playerOnly && actorSelect) {
                actorSelect.value = 'player';
            }

            const isStaff = actorSelect?.value === 'staff' && ['yellow', 'red'].includes(type);
            if (playerSelect) {
                playerSelect.hidden = isStaff;
                playerSelect.required = !isStaff;
                if (isStaff) {
                    playerSelect.value = '';
                }
            }
            if (staffSelect) {
                staffSelect.hidden = !isStaff;
                staffSelect.required = isStaff;
                if (!isStaff) {
                    staffSelect.value = '';
                }
            }
        };

        typeSelect.addEventListener('change', apply);
        actorSelect?.addEventListener('change', apply);
        apply();
    });
}

bindMatchAutosave();

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function showWorkspaceFlash(message, isError) {
    const main = document.querySelector('.ws-main');
    if (!main) {
        return;
    }

    let flash = main.querySelector('.ws-flash');
    if (!flash) {
        flash = document.createElement('p');
        flash.className = 'ws-flash';
        const header = main.querySelector('.stc-topbar');
        if (header?.nextSibling) {
            main.insertBefore(flash, header.nextSibling);
        } else {
            main.prepend(flash);
        }
    }

    flash.textContent = message;
    flash.classList.toggle('is-error', Boolean(isError));
}

function applyMatchSaved(data) {
    document.querySelectorAll('[data-ws-status-banner]').forEach((banner) => {
        banner.textContent = data.status || '';
        banner.className = 'match-status is-' + (data.tone || 'soon');
    });
    document.querySelectorAll('[data-ws-status-text]').forEach((el) => {
        el.textContent = data.status || '';
    });
    if (data.field) {
        document.querySelectorAll('[data-ws-field-text]').forEach((el) => {
            el.textContent = data.field;
        });
    }
    if (data.when) {
        document.querySelectorAll('[data-ws-when-text]').forEach((el) => {
            el.textContent = data.when;
        });
    }
    if (data.home_score !== undefined && data.away_score !== undefined) {
        document.querySelectorAll('[data-ws-score-text]').forEach((el) => {
            el.textContent = `${data.home_score} : ${data.away_score}`;
        });
    }

    document.querySelectorAll('.ws-card').forEach((card) => {
        card.classList.toggle('is-live-match', Boolean(data.live));
    });
}

function bindMatchAutosave() {
    document.querySelectorAll('[data-ws-autosave]').forEach((form) => {
        const save = async () => {
            const body = new FormData(form);
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors)[0] : null;
                    showWorkspaceFlash((Array.isArray(firstError) ? firstError[0] : data.message) || 'No se pudo guardar.', true);
                    return;
                }
                applyMatchSaved(data);
                showWorkspaceFlash(data.message || 'Guardado.');
            } catch (error) {
                showWorkspaceFlash('No se pudo guardar. Probá de nuevo.', true);
            }
        };

        form.querySelectorAll('select, input').forEach((field) => {
            field.addEventListener('change', () => {
                if (field.name === 'status') {
                    const tones = {
                        scheduled: 'soon',
                        live: 'live',
                        finished: 'done',
                        suspended: 'warn',
                        rescheduled: 'soon',
                    };
                    applyMatchSaved({
                        status: field.selectedOptions[0]?.textContent?.trim() || '',
                        tone: tones[field.value] || 'soon',
                        live: field.value === 'live',
                    });
                }
                save();
            });
        });
    });
}

document.querySelectorAll('[data-ws-fixture-scope]').forEach((select) => {
    const updateHints = () => {
        document.querySelectorAll('[data-ws-scope-hint]').forEach((hint) => {
            hint.hidden = hint.getAttribute('data-ws-scope-hint') !== select.value;
        });
    };
    select.addEventListener('change', updateHints);
    updateHints();
});

document.querySelectorAll('[data-ws-mix-box]').forEach((box) => {
    const selects = [...box.querySelectorAll('[data-ws-mix-from]')];
    const byFrom = Object.fromEntries(selects.map((select) => [select.getAttribute('data-ws-mix-from'), select]));

    const syncDisabled = () => {
        const taken = {};
        selects.forEach((select) => {
            if (select.value) {
                taken[select.value] = select.getAttribute('data-ws-mix-from');
            }
        });
        selects.forEach((select) => {
            const from = select.getAttribute('data-ws-mix-from');
            [...select.options].forEach((option) => {
                if (!option.value) {
                    return;
                }
                const owner = taken[option.value];
                option.disabled = Boolean(owner && owner !== from);
            });
        });
    };

    selects.forEach((select) => {
        select.addEventListener('change', () => {
            const from = select.getAttribute('data-ws-mix-from');
            const to = select.value;

            selects.forEach((other) => {
                if (other !== select && other.value === from) {
                    other.value = '';
                }
            });

            if (to && byFrom[to]) {
                selects.forEach((other) => {
                    if (other !== select && other !== byFrom[to] && other.value === to) {
                        other.value = '';
                    }
                });
                byFrom[to].value = from;
            }

            syncDisabled();
        });
    });

    syncDisabled();
});

function initShieldCrop() {
    const modal = document.querySelector('[data-ws-modal="shield-crop"]');
    if (!modal) {
        return;
    }

    const stage = modal.querySelector('[data-ws-crop-stage]');
    const image = modal.querySelector('[data-ws-crop-image]');
    const zoom = modal.querySelector('[data-ws-crop-zoom]');
    const apply = modal.querySelector('[data-ws-crop-apply]');
    const errorBox = modal.querySelector('[data-ws-crop-error]');
    const size = () => stage?.clientWidth || 280;

    let input = null;
    let preview = null;
    let dataInput = null;
    let saveUrl = '';
    let cropKind = '';
    let natural = { w: 0, h: 0 };
    let scale = 1;
    let minScale = 1;
    let x = 0;
    let y = 0;
    let drag = null;
    let objectUrl = null;
    let saving = false;

    function showError(message) {
        if (!errorBox) {
            return;
        }
        errorBox.hidden = !message;
        errorBox.textContent = message || '';
    }

    function setTransform() {
        image.style.width = `${natural.w * scale}px`;
        image.style.height = `${natural.h * scale}px`;
        image.style.transform = `translate(${x}px, ${y}px)`;
    }

    function clamp() {
        const box = size();
        const width = natural.w * scale;
        const height = natural.h * scale;
        const keep = Math.min(80, box * 0.35);
        x = Math.min(box - keep, Math.max(keep - width, x));
        y = Math.min(box - keep, Math.max(keep - height, y));
    }

    function fitContain() {
        const box = size();
        minScale = Math.min(box / natural.w, box / natural.h);
        if (!Number.isFinite(minScale) || minScale <= 0) {
            minScale = 1;
        }
        scale = minScale;
        zoom.min = String(minScale);
        zoom.max = String(minScale * 5);
        zoom.value = String(scale);
        x = (box - natural.w * scale) / 2;
        y = (box - natural.h * scale) / 2;
        setTransform();
    }

    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result || ''));
            reader.onerror = () => reject(reader.error);
            reader.readAsDataURL(blob);
        });
    }

    async function loadSource(src) {
        if (!src) {
            return;
        }
        showError('');
        const start = () => {
            natural = { w: image.naturalWidth, h: image.naturalHeight };
            if (!natural.w || !natural.h) {
                showError('No pudimos leer esa imagen. Probá PNG, JPG o WebP.');
                return;
            }
            openWorkspaceModal('shield-crop');
            requestAnimationFrame(fitContain);
        };
        image.onload = start;
        image.onerror = () => showError('No pudimos abrir esa imagen. Probá PNG, JPG o WebP.');
        if (objectUrl && objectUrl !== src) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        if (src.startsWith('blob:') || src.startsWith('data:')) {
            image.src = src;
            return;
        }
        try {
            const response = await fetch(src, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('fetch');
            }
            const blob = await response.blob();
            objectUrl = URL.createObjectURL(blob);
            image.src = objectUrl;
        } catch (error) {
            image.src = src;
        }
    }

    function openFile(file) {
        if (!file) {
            return;
        }
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }
        objectUrl = URL.createObjectURL(file);
        if (preview) {
            preview.src = objectUrl;
        }
        loadSource(objectUrl);
    }

    function bindField(root) {
        const fieldInput = root.querySelector('input[type="file"]');
        const fieldPreview = root.querySelector('[data-ws-shield-preview]');
        if (!fieldInput) {
            return;
        }

        const arm = () => {
            input = fieldInput;
            preview = fieldPreview;
            dataInput = root.querySelector('[data-ws-shield-data]');
            saveUrl = root.getAttribute('data-ws-shield-save') || '';
            cropKind = root.getAttribute('data-ws-crop-kind') || '';
        };
        root.querySelector('[data-ws-shield-pick]')?.addEventListener('click', () => fieldInput.click());
        fieldInput.addEventListener('change', () => {
            arm();
            openFile(fieldInput.files?.[0]);
        });
        root.querySelector('[data-ws-shield-adjust]')?.addEventListener('click', () => {
            arm();
            const file = fieldInput.files?.[0];
            if (file) {
                openFile(file);
                return;
            }
            loadSource(fieldPreview?.getAttribute('src'));
        });
    }

    document.querySelectorAll('[data-ws-shield-crop]').forEach(bindField);

    zoom?.addEventListener('input', () => {
        const box = size();
        const cx = (box / 2 - x) / scale;
        const cy = (box / 2 - y) / scale;
        scale = Math.max(minScale, Number(zoom.value) || minScale);
        x = box / 2 - cx * scale;
        y = box / 2 - cy * scale;
        clamp();
        setTransform();
    });

    const startDrag = (event) => {
        if (event.button != null && event.button !== 0) {
            return;
        }
        event.preventDefault();
        drag = { x: event.clientX - x, y: event.clientY - y };
        stage.classList.add('is-dragging');
        stage.setPointerCapture?.(event.pointerId);
    };
    const moveDrag = (event) => {
        if (!drag) {
            return;
        }
        event.preventDefault();
        x = event.clientX - drag.x;
        y = event.clientY - drag.y;
        clamp();
        setTransform();
    };
    const endDrag = () => {
        drag = null;
        stage.classList.remove('is-dragging');
    };

    stage?.addEventListener('pointerdown', startDrag);
    stage?.addEventListener('pointermove', moveDrag);
    stage?.addEventListener('pointerup', endDrag);
    stage?.addEventListener('pointercancel', endDrag);
    stage?.addEventListener('wheel', (event) => {
        event.preventDefault();
        const next = Math.min(minScale * 5, Math.max(minScale, scale + (event.deltaY < 0 ? minScale * 0.12 : -minScale * 0.12)));
        zoom.value = String(next);
        zoom.dispatchEvent(new Event('input'));
    }, { passive: false });

    apply?.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (saving) {
            return;
        }
        if (!input) {
            showError('Elegí una imagen primero.');
            return;
        }
        if (!natural.w || !natural.h) {
            showError('La imagen todavía no cargó. Probá de nuevo.');
            return;
        }

        const box = size();
        const output = 512;
        const canvas = document.createElement('canvas');
        canvas.width = output;
        canvas.height = output;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            showError('El navegador no pudo recortar la imagen.');
            return;
        }

        if (cropKind === 'player') {
            ctx.fillStyle = '#071833';
            ctx.fillRect(0, 0, output, output);
        }
        ctx.drawImage(image, -x / scale, -y / scale, box / scale, box / scale, 0, 0, output, output);

        saving = true;
        apply.disabled = true;
        showError('');

        try {
            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob((value) => (value ? resolve(value) : reject(new Error('blob'))), 'image/png');
            });
            const dataUrl = await blobToDataUrl(blob);
            const file = new File([blob], cropKind === 'player' ? 'foto.png' : 'escudo.png', { type: 'image/png' });

            if (dataInput) {
                dataInput.value = dataUrl;
            }
            try {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
            } catch (error) {
                // Safari viejo: el hidden shield_data alcanza para guardar.
            }

            if (preview) {
                preview.src = dataUrl;
            }

            if (saveUrl && cropKind === 'player') {
                const form = new FormData();
                form.append('_token', csrfToken());
                form.append('type', 'Foto del jugador');
                form.append('file', file, 'foto.png');
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || (response.status === 419
                        ? 'La sesión expiró. Recargá la página e intentá de nuevo.'
                        : 'No se pudo guardar la foto.'));
                }
                const nextUrl = payload.url || dataUrl;
                document.querySelectorAll('.stc-pcard-photo, .pfc-hero-photo img, .pfc-hero-backdrop img, .ws-ficha-hero-photo img, .ws-player-photo img').forEach((img) => {
                    img.src = nextUrl;
                });
                closeWorkspaceModals();
                showWorkspaceFlash(payload.message || 'La foto del jugador quedó actualizada.');
                window.setTimeout(() => {
                    window.location.reload();
                }, 350);
                return;
            }

            const shouldSaveNow = Boolean(saveUrl);
            if (shouldSaveNow) {
                const form = new FormData();
                form.append('_method', 'PATCH');
                form.append('_token', csrfToken());
                form.append('shield_file', file);
                form.append('shield_data', dataUrl);
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || 'No se pudo guardar el escudo.');
                }
                if (preview && payload.url) {
                    preview.src = `${payload.url}${payload.url.includes('?') ? '&' : '?'}v=${Date.now()}`;
                }
            }

            closeWorkspaceModals();
            if (!shouldSaveNow) {
                restoreCropParent();
            }
        } catch (error) {
            showError(error.message || 'No se pudo guardar el recorte. Probá PNG o JPG.');
        } finally {
            saving = false;
            apply.disabled = false;
        }
    });
}

initShieldCrop();

function initClubShieldSelect() {
    document.querySelectorAll('[data-ws-club-select]').forEach((select) => {
        const form = select.closest('form');
        if (!form) {
            return;
        }

        const preview = form.querySelector('[data-ws-shield-preview]');
        const fileInput = form.querySelector('input[name="shield_file"]');
        const newWrap = form.querySelector('[data-ws-club-new]');
        const nameInput = form.querySelector('[data-ws-team-name]');
        const meta = form.closest('.ws-card')?.querySelector('[data-ws-club-meta]')
            || form.parentElement?.querySelector('[data-ws-club-meta]');

        const apply = () => {
            const option = select.selectedOptions[0];
            const logo = option?.getAttribute('data-logo') || '';
            const hasClub = Boolean(select.value);

            if (newWrap) {
                newWrap.hidden = hasClub;
            }

            if (preview) {
                if (hasClub && logo && !fileInput?.files?.length) {
                    preview.src = logo;
                    preview.hidden = false;
                } else if (!hasClub) {
                    preview.hidden = true;
                }
            }

            if (nameInput && hasClub && option?.dataset.name) {
                nameInput.value = option.dataset.name;
            }

            if (meta) {
                if (!hasClub) {
                    meta.textContent = '';
                    meta.hidden = true;
                    return;
                }

                const parts = [];
                const origin = [option.dataset.city, option.dataset.country].filter(Boolean).join(', ');
                if (origin) {
                    parts.push(origin);
                }
                if (option.dataset.delegate) {
                    parts.push('Delegado: ' + option.dataset.delegate);
                }

                meta.textContent = parts.join(' · ');
                meta.hidden = parts.length === 0;
            }
        };

        select.addEventListener('change', apply);
        apply();
    });
}

initClubShieldSelect();
bindDelegateSelect();

function bindDelegateSelect() {
    document.querySelectorAll('[data-ws-delegate-select]').forEach((select) => {
        const form = select.closest('form');
        const name = form?.querySelector('[name="delegate_name"]');
        const email = form?.querySelector('[name="delegate_email"]');

        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            if (!option?.value) {
                return;
            }
            if (name && option.dataset.name) {
                name.value = option.dataset.name;
            }
            if (email && option.dataset.email) {
                email.value = option.dataset.email;
            }
        });
    });
}

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
});

function bindTournamentGate() {
    document.querySelectorAll('[data-ws-tourney-board]').forEach((board) => {
        const search = board.querySelector('[data-ws-tourney-search]');
        const countLabel = board.querySelector('[data-ws-tourney-count]');
        const emptyMsg = board.querySelector('[data-ws-tourney-empty]');
        const items = () => [...board.querySelectorAll('[data-ws-tourney-item]')];

        const applyFilter = () => {
            const query = String(search?.value || '').trim().toLowerCase();
            let visible = 0;

            items().forEach((item) => {
                const haystack = String(item.getAttribute('data-search') || '').toLowerCase();
                const show = query === '' || haystack.includes(query);
                item.hidden = !show;
                if (show) {
                    visible += 1;
                }
            });

            if (countLabel) {
                const total = items().length;
                countLabel.textContent = query === ''
                    ? `${total} ${total === 1 ? 'torneo' : 'torneos'}`
                    : `${visible} de ${total} torneos`;
            }

            if (emptyMsg) {
                emptyMsg.hidden = visible > 0 || items().length === 0;
            }

            board.classList.toggle('is-filtering', query !== '');
        };

        search?.addEventListener('input', applyFilter);
    });
}

bindTournamentGate();

document.querySelectorAll('[data-ficha-steps]').forEach((form) => {
    if (form.dataset.fichaStepsReady === '1') {
        return;
    }
    form.dataset.fichaStepsReady = '1';

    const steps = [...form.querySelectorAll('[data-ficha-step]')];
    const dots = [...form.querySelectorAll('[data-ficha-dot]')];
    const prev = form.querySelector('[data-ficha-prev]');
    const next = form.querySelector('[data-ficha-next]');
    const finish = form.querySelector('[data-ficha-finish]');
    if (! steps.length) {
        return;
    }

    let current = 0;

    const show = (index) => {
        current = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach((step, i) => {
            step.hidden = i !== current;
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('is-current', i === current);
            dot.classList.toggle('is-done', i < current);
        });
        if (prev) {
            prev.hidden = current === 0;
        }
        if (next) {
            next.hidden = current === steps.length - 1;
        }
        if (finish) {
            finish.hidden = current !== steps.length - 1;
        }
    };

    const fieldsOf = (root) => [...root.querySelectorAll('input, select, textarea')]
        .filter((field) => ! field.disabled && field.type !== 'hidden');

    const validateStep = (index) => {
        const invalid = fieldsOf(steps[index]).find((field) => ! field.checkValidity());
        if (invalid) {
            invalid.reportValidity();
            invalid.focus();
            return false;
        }
        return true;
    };

    prev?.addEventListener('click', (event) => {
        event.preventDefault();
        show(current - 1);
    });

    next?.addEventListener('click', (event) => {
        event.preventDefault();
        if (! validateStep(current)) {
            return;
        }
        show(current + 1);
    });

    // type=submit: si está visible, el navegador envía; este handler solo valida.
    finish?.addEventListener('click', (event) => {
        if (! validateStep(current)) {
            event.preventDefault();
        }
    });

    dots.forEach((dot, index) => {
        dot.style.cursor = 'pointer';
        dot.addEventListener('click', () => {
            if (index > current) {
                for (let i = current; i < index; i += 1) {
                    if (! validateStep(i)) {
                        show(i);
                        return;
                    }
                }
            }
            show(index);
        });
    });

    show(0);
});

document.addEventListener('DOMContentLoaded', () => {
    const auto = document.querySelector('[data-ws-modal][data-ws-auto-open="1"]');
    if (auto) {
        openWorkspaceModal(auto.getAttribute('data-ws-modal'));
    }

    const hashTarget = window.location.hash ? document.querySelector(window.location.hash) : null;
    if (hashTarget) {
        hashTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});

document.querySelectorAll('[data-scorer]').forEach((root) => {
    const tabs = [...root.querySelectorAll('[data-scorer-tab]')];
    const panels = [...root.querySelectorAll('[data-scorer-panel]')];
    const show = (key) => {
        tabs.forEach((tab) => {
            const on = tab.dataset.scorerTab === key;
            tab.classList.toggle('is-on', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.scorerPanel !== key;
        });
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => show(tab.dataset.scorerTab)));
});

const lineupFormations = {
    5: [[50, 86], [22, 62], [78, 62], [32, 32], [68, 32]],
    6: [[50, 86], [22, 64], [78, 64], [22, 36], [78, 36], [50, 16]],
    7: [[50, 88], [18, 66], [50, 68], [82, 66], [28, 38], [72, 38], [50, 16]],
    8: [[50, 88], [18, 68], [50, 70], [82, 68], [22, 44], [78, 44], [32, 18], [68, 18]],
    9: [[50, 88], [16, 70], [40, 72], [60, 72], [84, 70], [28, 44], [72, 44], [34, 18], [66, 18]],
    10: [[50, 88], [16, 70], [38, 72], [62, 72], [84, 70], [22, 46], [50, 44], [78, 46], [32, 18], [68, 18]],
    11: [[50, 90], [14, 72], [36, 74], [64, 74], [86, 72], [24, 48], [50, 50], [76, 48], [22, 20], [50, 14], [78, 20]],
};

function lineupFormation(count) {
    const keys = Object.keys(lineupFormations).map(Number);
    const key = keys.includes(count)
        ? count
        : keys.reduce((best, item) => (Math.abs(item - count) < Math.abs(best - count) ? item : best), keys[0]);

    return lineupFormations[key];
}

function initMatchLineup() {
    const root = document.querySelector('[data-ws-lineup]');
    if (!root) {
        return;
    }

    const onField = Number(root.dataset.onField || 11);
    const list = root.querySelector('[data-lineup-list]');
    const graphic = root.querySelector('[data-lineup-graphic]');
    const pitchPlayers = root.querySelector('[data-ws-pitch-players]');
    const emptyNote = root.querySelector('[data-pitch-empty]');
    const openGraphic = root.querySelector('[data-lineup-graphic-open]');
    const backGraphic = root.querySelector('[data-lineup-graphic-back]');
    let activeTeam = 'home';

    const starterCount = (teamKey, exceptRow = null) => [...root.querySelectorAll('[data-lineup-row]')]
        .filter((row) => row !== exceptRow && row.dataset.teamKey === teamKey && row.querySelector('[data-role]')?.value === 'starter')
        .length;

    const showLimit = () => {
        let alert = root.querySelector('[data-lineup-limit]');
        if (!alert) {
            alert = document.createElement('p');
            alert.className = 'ws-lineup-alert';
            alert.dataset.lineupLimit = '1';
            root.querySelector('.ws-lineup-note')?.after(alert);
        }
        alert.textContent = `En esta categoría van ${onField} titulares. Marcá al resto para dejarlos como suplentes.`;
    };

    const paintRole = (row) => {
        const role = row.querySelector('[data-role]')?.value === 'starter' ? 'starter' : 'sub';
        const button = row.querySelector('[data-role-toggle]');
        const called = row.querySelector('[data-called]');
        if (called) {
            called.checked = role === 'starter';
        }
        if (!button) {
            return;
        }
        button.textContent = role === 'starter' ? 'Titular' : 'Suplente';
        button.classList.toggle('is-starter', role === 'starter');
        button.classList.toggle('is-sub', role !== 'starter');
        row.classList.toggle('is-off', role !== 'starter');
    };

    root.querySelectorAll('[data-lineup-row]').forEach((row) => paintRole(row));
    ['home', 'away'].forEach((teamKey) => {
        if (starterCount(teamKey) > onField) {
            showLimit();
        }
    });

    root.querySelectorAll('[data-lineup-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            activeTeam = tab.dataset.lineupTab;
            root.querySelectorAll('[data-lineup-tab]').forEach((item) => {
                item.classList.toggle('is-on', item === tab);
            });
            root.querySelectorAll('[data-lineup-panel]').forEach((panel) => {
                panel.hidden = panel.dataset.lineupPanel !== activeTeam;
            });
            if (!graphic.hidden) {
                renderPitch();
            }
        });
    });

    const setRole = (row, next) => {
        const role = row.querySelector('[data-role]');
        if (next === 'starter' && starterCount(row.dataset.teamKey, row) >= onField) {
            showLimit();
            paintRole(row);
            return;
        }
        role.value = next;
        paintRole(row);
    };

    root.querySelectorAll('[data-called]').forEach((input) => {
        input.addEventListener('change', () => {
            const row = input.closest('[data-lineup-row]');
            setRole(row, input.checked ? 'starter' : 'sub');
        });
    });

    root.querySelectorAll('[data-role-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-lineup-row]');
            const role = row.querySelector('[data-role]');
            setRole(row, role.value === 'starter' ? 'sub' : 'starter');
        });
    });

    const placeToken = (token, x, y) => {
        const nextX = Math.min(92, Math.max(8, x));
        const nextY = Math.min(94, Math.max(8, y));
        token.style.left = `${nextX}%`;
        token.style.top = `${nextY}%`;
        token.dataset.x = String(Math.round(nextX));
        token.dataset.y = String(Math.round(nextY));
    };

    const renderPitch = () => {
        const starters = [...root.querySelectorAll('[data-lineup-row]')]
            .filter((row) => row.dataset.teamKey === activeTeam && row.querySelector('[data-role]')?.value === 'starter')
            .sort((a, b) => Number(a.dataset.sort || 999) - Number(b.dataset.sort || 999));
        const slots = lineupFormation(onField);
        pitchPlayers.innerHTML = '';
        emptyNote.hidden = starters.length > 0;

        starters.forEach((row, index) => {
            const storedX = row.querySelector('[data-pos-x]')?.value;
            const storedY = row.querySelector('[data-pos-y]')?.value;
            const slot = slots[index] || [50, 50];
            const token = document.createElement('div');
            token.className = 'ws-pitch-token';
            token.dataset.playerId = row.dataset.playerId;
            const photo = document.createElement('img');
            photo.alt = '';
            photo.src = row.dataset.photo || '';
            const name = document.createElement('span');
            name.textContent = row.dataset.name || '';
            token.append(photo, name);
            pitchPlayers.append(token);
            placeToken(token, storedX === '' || storedX == null ? slot[0] : Number(storedX), storedY === '' || storedY == null ? slot[1] : Number(storedY));
            row.querySelector('[data-pos-x]').value = token.dataset.x;
            row.querySelector('[data-pos-y]').value = token.dataset.y;

            token.addEventListener('pointerdown', (event) => {
                token.setPointerCapture(event.pointerId);
                const move = (pointer) => {
                    const rect = pitchPlayers.getBoundingClientRect();
                    if (!rect.width || !rect.height) {
                        return;
                    }
                    placeToken(token, ((pointer.clientX - rect.left) / rect.width) * 100, ((pointer.clientY - rect.top) / rect.height) * 100);
                };
                const up = (pointer) => {
                    token.releasePointerCapture(pointer.pointerId);
                    token.removeEventListener('pointermove', move);
                    token.removeEventListener('pointerup', up);
                    const target = root.querySelector(`[data-lineup-row][data-player-id="${token.dataset.playerId}"]`);
                    if (!target) {
                        return;
                    }
                    target.querySelector('[data-pos-x]').value = token.dataset.x;
                    target.querySelector('[data-pos-y]').value = token.dataset.y;
                    target.dataset.sort = String(index);
                };
                token.addEventListener('pointermove', move);
                token.addEventListener('pointerup', up);
            });
        });
    };

    const showGraphic = (open) => {
        list.hidden = open;
        graphic.hidden = !open;
        openGraphic.hidden = open;
        backGraphic.hidden = !open;
        root.querySelectorAll('[data-lineup-tab]').forEach((tab) => {
            tab.hidden = false;
        });
        if (open) {
            renderPitch();
        }
    };

    openGraphic?.addEventListener('click', () => showGraphic(true));
    backGraphic?.addEventListener('click', () => showGraphic(false));
}

initMatchLineup();

document.querySelectorAll('[data-copy-guardian-link]').forEach((button) => {
    button.addEventListener('click', async () => {
        const wrap = button.closest('.ws-tutor-share');
        const input = wrap?.querySelector('[data-guardian-link]');
        const value = input?.value?.trim();
        if (! value) {
            return;
        }
        try {
            await navigator.clipboard.writeText(value);
            const label = button.getAttribute('data-copy-label') || 'Copiar link';
            button.textContent = 'Copiado';
            window.setTimeout(() => {
                button.textContent = label;
            }, 1600);
        } catch (error) {
            input.select();
            document.execCommand('copy');
        }
    });
});
