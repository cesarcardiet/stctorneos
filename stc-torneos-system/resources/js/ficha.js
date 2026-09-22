function fichaPlaceholder(value) {
    const trimmed = String(value ?? '').trim();

    return trimmed !== '' ? trimmed : '________________';
}

function fichaRelationshipLegalLabel(relationship) {
    const normalized = String(relationship ?? '').trim().toLowerCase();

    if (normalized === 'padre') {
        return 'padre';
    }

    if (normalized === 'madre') {
        return 'madre';
    }

    return 'padre / madre / tutor';
}

function fichaBuildParticipationText(values) {
    const relationshipLabel = values.relationshipLabel || fichaRelationshipLegalLabel(values.relationship);

    return [
        'AUTORIZACIÓN Y ACEPTACIÓN DE RESPONSABILIDAD',
        '',
        `Por medio del presente, yo ${fichaPlaceholder(values.guardianName)}, en mi carácter de ${relationshipLabel} del/la menor ${fichaPlaceholder(values.playerName)}, D.N.I. Nº ${fichaPlaceholder(values.playerDocument)}, autorizo expresamente su participación en la ${fichaPlaceholder(values.tournamentName)}, como integrante del equipo ${fichaPlaceholder(values.teamName)}, bajo mi responsabilidad y en conocimiento de las características y condiciones propias de la actividad deportiva.`,
        '',
        'Asimismo, declaro haber leído y aceptado el Reglamento del Torneo, comprometiéndome a informar y explicar su contenido al/la menor, y a velar por su respeto y cumplimiento durante su participación.',
        '',
        'En virtud de lo expuesto, manifiesto conocer y aceptar que la práctica deportiva implica riesgos propios de la actividad y que la participación del/la menor se realiza de manera voluntaria. En tal sentido, libero de responsabilidad a la organizadora del torneo y a las personas que intervengan en su organización, coordinación y desarrollo, respecto de aquellos hechos o accidentes que pudieran producirse como consecuencia de los riesgos propios de la práctica deportiva, sin perjuicio de las responsabilidades que legalmente pudieran corresponder.',
    ].join('\n');
}

function fichaBuildImageText(values) {
    return [
        'AUTORIZACIÓN DE USO DE IMAGEN',
        '',
        `Autorizo expresamente a STC Torneos a realizar, utilizar, publicar y difundir fotografías y/o videos en los que pueda aparecer el/la menor ${fichaPlaceholder(values.playerName)} durante su participación en el torneo y sus actividades relacionadas, a través de las distintas plataformas de comunicación, redes sociales, página web oficial y demás medios de difusión vinculados al torneo, con fines exclusivamente informativos, institucionales y de promoción del evento, sin que dicha autorización genere derecho a compensación económica, indemnización o contraprestación alguna a favor del/la menor o de quien suscribe.`,
    ].join('\n');
}

function fichaBuildMedicalText(values) {
    const relationshipLabel = values.relationshipLabel || fichaRelationshipLegalLabel(values.relationship);

    return [
        'APTITUD MÉDICA',
        '',
        `Declaro, en mi carácter de ${relationshipLabel} del/la menor, haberme hecho responsable de realizar la correspondiente evaluación y/o revisión médica previa a su participación en el torneo, habiendo sido evaluado/a por un profesional de la salud, quien ha determinado que el/la menor ${fichaPlaceholder(values.playerName)} se encuentra apto/a para realizar la actividad deportiva en la que participará.`,
        '',
        'Asimismo, dejo constancia de que me responsabilizo por la veracidad de la declaración de aptitud médica del/la menor para la práctica de dicha actividad.',
    ].join('\n');
}

function fichaFormatBirth(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return '________________';
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        return raw;
    }

    const parts = raw.split('-');
    if (parts.length !== 3) {
        return raw;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function fichaBirthDigitsOnly(value) {
    return String(value ?? '').replace(/\D+/g, '').slice(0, 8);
}

function fichaBirthDisplayFromDigits(digits) {
    const clean = fichaBirthDigitsOnly(digits);
    if (clean.length <= 2) {
        return clean;
    }
    if (clean.length <= 4) {
        return `${clean.slice(0, 2)}/${clean.slice(2)}`;
    }

    return `${clean.slice(0, 2)}/${clean.slice(2, 4)}/${clean.slice(4, 8)}`;
}

function fichaBirthIsoFromDisplay(display) {
    const match = String(display ?? '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) {
        return '';
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const minYear = 1990;
    const maxYear = new Date().getFullYear();

    if (month < 1 || month > 12) {
        return '';
    }

    if (day < 1 || day > 31) {
        return '';
    }

    if (year < minYear || year > maxYear) {
        return '';
    }

    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return '';
    }

    if (date > new Date()) {
        return '';
    }

    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function fichaBirthErrorMessage(display) {
    const raw = String(display ?? '').trim();
    if (raw === '') {
        return 'Completá la fecha de nacimiento (DD/MM/AAAA).';
    }

    const digits = fichaBirthDigitsOnly(raw);
    if (digits.length < 8) {
        return 'La fecha tiene que tener día, mes y año completo (ej. 12/06/2014).';
    }

    const match = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) {
        return 'Usá el formato DD/MM/AAAA.';
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const minYear = 1990;
    const maxYear = new Date().getFullYear();

    if (month < 1 || month > 12) {
        return 'El mes tiene que estar entre 01 y 12.';
    }

    if (day < 1 || day > 31) {
        return 'El día tiene que estar entre 01 y 31.';
    }

    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return 'Esa fecha no existe (revisá día y mes).';
    }

    if (year < minYear) {
        return 'El año tiene que ser desde 1990.';
    }

    if (date > new Date()) {
        return 'La fecha de nacimiento no puede ser futura.';
    }

    return '';
}

function fichaSyncBirthFields(root) {
    const display = root.querySelector('[data-ficha-birth-display]');
    const hidden = root.querySelector('[data-ficha-birth-value]');
    if (!display || !hidden) {
        return;
    }

    hidden.value = fichaBirthIsoFromDisplay(display.value);
}

function fichaInitBirthDateMask(root) {
    const display = root.querySelector('[data-ficha-birth-display]');
    const hidden = root.querySelector('[data-ficha-birth-value]');
    if (!display || !hidden) {
        return;
    }

    if (hidden.value && !display.value) {
        display.value = fichaFormatBirth(hidden.value);
    }

    const apply = () => {
        const formatted = fichaBirthDisplayFromDigits(display.value);
        display.value = formatted;
        hidden.value = fichaBirthIsoFromDisplay(formatted);
        const message = formatted === '' ? '' : fichaBirthErrorMessage(formatted);
        display.setCustomValidity(message);
    };

    display.addEventListener('input', apply);
    display.addEventListener('blur', apply);

    apply();
}

function fichaReadValues(root) {
    const form = root.querySelector('[data-ficha-form]');
    let base = {};

    try {
        base = JSON.parse(root.dataset.authContext || '{}');
    } catch {
        base = {};
    }

    const guardianFirst = String(form?.querySelector('[name="guardian_first_name"]')?.value ?? '').trim();
    const guardianLast = String(form?.querySelector('[name="guardian_last_name"]')?.value ?? '').trim();
    const guardianName = [guardianFirst, guardianLast].filter(Boolean).join(' ')
        || String(form?.querySelector('[name="guardian_name"]')?.value ?? base.guardian_name ?? '').trim();
    const guardianDocument = String(form?.querySelector('[name="guardian_document_number"]')?.value ?? base.guardian_document ?? '').trim();
    const relationship = String(form?.querySelector('[name="relationship"]')?.value ?? base.relationship ?? '').trim();
    const playerDocument = String(form?.querySelector('[name="document_number"]')?.value ?? base.player_document ?? root.dataset.playerDocument ?? '').trim();
    const firstName = String(form?.querySelector('[name="first_name"]')?.value ?? '').trim();
    const lastName = String(form?.querySelector('[name="last_name"]')?.value ?? '').trim();
    const playerName = [firstName, lastName].filter(Boolean).join(' ')
        || String(base.player_name ?? root.dataset.playerName ?? '').trim();

    const fullInput = form?.querySelector('[data-ficha-guardian-full]');
    if (fullInput) {
        fullInput.value = guardianName;
    }

    return {
        guardianName,
        guardianDocument,
        relationship,
        relationshipLabel: fichaRelationshipLegalLabel(relationship),
        playerName,
        playerDocument,
        teamName: String(base.team_name ?? root.dataset.teamName ?? '').trim(),
        tournamentName: String(base.tournament_name ?? root.dataset.tournamentName ?? '').trim(),
    };
}

function fichaRefreshLegalTexts(root) {
    const form = root.querySelector('[data-ficha-form]');
    const values = fichaReadValues(root);

    root.querySelector('[data-legal="participation"]')?.replaceChildren(document.createTextNode(fichaBuildParticipationText(values)));
    root.querySelector('[data-legal="image"]')?.replaceChildren(document.createTextNode(fichaBuildImageText(values)));
    root.querySelector('[data-legal="medical"]')?.replaceChildren(document.createTextNode(fichaBuildMedicalText(values)));

    const summary = root.querySelector('[data-ficha-summary]');
    if (!summary) {
        return;
    }

    summary.querySelector('[data-summary-guardian]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.guardianName)));
    summary.querySelector('[data-summary-guardian-document]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.guardianDocument)));
    summary.querySelector('[data-summary-player]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.playerName)));
    summary.querySelector('[data-summary-player-document]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.playerDocument)));
    summary.querySelector('[data-summary-birth]')?.replaceChildren(document.createTextNode(fichaFormatBirth(form?.querySelector('[name="birth_date"]')?.value)));
    summary.querySelector('[data-summary-kit]')?.replaceChildren(document.createTextNode(fichaPlaceholder(form?.querySelector('[name="kit_size"]:checked')?.value ?? form?.querySelector('[name="kit_size"]')?.value)));
    summary.querySelector('[data-summary-height]')?.replaceChildren(document.createTextNode(fichaPlaceholder(form?.querySelector('[name="height"]')?.value)));
    summary.querySelector('[data-summary-weight]')?.replaceChildren(document.createTextNode(fichaPlaceholder(form?.querySelector('[name="weight"]')?.value)));
    summary.querySelector('[data-summary-team]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.teamName)));
    summary.querySelector('[data-summary-tournament]')?.replaceChildren(document.createTextNode(fichaPlaceholder(values.tournamentName)));
    summary.querySelector('[data-summary-player-email]')?.replaceChildren(document.createTextNode(fichaPlaceholder(form?.querySelector('[name="player_email"]')?.value)));
}

function fichaSetStep(root, step) {
    const total = Number(root.dataset.totalSteps ?? '1');
    const nextStep = Math.max(1, Math.min(step, total));

    root.dataset.currentStep = String(nextStep);

    root.querySelectorAll('[data-step]').forEach((panel) => {
        panel.hidden = panel.dataset.step !== String(nextStep);
    });

    const errorSteps = new Set(
        String(root.dataset.errorSteps || '')
            .split(',')
            .map((value) => Number(value.trim()))
            .filter((value) => value > 0)
    );

    root.querySelectorAll('[data-step-dot]').forEach((dot) => {
        const dotStep = Number(dot.dataset.stepDot);
        dot.classList.toggle('is-active', dotStep === nextStep);
        dot.classList.toggle('is-done', dotStep < nextStep && !errorSteps.has(dotStep));
        dot.classList.toggle('is-error', errorSteps.has(dotStep));
    });

    const prevButton = root.querySelector('[data-prev]');
    const nextButton = root.querySelector('[data-next]');
    const completeButton = root.querySelector('[data-complete]');

    if (prevButton) {
        prevButton.hidden = nextStep <= 1;
    }

    if (nextButton) {
        nextButton.hidden = nextStep >= total;
    }

    if (completeButton) {
        completeButton.hidden = nextStep < total;
    }

    const label = root.querySelector('[data-step-label]');
    if (label) {
        label.textContent = `Paso ${nextStep} de ${total}`;
    }

    const progress = root.querySelector('[data-ficha-progress-bar]');
    if (progress) {
        progress.style.width = `${Math.round((nextStep / total) * 100)}%`;
    }
}

function fichaMarkStepError(root, step) {
    const current = String(root.dataset.errorSteps || '')
        .split(',')
        .map((value) => Number(value.trim()))
        .filter((value) => value > 0);
    if (!current.includes(step)) {
        current.push(step);
        current.sort((a, b) => a - b);
        root.dataset.errorSteps = current.join(',');
    }
    fichaSetStep(root, Number(root.dataset.currentStep || step));
}

function fichaClearStepError(root, step) {
    const current = String(root.dataset.errorSteps || '')
        .split(',')
        .map((value) => Number(value.trim()))
        .filter((value) => value > 0 && value !== step);
    root.dataset.errorSteps = current.join(',');
    fichaSetStep(root, Number(root.dataset.currentStep || step));
}

function fichaDecimalMissing(form, selector, message, min, max) {
    const field = form.querySelector(selector);
    const raw = String(field?.value ?? '').trim().replace(',', '.');
    if (raw === '') {
        fichaFocusField(field);
        return message;
    }

    if (!/^\d+(\.\d+)?$/.test(raw)) {
        fichaFocusField(field);
        return 'Usá solo números (podés usar punto decimal).';
    }

    const number = Number(raw);
    if (!Number.isFinite(number) || number < min || number > max) {
        fichaFocusField(field);
        return `El valor tiene que estar entre ${min} y ${max}.`;
    }

    if (field) {
        field.value = raw;
    }

    return '';
}

function fichaDniMissing(form, selector, message) {
    const field = form.querySelector(selector);
    const raw = String(field?.value ?? '').replace(/\D+/g, '');
    if (field) {
        field.value = raw;
    }

    if (!/^\d{7,8}$/.test(raw)) {
        fichaFocusField(field);
        return message;
    }

    return '';
}

function fichaEmailFormatOk(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value ?? '').trim());
}

function fichaFocusField(field) {
    if (!field) {
        return;
    }

    try {
        field.focus({ preventScroll: true });
    } catch {
        field.focus();
    }
    field.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
}

function fichaFieldMissing(form, selector, message) {
    const field = form.querySelector(selector);
    const value = String(field?.value ?? '').trim();

    if (value !== '') {
        return '';
    }

    fichaFocusField(field);

    return message;
}

function fichaKitMissing(form) {
    const selected = form.querySelector('[name="kit_size"]:checked');
    if (selected?.value) {
        return '';
    }

    const select = form.querySelector('select[name="kit_size"]');
    if (select && String(select.value ?? '').trim() !== '') {
        return '';
    }

    form.querySelector('.ws-kit-sizes')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    return 'Elegí el talle de camiseta.';
}

function fichaDocumentsMissing(root) {
    const pending = root.querySelector('[data-ficha-doc][data-doc-must="1"][data-doc-uploaded="0"], [data-ficha-photo][data-doc-uploaded="0"]');
    if (!pending) {
        return '';
    }

    const type = pending.dataset.docType || (pending.hasAttribute('data-ficha-photo') ? 'Foto del jugador' : 'documento');
    pending.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    return `Adjuntá ${type} para continuar.`;
}

function fichaAuthMissing(form, key, message) {
    const checkbox = form.querySelector(`[data-auth="${key}"]`);
    if (checkbox?.checked) {
        return '';
    }

    fichaFocusField(checkbox);

    return message;
}

function fichaValidateStep(root, step) {
    const form = root.querySelector('[data-ficha-form]');
    if (!form) {
        return '';
    }

    if (step === 1) {
        fichaSyncGuardianName(form);

        return (
            fichaFieldMissing(form, '[name="last_name"]', 'Completá el apellido del/de la jugador/a.') ||
            fichaFieldMissing(form, '[name="first_name"]', 'Completá el nombre del/de la jugador/a.') ||
            fichaDniMissing(form, '[name="document_number"]', 'El DNI del jugador tiene que tener 7 u 8 números.') ||
            fichaFieldMissing(form, '[name="guardian_first_name"]', 'Completá el nombre del tutor.') ||
            fichaFieldMissing(form, '[name="guardian_last_name"]', 'Completá el apellido del tutor.') ||
            fichaFieldMissing(form, '[name="relationship"]', 'Indicá el vínculo con el/la jugador/a.') ||
            fichaDniMissing(form, '[name="guardian_document_number"]', 'El DNI del tutor tiene que tener 7 u 8 números.') ||
            fichaFieldMissing(form, '[name="email"]', 'Completá el email de contacto.') ||
            (!fichaEmailFormatOk(form.querySelector('[name="email"]')?.value) ? (fichaFocusField(form.querySelector('[name="email"]')), 'El email del tutor no es válido.') : '') ||
            (form.querySelector('[name="email"]')?.dataset.emailOk === '0' ? (fichaFocusField(form.querySelector('[name="email"]')), (form.querySelector('[name="email"]')?.dataset.emailMessage || 'Revisá el email del tutor.')) : '') ||
            fichaFieldMissing(form, '[name="guardian_alternate_name"]', 'Completá el nombre del contacto alternativo.') ||
            fichaFieldMissing(form, '[name="guardian_alternate_phone"]', 'Completá el teléfono del contacto alternativo.')
        );
    }

    if (step === 2) {
        fichaSyncBirthFields(root);
        const birthDisplay = form.querySelector('[name="birth_date_display"]');
        const birthError = fichaBirthErrorMessage(birthDisplay?.value);
        if (birthError) {
            fichaFocusField(birthDisplay);
            return birthError;
        }

        return fichaFieldMissing(form, '[name="address"]', 'Completá el domicilio.');
    }

    if (step === 3) {
        const jersey = form.querySelector('[name="jersey_number"]');
        if (jersey && String(jersey.value ?? '').trim() !== '') {
            const jerseyValue = Number(jersey.value);
            if (!Number.isInteger(jerseyValue) || jerseyValue < 1 || jerseyValue > 99) {
                fichaFocusField(jersey);
                return 'El número de camiseta tiene que ser un entero entre 1 y 99.';
            }
        }

        return (
            fichaFieldMissing(form, '[name="kit_size"]', 'Elegí el talle de camiseta.') ||
            fichaDecimalMissing(form, '[name="height"]', 'Completá la altura en mts.', 0.5, 2.5) ||
            fichaDecimalMissing(form, '[name="weight"]', 'Completá el peso en Kg.', 15, 150)
        );
    }

    if (step === 4) {
        return (
            fichaFieldMissing(form, '[name="blood_type"]', 'Elegí el grupo sanguíneo.') ||
            fichaFieldMissing(form, '[name="medical_coverage"]', 'Completá la cobertura médica o escribí Ninguna.') ||
            fichaFieldMissing(form, '[name="allergies"]', 'Completá alergias o escribí Ninguna.') ||
            fichaFieldMissing(form, '[name="medication"]', 'Completá medicación o escribí Ninguna.') ||
            fichaFieldMissing(form, '[name="illnesses"]', 'Completá enfermedades o escribí Ninguna.') ||
            fichaFieldMissing(form, '[name="restrictions"]', 'Completá restricciones alimentarias o escribí Ninguna.') ||
            fichaFieldMissing(form, '[name="vaccination_calendar_complete"]', 'Indicá si el calendario de vacunación está completo.') ||
            fichaFieldMissing(form, '[name="emergency_contact"]', 'Completá observaciones o escribí Ninguna.')
        );
    }

    if (step === 5) {
        return fichaDocumentsMissing(root);
    }

    if (step === 6) {
        return fichaAuthMissing(form, 'Autorización', 'Tenés que marcar la casilla de autorización y responsabilidad para continuar.');
    }

    if (step === 7) {
        return fichaAuthMissing(form, 'Uso de imagen', 'Tenés que autorizar el uso de imagen para continuar.');
    }

    if (step === 8) {
        return fichaAuthMissing(form, 'Apto médico', 'Tenés que declarar la aptitud médica para continuar.');
    }

    if (step === 9) {
        const consent = form.querySelector('[name="consent"]');
        if (!consent?.checked) {
            return 'Confirmá que los datos son correctos antes de enviar.';
        }

        const playerEmail = String(form.querySelector('[name="player_email"]')?.value ?? '').trim();
        if (playerEmail !== '' && !fichaEmailFormatOk(playerEmail)) {
            fichaFocusField(form.querySelector('[name="player_email"]'));
            return 'El email del jugador no es válido.';
        }

        if (form.querySelector('[name="player_email"]')?.dataset.emailOk === '0') {
            fichaFocusField(form.querySelector('[name="player_email"]'));
            return form.querySelector('[name="player_email"]')?.dataset.emailMessage || 'Revisá el email del jugador.';
        }
    }

    return '';
}

function fichaSyncGuardianName(form) {
    const first = String(form.querySelector('[name="guardian_first_name"]')?.value ?? '').trim();
    const last = String(form.querySelector('[name="guardian_last_name"]')?.value ?? '').trim();
    const full = form.querySelector('[data-ficha-guardian-full]');
    if (full) {
        full.value = [first, last].filter(Boolean).join(' ');
    }
}

function fichaInitNoneDefaults(root) {
    root.querySelectorAll('[data-ficha-none-default]').forEach((field) => {
        if (String(field.value ?? '').trim() === '') {
            field.value = 'Ninguna';
        }

        field.addEventListener('focus', () => {
            if (String(field.value ?? '').trim().toLowerCase() === 'ninguna') {
                field.value = '';
            }
        });

        field.addEventListener('blur', () => {
            if (String(field.value ?? '').trim() === '') {
                field.value = 'Ninguna';
            }
        });
    });
}

function fichaInitGuardianNameParts(root) {
    const form = root.querySelector('[data-ficha-form]');
    if (!form) {
        return;
    }

    form.querySelectorAll('[data-ficha-guardian-part]').forEach((field) => {
        field.addEventListener('input', () => {
            fichaSyncGuardianName(form);
            fichaRefreshLegalTexts(root);
        });
    });
}

async function fichaLoadImageElement(fileOrUrl) {
    const img = new Image();
    img.decoding = 'async';

    const shouldRevoke = typeof fileOrUrl !== 'string';
    const url = shouldRevoke ? URL.createObjectURL(fileOrUrl) : fileOrUrl;

    await new Promise((resolve, reject) => {
        img.onload = () => resolve();
        img.onerror = () => reject(new Error('image'));
        img.src = url;
    });

    return {
        img,
        revoke: () => {
            if (shouldRevoke) {
                URL.revokeObjectURL(url);
            }
        },
    };
}

function fichaCanvasToBlob(canvas, type, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob) {
                resolve(blob);
            } else {
                reject(new Error('blob'));
            }
        }, type, quality);
    });
}

/**
 * Reduce automáticamente fotos del celular a un tamaño liviano (sin pedir nada al tutor).
 */
async function fichaCompressImageFile(file, options = {}) {
    const maxEdge = options.maxEdge ?? 1600;
    const maxBytes = options.maxBytes ?? 1_200_000;
    const type = options.type ?? 'image/jpeg';
    const baseName = String(file?.name || 'foto.jpg').replace(/\.[^.]+$/, '') || 'foto';

    if (!file || !String(file.type || '').startsWith('image/')) {
        return file;
    }

    // Ya es liviana: no tocar.
    if (file.size <= maxBytes && file.size <= 900_000 && (file.type === 'image/jpeg' || file.type === 'image/webp')) {
        return file;
    }

    const loaded = await fichaLoadImageElement(file);
    const img = loaded.img;

    try {
        const scale = Math.min(1, maxEdge / Math.max(img.naturalWidth || 1, img.naturalHeight || 1));
        const width = Math.max(1, Math.round((img.naturalWidth || 1) * scale));
        const height = Math.max(1, Math.round((img.naturalHeight || 1) * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return file;
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.drawImage(img, 0, 0, width, height);

        let quality = 0.82;
        let blob = await fichaCanvasToBlob(canvas, type, quality);

        while (blob.size > maxBytes && quality > 0.45) {
            quality -= 0.08;
            blob = await fichaCanvasToBlob(canvas, type, quality);
        }

        if (blob.size > maxBytes) {
            const tighter = Math.min(1, 1200 / Math.max(width, height));
            canvas.width = Math.max(1, Math.round(width * tighter));
            canvas.height = Math.max(1, Math.round(height * tighter));
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            blob = await fichaCanvasToBlob(canvas, type, 0.7);
        }

        return new File([blob], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
    } finally {
        loaded.revoke();
    }
}

async function fichaAssignFileToInput(input, file) {
    if (!input || !file) {
        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    input.files = transfer.files;
}

function fichaInitPhotoEditor(root) {
    const wrap = root.querySelector('[data-ficha-photo]');
    const modal = document.querySelector('[data-ficha-crop-modal]');
    if (!wrap || !modal) {
        return;
    }

    const preview = wrap.querySelector('[data-ficha-photo-preview]');
    const fileInput = wrap.querySelector('[data-ficha-photo-file]');
    const hiddenFile = wrap.querySelector('[data-doc-input="Foto del jugador"]');
    const dataInput = wrap.querySelector('[data-ficha-photo-data]');
    const stage = modal.querySelector('[data-ficha-crop-stage]');
    const image = modal.querySelector('[data-ficha-crop-image]');
    const zoom = modal.querySelector('[data-ficha-crop-zoom]');
    let offsetX = 0;
    let offsetY = 0;
    let scale = 1;
    let dragging = false;
    let startX = 0;
    let startY = 0;

    const markUploaded = () => {
        wrap.dataset.docUploaded = '1';
    };

    const clearRawPhotoFile = () => {
        if (hiddenFile) {
            hiddenFile.value = '';
        }
        if (fileInput) {
            fileInput.value = '';
        }
    };

    const render = () => {
        if (!image) {
            return;
        }
        image.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
    };

    const openModal = (url) => {
        image.src = url;
        offsetX = 0;
        offsetY = 0;
        scale = 1;
        if (zoom) {
            zoom.value = '1';
        }
        modal.hidden = false;
        render();
    };

    fileInput?.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) {
            return;
        }

        try {
            const compressed = await fichaCompressImageFile(file, { maxEdge: 1400, maxBytes: 900_000 });
            const dataUrl = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(String(reader.result || ''));
                reader.onerror = () => reject(new Error('read'));
                reader.readAsDataURL(compressed);
            });

            if (dataInput) {
                dataInput.value = dataUrl;
            }
            if (preview) {
                preview.src = dataUrl;
            }

            // No subir el original pesado: solo photo_data ya optimizado.
            clearRawPhotoFile();
            markUploaded();
            openModal(dataUrl);
        } catch {
            // Si el navegador no pudo optimizar, igual dejamos la vista previa y seguimos.
            const url = URL.createObjectURL(file);
            if (preview) {
                preview.src = url;
            }
            markUploaded();
            openModal(url);
        }
    });

    wrap.querySelector('[data-ficha-photo-adjust]')?.addEventListener('click', () => {
        if (!preview?.src) {
            fileInput?.click();

            return;
        }
        openModal(preview.src);
    });

    zoom?.addEventListener('input', () => {
        scale = Number(zoom.value || 1);
        render();
    });

    stage?.addEventListener('pointerdown', (event) => {
        dragging = true;
        startX = event.clientX - offsetX;
        startY = event.clientY - offsetY;
        stage.setPointerCapture(event.pointerId);
    });
    stage?.addEventListener('pointermove', (event) => {
        if (!dragging) {
            return;
        }
        offsetX = event.clientX - startX;
        offsetY = event.clientY - startY;
        render();
    });
    stage?.addEventListener('pointerup', () => {
        dragging = false;
    });

    modal.querySelector('[data-ficha-crop-cancel]')?.addEventListener('click', () => {
        modal.hidden = true;
    });

    modal.querySelector('[data-ficha-crop-apply]')?.addEventListener('click', () => {
        const size = 640;
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        if (!ctx || !image.naturalWidth) {
            modal.hidden = true;

            return;
        }

        const frame = stage.getBoundingClientRect();
        const img = image.getBoundingClientRect();
        const ratio = image.naturalWidth / Math.max(img.width, 1);
        const sx = Math.max(0, (frame.left - img.left) * ratio);
        const sy = Math.max(0, (frame.top - img.top) * ratio);
        const sw = Math.min(image.naturalWidth - sx, frame.width * ratio);
        const sh = Math.min(image.naturalHeight - sy, frame.height * ratio);

        ctx.fillStyle = '#0b1528';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(image, sx, sy, sw, sh, 0, 0, size, size);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.82);
        if (dataInput) {
            dataInput.value = dataUrl;
        }
        if (preview) {
            preview.src = dataUrl;
        }
        clearRawPhotoFile();
        markUploaded();
        modal.hidden = true;
    });
}

function fichaShowError(root, message, focusSelector = null) {
    let alert = root.querySelector('[data-ficha-alert]');
    if (!alert) {
        alert = document.querySelector('[data-ficha-alert]');
    }
    if (!alert) {
        window.alert(message);

        return;
    }

    // Si el toast quedó dentro del wizard, lo sacamos al body para que sea overlay fijo.
    if (alert.parentElement !== document.body) {
        document.body.appendChild(alert);
    }

    const text = alert.querySelector('[data-ficha-alert-text]');
    if (text) {
        text.textContent = message;
    } else {
        alert.textContent = message;
    }

    alert.hidden = false;
    alert.classList.add('is-visible');

    window.clearTimeout(alert._fichaToastTimer);
    alert._fichaToastTimer = window.setTimeout(() => fichaClearError(root), 5200);

    if (focusSelector) {
        const field = root.querySelector(focusSelector);
        field?.focus({ preventScroll: true });
        field?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }
}

function fichaClearError(root) {
    const alert = root?.querySelector?.('[data-ficha-alert]') || document.querySelector('[data-ficha-alert]');
    if (!alert) {
        return;
    }

    window.clearTimeout(alert._fichaToastTimer);
    alert.hidden = true;
    alert.classList.remove('is-visible');
    const text = alert.querySelector('[data-ficha-alert-text]');
    if (text) {
        text.textContent = '';
    } else {
        alert.textContent = '';
    }
}

function fichaInitYesNoFields(root) {
    root.querySelectorAll('[data-ws-yes-no-select]').forEach((select) => {
        const detail = select.closest('form')?.querySelector(`[data-ws-yes-no-detail][data-ws-yes-no-for="${select.name}"]`);
        if (!detail) {
            return;
        }

        const sync = () => {
            detail.hidden = select.value !== '1';
        };

        select.addEventListener('change', sync);
        sync();
    });
}

function fichaInitDocumentPreviews(root) {
    root.querySelectorAll('[data-doc-input]').forEach((input) => {
        // La foto del jugador se maneja en el editor (photo_data).
        if (input.dataset.docInput === 'Foto del jugador') {
            return;
        }

        input.addEventListener('change', async () => {
            const row = input.closest('[data-ficha-doc]');
            const preview = row?.querySelector('[data-doc-preview]');
            const status = row?.querySelector('.ficha-doc-status');
            const file = input.files?.[0];

            if (!file || !row) {
                return;
            }

            if (status) {
                status.textContent = 'Preparando…';
            }

            try {
                const compressed = await fichaCompressImageFile(file, { maxEdge: 1600, maxBytes: 1_200_000 });
                await fichaAssignFileToInput(input, compressed);

                row.dataset.docUploaded = '1';
                if (status) {
                    status.textContent = 'Listo';
                }
                if (preview) {
                    preview.src = URL.createObjectURL(compressed);
                    preview.hidden = false;
                }
            } catch {
                // Si falla la optimización, igual dejamos el archivo elegido para no trabar al tutor.
                row.dataset.docUploaded = '1';
                if (status) {
                    status.textContent = 'Listo';
                }
                if (preview) {
                    preview.src = URL.createObjectURL(file);
                    preview.hidden = false;
                }
            }
        });
    });
}

function fichaInitNumericGuards(root) {
    root.querySelectorAll('[data-ficha-dni]').forEach((field) => {
        field.addEventListener('input', () => {
            field.value = String(field.value || '').replace(/\D+/g, '').slice(0, 8);
        });
    });

    root.querySelectorAll('[data-ficha-int]').forEach((field) => {
        field.addEventListener('input', () => {
            field.value = String(field.value || '').replace(/[^\d]/g, '').slice(0, 2);
        });
    });

    root.querySelectorAll('[data-ficha-decimal]').forEach((field) => {
        field.addEventListener('input', () => {
            let value = String(field.value || '').replace(',', '.').replace(/[^\d.]/g, '');
            const parts = value.split('.');
            if (parts.length > 2) {
                value = `${parts[0]}.${parts.slice(1).join('')}`;
            }
            field.value = value;
        });
    });

    root.querySelectorAll('[data-ficha-text]').forEach((field) => {
        field.addEventListener('input', () => {
            field.value = String(field.value || '').replace(/[0-9]/g, '');
        });
    });
}

function fichaInitEmailChecks(root) {
    const url = String(root.dataset.checkEmailUrl || '').trim();
    const tokenMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const csrf = root.querySelector('[data-ficha-form] input[name="_token"]')?.value || tokenMeta;

    root.querySelectorAll('[data-ficha-email]').forEach((field) => {
        let timer = null;
        const kind = field.dataset.fichaEmail || 'tutor';
        const hint = root.querySelector(`[data-email-hint="${kind}"]`);

        const setState = (ok, message) => {
            field.dataset.emailOk = ok === null ? '' : (ok ? '1' : '0');
            field.dataset.emailMessage = message || '';
            field.classList.toggle('is-email-bad', ok === false);
            field.classList.toggle('is-email-ok', ok === true);
            if (hint) {
                hint.textContent = message || (kind === 'player'
                    ? 'Opcional. Se verifica mientras escribís.'
                    : 'Se verifica mientras escribís.');
                hint.classList.toggle('is-bad', ok === false);
                hint.classList.toggle('is-ok', ok === true);
            }
        };

        const run = async () => {
            const value = String(field.value || '').trim();
            if (value === '') {
                setState(null, '');
                return;
            }

            if (!fichaEmailFormatOk(value)) {
                setState(false, 'Formato de email inválido.');
                return;
            }

            if (!url || kind === 'tutor') {
                setState(true, 'Email con formato válido.');
                return;
            }

            try {
                hint && (hint.textContent = 'Verificando…');
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ email: value, kind }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    setState(false, payload.message || 'No se pudo verificar el email.');
                    return;
                }
                setState(payload.ok !== false, payload.message || (payload.ok === false ? 'Email no disponible.' : 'Email disponible.'));
            } catch {
                setState(true, 'Email con formato válido.');
            }
        };

        field.addEventListener('input', () => {
            setState(null, '');
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 450);
        });
        field.addEventListener('blur', run);
    });
}

function initFichaWizard() {
    document.querySelectorAll('[data-ficha-wizard]').forEach((root) => {
        if (root.dataset.locked === '1') {
            return;
        }

        const form = root.querySelector('[data-ficha-form]');
        if (!form) {
            return;
        }

        const refresh = () => fichaRefreshLegalTexts(root);
        refresh();

        fichaInitYesNoFields(root);
        fichaInitDocumentPreviews(root);
        fichaInitBirthDateMask(root);
        fichaInitNoneDefaults(root);
        fichaInitGuardianNameParts(root);
        fichaInitPhotoEditor(root);
        fichaInitNumericGuards(root);
        fichaInitEmailChecks(root);
        fichaSyncGuardianName(form);

        form.querySelectorAll('[name="guardian_first_name"], [name="guardian_last_name"], [name="guardian_document_number"], [name="relationship"], [name="document_number"], [name="email"], [name="player_email"], [name="first_name"], [name="last_name"], [name="birth_date_display"], [name="height"], [name="weight"]').forEach((field) => {
            field.addEventListener('input', refresh);
            field.addEventListener('change', refresh);
        });

        form.querySelectorAll('[name="kit_size"]').forEach((field) => {
            field.addEventListener('input', refresh);
            field.addEventListener('change', refresh);
        });

        fichaSetStep(root, Number(root.dataset.initialStep ?? '1'));

        document.querySelector('[data-ficha-alert-close]')?.addEventListener('click', () => fichaClearError(root));

        // Errores del servidor: toast flotante, y bajar al bloque del formulario (no al logo).
        const serverBox = document.querySelector('.ficha-setup-alert.is-error');
        if (serverBox) {
            const serverErrors = [...serverBox.querySelectorAll('div')]
                .map((node) => node.textContent.trim())
                .filter(Boolean);
            serverBox.hidden = true;
            if (serverErrors.length) {
                fichaShowError(root, serverErrors[0]);
            }
        } else if (String(root.dataset.errorSteps || '').trim() !== '') {
            const fallback = [...document.querySelectorAll('.login-alert:not([data-ficha-alert])')]
                .map((node) => node.textContent.trim())
                .filter(Boolean);
            if (fallback.length) {
                fichaShowError(root, fallback[0]);
            }
        }

        if (String(root.dataset.errorSteps || '').trim() !== '' || serverBox) {
            window.requestAnimationFrame(() => {
                root.closest('.ficha-setup-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        root.querySelector('[data-prev]')?.addEventListener('click', () => {
            fichaClearError(root);
            fichaSetStep(root, Number(root.dataset.currentStep) - 1);
        });

        root.querySelector('[data-next]')?.addEventListener('click', () => {
            const current = Number(root.dataset.currentStep);
            const error = fichaValidateStep(root, current);
            if (error) {
                fichaMarkStepError(root, current);
                fichaShowError(root, error);

                return;
            }

            fichaClearStepError(root, current);
            fichaClearError(root);
            fichaRefreshLegalTexts(root);
            fichaSetStep(root, current + 1);
        });

        root.querySelectorAll('[data-step-dot]').forEach((dot) => {
            dot.addEventListener('click', () => {
                const target = Number(dot.dataset.stepDot);
                const current = Number(root.dataset.currentStep);

                if (target <= current || root.dataset.errorSteps.includes(String(target))) {
                    fichaClearError(root);
                    fichaSetStep(root, target);
                }
            });
        });

        form.addEventListener('submit', (event) => {
            fichaSyncBirthFields(root);
            fichaSyncGuardianName(form);

            const submitter = event.submitter;
            const isComplete = submitter?.name === 'complete';

            if (!isComplete) {
                return;
            }

            const total = Number(root.dataset.totalSteps ?? '9');
            const failed = [];

            for (let step = 1; step <= total; step += 1) {
                const error = fichaValidateStep(root, step);
                if (error) {
                    failed.push({ step, error });
                }
            }

            if (failed.length) {
                event.preventDefault();
                root.dataset.errorSteps = failed.map((item) => item.step).join(',');
                fichaShowError(root, failed[0].error);
                fichaSetStep(root, failed[0].step);
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFichaWizard);
} else {
    initFichaWizard();
}
