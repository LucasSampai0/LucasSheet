import './bootstrap';

const closeAllCustomSelects = (except = null) => {
    document.querySelectorAll('[data-custom-select-wrapper].is-open').forEach((wrapper) => {
        if (wrapper !== except) {
            wrapper.classList.remove('is-open');
            wrapper.querySelector('[data-custom-select-trigger]')?.setAttribute('aria-expanded', 'false');
        }
    });
};

const closeAllActionMenus = (except = null) => {
    document.querySelectorAll('[data-action-menu].is-open').forEach((menu) => {
        if (menu !== except) {
            menu.classList.remove('is-open');
            menu.querySelector('[data-action-menu-trigger]')?.setAttribute('aria-expanded', 'false');
        }
    });
};

const positionActionMenu = (menu) => {
    const trigger = menu.querySelector('[data-action-menu-trigger]');
    const panel = menu.querySelector('[data-action-menu-panel]');

    if (! trigger || ! panel || ! menu.classList.contains('is-open')) {
        return;
    }

    const triggerRect = trigger.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const gap = 8;
    const margin = 12;

    const left = Math.min(
        window.innerWidth - panelRect.width - margin,
        Math.max(margin, triggerRect.right - panelRect.width),
    );

    let top = triggerRect.bottom + gap;

    if (top + panelRect.height > window.innerHeight - margin) {
        top = Math.max(margin, triggerRect.top - panelRect.height - gap);
    }

    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
};

const positionOpenActionMenus = () => {
    document.querySelectorAll('[data-action-menu].is-open').forEach(positionActionMenu);
};

const callClosestLivewire = (element, method, ...args) => {
    const component = element.closest('[wire\\:id]');
    const livewire = window.Livewire || window.livewire;

    if (! component || ! livewire) {
        return;
    }

    const instance = livewire.find(component.getAttribute('wire:id'));

    if (typeof instance?.call === 'function') {
        instance.call(method, ...args);
        return;
    }

    if (typeof instance?.$wire?.[method] === 'function') {
        instance.$wire[method](...args);
        return;
    }

    if (typeof instance?.$wire?.call === 'function') {
        instance.$wire.call(method, ...args);
    }
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
    trigger.disabled = select.disabled;
    trigger.classList.toggle('is-placeholder', ! selected?.value);
    wrapper.classList.toggle('is-disabled', select.disabled);

    if (select.disabled) {
        wrapper.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    }

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
        if (select.disabled) {
            return;
        }

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
        if (select.disabled) {
            return;
        }

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

const applyTheme = (theme = localStorage.getItem('theme') || 'dark') => {
    const isDark = theme === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.classList.toggle('light', ! isDark);
    localStorage.setItem('theme', isDark ? 'dark' : 'light');

    document.querySelectorAll('[data-theme-label]').forEach((label) => {
        label.textContent = isDark ? 'Modo escuro' : 'Modo claro';
    });
};

const toggleTheme = () => {
    applyTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
};

const dashboardWidgetsKey = 'lucassheet.hiddenDashboardWidgets';

const getHiddenDashboardWidgets = () => {
    try {
        return new Set(JSON.parse(localStorage.getItem(dashboardWidgetsKey) || '[]'));
    } catch {
        return new Set();
    }
};

const storeHiddenDashboardWidgets = (widgets) => {
    localStorage.setItem(dashboardWidgetsKey, JSON.stringify(Array.from(widgets)));
};

const applyDashboardWidgets = () => {
    const hiddenWidgets = getHiddenDashboardWidgets();
    const controls = document.querySelector('[data-dashboard-hidden-controls]');
    let hasHiddenWidget = false;

    document.querySelectorAll('[data-dashboard-widget]').forEach((widget) => {
        const isHidden = hiddenWidgets.has(widget.dataset.dashboardWidget);
        widget.classList.toggle('is-dashboard-hidden', isHidden);
        hasHiddenWidget = hasHiddenWidget || isHidden;
    });

    document.querySelectorAll('[data-dashboard-restore]').forEach((button) => {
        button.hidden = ! hiddenWidgets.has(button.dataset.dashboardRestore);
    });

    if (controls) {
        controls.hidden = ! hasHiddenWidget;
    }
};

document.addEventListener('click', (event) => {
    const taskSummary = event.target.closest('.work-card-summary');

    if (taskSummary?.closest('[data-task-card]')?.dataset.dragging === 'true') {
        event.preventDefault();
        return;
    }

    if (! event.target.closest('[data-custom-select-wrapper]')) {
        closeAllCustomSelects();
    }

    const actionMenuTrigger = event.target.closest('[data-action-menu-trigger]');
    const actionMenuItem = event.target.closest('[data-action-menu-item]');

    if (actionMenuTrigger) {
        const menu = actionMenuTrigger.closest('[data-action-menu]');
        const willOpen = ! menu.classList.contains('is-open');

        closeAllActionMenus(menu);
        menu.classList.toggle('is-open', willOpen);
        actionMenuTrigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

        if (willOpen) {
            requestAnimationFrame(() => positionActionMenu(menu));
        }
    } else if (! event.target.closest('[data-action-menu]')) {
        closeAllActionMenus();
    }

    if (actionMenuItem) {
        closeAllActionMenus();
    }

    if (event.target.closest('[data-theme-toggle]')) {
        toggleTheme();
    }

    const hideButton = event.target.closest('[data-dashboard-hide]');
    const restoreButton = event.target.closest('[data-dashboard-restore]');

    if (hideButton) {
        const hiddenWidgets = getHiddenDashboardWidgets();
        hiddenWidgets.add(hideButton.dataset.dashboardHide);
        storeHiddenDashboardWidgets(hiddenWidgets);
        applyDashboardWidgets();
    }

    if (restoreButton) {
        const hiddenWidgets = getHiddenDashboardWidgets();
        hiddenWidgets.delete(restoreButton.dataset.dashboardRestore);
        storeHiddenDashboardWidgets(hiddenWidgets);
        applyDashboardWidgets();
    }
});

document.addEventListener('dragstart', (event) => {
    const card = event.target.closest('[data-task-card]');

    if (! card) {
        return;
    }

    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', card.dataset.taskId);
    event.dataTransfer.setData('application/x-task-status', card.dataset.taskStatus || '');
    card.dataset.dragging = 'true';
    card.classList.add('is-dragging');
});

document.addEventListener('dragend', (event) => {
    const card = event.target.closest('[data-task-card]');

    if (card) {
        card.classList.remove('is-dragging');
        setTimeout(() => {
            delete card.dataset.dragging;
        }, 0);
    }

    document.querySelectorAll('[data-task-drop-status].is-drag-over').forEach((column) => {
        column.classList.remove('is-drag-over');
    });
});

document.addEventListener('dragover', (event) => {
    const column = event.target.closest('[data-task-drop-status]');

    if (! column) {
        return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    column.classList.add('is-drag-over');
});

document.addEventListener('dragleave', (event) => {
    const column = event.target.closest('[data-task-drop-status]');

    if (! column || column.contains(event.relatedTarget)) {
        return;
    }

    column.classList.remove('is-drag-over');
});

document.addEventListener('drop', (event) => {
    const column = event.target.closest('[data-task-drop-status]');

    if (! column) {
        return;
    }

    event.preventDefault();
    column.classList.remove('is-drag-over');

    const taskId = event.dataTransfer.getData('text/plain');
    const status = column.dataset.taskDropStatus;
    const currentStatus = event.dataTransfer.getData('application/x-task-status');

    if (! taskId || ! status || status === currentStatus) {
        return;
    }

    callClosestLivewire(column, 'changeStatusFromBoard', Number(taskId), status);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllCustomSelects();
        closeAllActionMenus();
    }
});

window.addEventListener('resize', positionOpenActionMenus);
window.addEventListener('scroll', positionOpenActionMenus, true);

document.addEventListener('DOMContentLoaded', () => {
    applyTheme();
    enhanceCustomSelects();
    applyDashboardWidgets();
});
document.addEventListener('livewire:navigated', () => {
    applyTheme();
    refreshCustomSelects();
    applyDashboardWidgets();
});
document.addEventListener('livewire:init', () => {
    Livewire.hook('morph.updated', () => {
        applyTheme();
        refreshCustomSelects();
        applyDashboardWidgets();
    });
});

new MutationObserver(enhanceCustomSelects).observe(document.documentElement, {
    childList: true,
    subtree: true,
});
