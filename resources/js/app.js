import './bootstrap';

const closeAllCustomSelects = (except = null) => {
    document.querySelectorAll('[data-custom-select-wrapper].is-open').forEach((wrapper) => {
        if (wrapper !== except) {
            wrapper.classList.remove('is-open');
            wrapper.querySelector('[data-custom-select-trigger]')?.setAttribute('aria-expanded', 'false');
        }
    });
};

const optionLabel = (option) => option?.textContent?.trim() || 'Selecione';

const customSelectId = (select) => {
    if (! select.dataset.customSelectId) {
        select.dataset.customSelectId = `custom-select-${Math.random().toString(36).slice(2)}`;
    }

    return select.dataset.customSelectId;
};

const customSelectWrapper = (select) => {
    const id = customSelectId(select);

    return document.querySelector(`[data-custom-select-for="${id}"]`);
};

const renderCustomSelect = (select) => {
    const wrapper = customSelectWrapper(select);

    if (! wrapper) {
        return;
    }

    const trigger = wrapper.querySelector('[data-custom-select-trigger]');
    const valueLabel = wrapper.querySelector('[data-custom-select-value]');
    const menu = wrapper.querySelector('[data-custom-select-menu]');
    const selected = select.selectedOptions[0] || select.options[0];

    valueLabel.textContent = optionLabel(selected);
    trigger.classList.toggle('is-placeholder', ! selected?.value);
    menu.innerHTML = '';

    Array.from(select.options).forEach((option, index) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'custom-select-option';
        item.dataset.value = option.value;
        item.dataset.index = String(index);
        item.role = 'option';
        item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
        item.disabled = option.disabled;
        item.innerHTML = `
            <span class="custom-select-option-text">${optionLabel(option)}</span>
            <span class="custom-select-check" aria-hidden="true"></span>
        `;

        if (option.selected) {
            item.classList.add('is-selected');
        }

        if (! option.value) {
            item.classList.add('is-placeholder');
        }

        item.addEventListener('click', () => {
            if (option.disabled) {
                return;
            }

            select.value = option.value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            renderCustomSelect(select);
            closeAllCustomSelects();
            trigger.focus();
        });

        menu.appendChild(item);
    });
};

const enhanceSelect = (select) => {
    if (select.multiple || select.dataset.nativeSelect === 'true') {
        return;
    }

    if (customSelectWrapper(select)) {
        renderCustomSelect(select);
        return;
    }

    const id = customSelectId(select);
    const wrapper = document.createElement('div');
    wrapper.className = 'custom-select';
    wrapper.dataset.customSelectWrapper = 'true';
    wrapper.dataset.customSelectFor = id;

    if (select.classList.contains('mt-1')) {
        wrapper.classList.add('mt-1');
    }

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'custom-select-trigger';
    trigger.dataset.customSelectTrigger = 'true';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.innerHTML = `
        <span class="custom-select-value" data-custom-select-value></span>
        <span class="custom-select-icon" aria-hidden="true"></span>
    `;

    const menu = document.createElement('div');
    menu.className = 'custom-select-menu';
    menu.dataset.customSelectMenu = 'true';
    menu.role = 'listbox';

    wrapper.appendChild(trigger);
    wrapper.appendChild(menu);
    select.after(wrapper);
    select.dataset.customSelectEnhanced = 'true';
    select.classList.add('custom-select-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    trigger.addEventListener('click', () => {
        const willOpen = ! wrapper.classList.contains('is-open');

        closeAllCustomSelects(wrapper);
        wrapper.classList.toggle('is-open', willOpen);
        trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        if (willOpen) {
            const selectedItem = menu.querySelector('.is-selected') || menu.querySelector('.custom-select-option:not(:disabled)');
            selectedItem?.scrollIntoView({ block: 'nearest' });
        }
    });

    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            (menu.querySelector('.is-selected') || menu.querySelector('.custom-select-option:not(:disabled)'))?.focus();
        }
    });

    menu.addEventListener('keydown', (event) => {
        const options = Array.from(menu.querySelectorAll('.custom-select-option:not(:disabled)'));
        const currentIndex = options.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            closeAllCustomSelects();
            trigger.focus();
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            options[Math.min(currentIndex + 1, options.length - 1)]?.focus();
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            options[Math.max(currentIndex - 1, 0)]?.focus();
        }
    });

    select.addEventListener('change', () => renderCustomSelect(select));

    new MutationObserver(() => renderCustomSelect(select)).observe(select, {
        attributes: true,
        childList: true,
        subtree: true,
    });

    renderCustomSelect(select);
};

const enhanceCustomSelects = () => {
    document.querySelectorAll('select:not([data-native-select="true"]):not([data-custom-select-enhanced="true"])').forEach(enhanceSelect);
};

const refreshCustomSelects = () => {
    document.querySelectorAll('select[data-custom-select-enhanced="true"]').forEach(renderCustomSelect);
    enhanceCustomSelects();
};

document.addEventListener('click', (event) => {
    if (! event.target.closest('[data-custom-select-wrapper]')) {
        closeAllCustomSelects();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllCustomSelects();
    }
});

document.addEventListener('DOMContentLoaded', enhanceCustomSelects);
document.addEventListener('livewire:navigated', refreshCustomSelects);
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', refreshCustomSelects);
});

new MutationObserver(enhanceCustomSelects).observe(document.documentElement, {
    childList: true,
    subtree: true,
});
