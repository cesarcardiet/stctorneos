const GROUP_FORMATS = new Set([
    'Grupos y finales',
    'Grupos, semifinales y final',
    'Interzonales',
    'Grupos y llaves',
    'Copa Oro',
    'Copa Plata',
    'Copa Bronce',
    'Copa Amistad',
    'Formato personalizado',
]);

function syncCompetitionFormatFields(root) {
    const select = root.querySelector('[data-competition-format-select]');
    if (!select) {
        return;
    }

    const customWrap = root.querySelector('[data-competition-format-custom]');
    const customInput = root.querySelector('[name="custom_competition_format"]');
    const groupsWrap = root.querySelector('[data-competition-format-groups]');
    const groupsInput = root.querySelector('[name="groups_count"]');
    const teamsPerGroupInput = root.querySelector('[name="teams_per_group"]');
    const format = String(select.value || '').trim();
    const needsCustom = format === 'Formato personalizado';
    const needsGroups = GROUP_FORMATS.has(format);

    if (customWrap) {
        customWrap.hidden = !needsCustom;
    }
    if (customInput) {
        customInput.required = needsCustom;
        if (!needsCustom) {
            customInput.setCustomValidity('');
        }
    }

    if (groupsWrap) {
        groupsWrap.hidden = !needsGroups;
    }
    if (groupsInput) {
        groupsInput.required = needsGroups;
        if (!needsGroups) {
            groupsInput.value = '0';
            groupsInput.setCustomValidity('');
        } else if (Number(groupsInput.value) <= 0) {
            groupsInput.value = groupsInput.dataset.defaultValue || '2';
        }
    }
    if (teamsPerGroupInput && !needsGroups) {
        teamsPerGroupInput.value = '0';
    } else if (teamsPerGroupInput && needsGroups && Number(teamsPerGroupInput.value) <= 0) {
        teamsPerGroupInput.value = teamsPerGroupInput.dataset.defaultValue || '4';
    }
}

function bindCompetitionFormatFields() {
    document.querySelectorAll('[data-competition-format-root]').forEach((root) => {
        const select = root.querySelector('[data-competition-format-select]');
        if (!select || select.dataset.formatBound === '1') {
            return;
        }

        select.dataset.formatBound = '1';
        select.addEventListener('change', () => syncCompetitionFormatFields(root));
        syncCompetitionFormatFields(root);
    });
}

document.addEventListener('DOMContentLoaded', bindCompetitionFormatFields);

export { bindCompetitionFormatFields, syncCompetitionFormatFields };
