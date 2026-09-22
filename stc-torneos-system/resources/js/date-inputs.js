/**
 * Limita años a 4 dígitos en inputs date y en birth_year.
 * Chrome usa el largo del año de max/min para el campo del año.
 */

const DATE_MIN = '1900-01-01';
const DATE_MAX = '2100-12-31';
const BIRTH_MIN = '1990-01-01';

function todayIso() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

function isBirthDateField(input) {
    const name = String(input.getAttribute('name') || '').toLowerCase();

    return name.includes('birth_date') || input.hasAttribute('data-birth-date');
}

function applyDateBounds(input) {
    if (!(input instanceof HTMLInputElement) || input.type !== 'date') {
        return;
    }

    const birth = isBirthDateField(input);
    if (!input.getAttribute('min')) {
        input.setAttribute('min', birth ? BIRTH_MIN : DATE_MIN);
    }
    if (!input.getAttribute('max')) {
        input.setAttribute('max', birth ? todayIso() : DATE_MAX);
    }

    const value = String(input.value || '');
    if (value !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        input.value = '';
    }
}

function maskBirthYear(value) {
    const cleaned = String(value ?? '').replace(/[^\d/]/g, '');
    const parts = cleaned.split('/');
    const first = (parts[0] || '').replace(/\D/g, '').slice(0, 4);

    if (parts.length === 1) {
        return first;
    }

    const second = (parts.slice(1).join('').replace(/\D/g, '')).slice(0, 4);

    return `${first}/${second}`;
}

function applyBirthYearField(input) {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('maxlength', '9');
    input.setAttribute('pattern', '\\d{4}(/\\d{4})?');
    input.setAttribute('title', 'Año de 4 dígitos (ej. 2012) o rango 2013/2014');

    if (input.dataset.yearMasked === '1') {
        return;
    }

    input.dataset.yearMasked = '1';
    input.addEventListener('input', () => {
        const next = maskBirthYear(input.value);
        if (input.value !== next) {
            input.value = next;
        }
    });
    input.addEventListener('blur', () => {
        const next = maskBirthYear(input.value);
        input.value = next;
        if (next !== '' && !/^\d{4}(\/\d{4})?$/.test(next)) {
            input.setCustomValidity('Usá un año de 4 dígitos (ej. 2012) o un rango 2013/2014.');
        } else {
            input.setCustomValidity('');
        }
    });
}

export function initDateInputs(root = document) {
    root.querySelectorAll('input[type="date"]').forEach(applyDateBounds);
    root.querySelectorAll('input[name="birth_year"]').forEach(applyBirthYearField);
}

document.addEventListener('DOMContentLoaded', () => {
    initDateInputs();
});

document.addEventListener('focusin', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    if (target.type === 'date') {
        applyDateBounds(target);
    }

    if (target.name === 'birth_year') {
        applyBirthYearField(target);
    }
});

document.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.type === 'date') {
        applyDateBounds(target);
    }
});
