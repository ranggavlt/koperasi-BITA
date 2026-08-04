const normalize = (value) => String(value || '').toLocaleLowerCase('id-ID').trim();

const initializeMemberFilter = (form) => {
    const companySelect = form.querySelector('[data-company-select]');
    const memberSelect = form.querySelector('[data-member-select]');
    const searchInput = form.querySelector('[data-member-search]');

    if (!memberSelect) return;

    const memberOptions = Array.from(memberSelect.options).filter((option) => option.value !== '');

    const refreshOptions = () => {
        const companyId = companySelect?.value || '';
        const search = normalize(searchInput?.value);

        memberOptions.forEach((option) => {
            const matchesCompany = companyId === '' || option.dataset.companyId === companyId;
            const matchesSearch = search === '' || normalize(option.textContent).includes(search);
            const isVisible = matchesCompany && matchesSearch;

            option.hidden = !isVisible;
            option.disabled = !isVisible;
        });
    };

    companySelect?.addEventListener('change', () => {
        const selectedOption = memberSelect.selectedOptions[0];
        const companyId = companySelect.value;

        if (selectedOption?.value && companyId && selectedOption.dataset.companyId !== companyId) {
            memberSelect.value = '';
        }

        if (searchInput) searchInput.value = '';
        refreshOptions();
    });

    searchInput?.addEventListener('input', refreshOptions);
    refreshOptions();
};

document.querySelectorAll('[data-member-filter]').forEach(initializeMemberFilter);

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm || 'Lanjutkan aksi ini?')) {
            event.preventDefault();
        }
    });
});
