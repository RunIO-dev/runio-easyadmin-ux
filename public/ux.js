(() => {
    const collectCommands = () => {
        const seen = new Set();
        const commands = [];

        document.querySelectorAll('.ea-sidebar a[href]').forEach((link) => {
            const href = link.getAttribute('href');
            const label = link.textContent.replace(/\s+/g, ' ').trim();
            if (!label || !href || href.startsWith('#') || seen.has(href)) {
                return;
            }

            seen.add(href);
            const group = link.closest('.ea-sidebar-group')?.querySelector('.ea-sidebar-group-label');
            commands.push({ label, href, group: group?.textContent.trim() ?? '' });
        });

        return commands;
    };

    const createPalette = (commands, strings) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'ux-palette';
        dialog.setAttribute('aria-label', strings.label);

        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = strings.placeholder;
        input.autocomplete = 'off';
        input.setAttribute('aria-label', strings.placeholder);
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', 'ux-palette-results');

        const list = document.createElement('ul');
        list.id = 'ux-palette-results';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', strings.label);

        const status = document.createElement('p');
        status.className = 'ux-palette-empty';
        status.setAttribute('role', 'status');

        dialog.append(input, list, status);
        document.body.append(dialog);

        let matches = commands;
        let selected = 0;

        const render = () => {
            list.replaceChildren(
                ...matches.map((command, index) => {
                    const item = document.createElement('li');
                    item.id = `ux-palette-option-${index}`;
                    item.setAttribute('role', 'option');
                    item.setAttribute('aria-selected', String(index === selected));

                    const link = document.createElement('a');
                    link.href = command.href;
                    link.tabIndex = -1;
                    link.textContent = command.label;

                    if (command.group) {
                        const group = document.createElement('small');
                        group.textContent = command.group;
                        link.append(group);
                    }

                    item.append(link);
                    return item;
                })
            );

            const hasMatches = matches.length > 0;
            list.hidden = !hasMatches;
            status.textContent = hasMatches ? '' : strings.empty;
            input.setAttribute('aria-expanded', String(hasMatches));

            if (hasMatches) {
                input.setAttribute('aria-activedescendant', `ux-palette-option-${selected}`);
                list.children[selected]?.scrollIntoView({ block: 'nearest' });
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        };

        input.addEventListener('input', () => {
            const query = input.value.trim().toLocaleLowerCase();
            matches = commands.filter((command) =>
                `${command.group} ${command.label}`.toLocaleLowerCase().includes(query)
            );
            selected = 0;
            render();
        });

        input.addEventListener('keydown', (event) => {
            if ('ArrowDown' === event.key || 'ArrowUp' === event.key) {
                event.preventDefault();
                if (matches.length > 0) {
                    const step = 'ArrowDown' === event.key ? 1 : -1;
                    selected = (selected + step + matches.length) % matches.length;
                    render();
                }
            } else if ('Enter' === event.key && matches[selected]) {
                event.preventDefault();
                window.location.assign(matches[selected].href);
            }
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        return {
            isOpen: () => dialog.open,
            open() {
                input.value = '';
                matches = commands;
                selected = 0;
                render();
                dialog.showModal();
                input.focus();
            },
            close: () => dialog.close(),
        };
    };

    // EasyAdmin expands the submenu of the current page, which suits a sidebar but would leave
    // a flyout covering the content in the top navigation.
    const setUpTopbarSubmenus = () => {
        if ('topbar' !== document.body.dataset.uxLayout) {
            return;
        }

        const collapse = (except = null) => {
            document.querySelectorAll('.ea-sidebar-item.has-submenu.is-expanded').forEach((item) => {
                if (item !== except) {
                    item.classList.remove('is-expanded');
                    item.querySelector(':scope > .ea-sidebar-item-link')?.setAttribute('aria-expanded', 'false');
                }
            });
        };

        collapse();
        document.addEventListener('click', (event) => {
            collapse(event.target.closest('.ea-sidebar-item.has-submenu'));
        });
        document.addEventListener('keydown', (event) => {
            if ('Escape' === event.key) {
                collapse();
                if (document.activeElement?.closest('.ea-sidebar-item.has-submenu')) {
                    document.activeElement.blur();
                }
            }
        });
    };

    const setUpPalette = () => {
        const trigger = document.querySelector('.content-top [data-ux-palette-open]');
        const commands = collectCommands();
        if (!trigger || 0 === commands.length) {
            document.querySelectorAll('[data-ux-palette-open]').forEach((button) => button.remove());
            return;
        }

        const palette = createPalette(commands, {
            label: trigger.dataset.uxLabel,
            placeholder: trigger.dataset.uxPlaceholder,
            empty: trigger.dataset.uxEmpty,
        });

        // Text fields keep Ctrl+K: rich text editors use it to insert links, and macOS to cut a line.
        const isEditing = (target) =>
            target instanceof Element && null !== target.closest('input, textarea, select, [contenteditable], trix-editor');

        document.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey) || event.altKey || 'k' !== event.key?.toLowerCase()) {
                return;
            }

            if (palette.isOpen()) {
                event.preventDefault();
                palette.close();
            } else if (!isEditing(event.target)) {
                event.preventDefault();
                palette.open();
            }
        });

        trigger.addEventListener('click', () => palette.open());
    };

    const setUpColumnEditor = (list) => {
        let dragged = null;

        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-ux-move]');
            if (!button) {
                return;
            }

            event.preventDefault();
            const row = button.closest('li');
            if ('up' === button.dataset.uxMove) {
                row.previousElementSibling?.before(row);
            } else {
                row.nextElementSibling?.after(row);
            }
            button.focus();
        });

        // A table always keeps at least one visible column.
        list.addEventListener('change', (event) => {
            if (event.target.matches('input[type=checkbox]') && !list.querySelector('input[type=checkbox]:checked')) {
                event.target.checked = true;
            }
        });

        list.addEventListener('dragstart', (event) => {
            dragged = event.target.closest('li');
            if (dragged) {
                dragged.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', dragged.dataset.column);
            }
        });

        list.addEventListener('dragover', (event) => {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            const over = event.target.closest('li');
            if (!over || over === dragged) {
                return;
            }

            const rect = over.getBoundingClientRect();
            if (event.clientY > rect.top + rect.height / 2) {
                over.after(dragged);
            } else {
                over.before(dragged);
            }
        });

        list.addEventListener('drop', (event) => event.preventDefault());
        list.addEventListener('dragend', () => {
            dragged?.classList.remove('is-dragging');
            dragged = null;
        });
    };

    const setUpBatchSelection = (table) => {
        const batchActions = document.querySelector('.batch-actions');
        const selectAll = document.querySelector('.form-batch-checkbox-all');
        if (!batchActions || !selectAll) {
            return;
        }

        const count = document.createElement('span');
        count.className = 'ux-selection-count';
        count.setAttribute('role', 'status');

        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'btn btn-secondary ux-selection-clear';
        clear.textContent = table.dataset.uxClear;
        clear.addEventListener('click', () => {
            selectAll.checked = false;
            selectAll.dispatchEvent(new Event('change'));
        });

        batchActions.prepend(count, clear);

        // EasyAdmin dispatches non-bubbling change events, which only a capturing listener sees.
        document.addEventListener(
            'change',
            (event) => {
                if (event.target.matches?.('.form-batch-checkbox')) {
                    const selected = document.querySelectorAll('.form-batch-checkbox:checked').length;
                    count.textContent = selected > 0 ? table.dataset.uxSelected.replace('%count%', selected) : '';
                }
            },
            true
        );

        // Browsers can restore checked boxes on Back without firing change events, which would
        // leave both EasyAdmin's batch bar and this count out of sync with the selection.
        window.addEventListener('pageshow', () => {
            setTimeout(() => {
                document.querySelectorAll('.form-batch-checkbox:checked').forEach((checkbox) => {
                    checkbox.dispatchEvent(new Event('change'));
                });
            });
        });
    };

    const EDITABLE_INPUTS = new Set(['text', 'number', 'email', 'url', 'tel', 'date', 'time', 'datetime-local']);

    const parseHtml = (html) => new DOMParser().parseFromString(html, 'text/html');

    const findEditForm = (doc) => doc.querySelector('form[id^="edit-"]');

    // Only a direct property of the entity maps to one cell, so nested form fields are excluded.
    const findControl = (form, column) =>
        [...form.elements].find((element) => element.name && element.name.slice(element.name.indexOf('[')) === `[${column}]`);

    const canEditInCell = (control) => {
        if (!control || control.disabled || control.readOnly) {
            return false;
        }
        if ('SELECT' === control.tagName) {
            return !control.multiple && !control.hasAttribute('data-ea-autocomplete-endpoint-url');
        }
        return 'INPUT' === control.tagName && EDITABLE_INPUTS.has(control.type);
    };

    const fetchEditForm = async (url) => {
        const response = await fetch(url, { credentials: 'same-origin' });
        const form = response.ok ? findEditForm(parseHtml(await response.text())) : null;
        if (!form) {
            throw new Error('edit form unavailable');
        }
        return form;
    };

    const buildCellControl = (source) => {
        const control = source.cloneNode('SELECT' === source.tagName);
        control.removeAttribute('id');
        control.removeAttribute('name');
        [...control.attributes]
            .filter((attribute) => attribute.name.startsWith('data-'))
            .forEach((attribute) => control.removeAttribute(attribute.name));
        control.className = 'SELECT' === source.tagName ? 'form-select form-select-sm' : 'form-control form-control-sm';
        control.value = source.value;
        return control;
    };

    const setUpDrawer = (table) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'ux-drawer';
        dialog.setAttribute('aria-label', table.dataset.uxDrawerTitle);

        const header = document.createElement('header');
        const title = document.createElement('strong');
        title.textContent = table.dataset.uxDrawerTitle;
        const fullPage = document.createElement('a');
        fullPage.textContent = table.dataset.uxFullPage;
        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';
        close.setAttribute('aria-label', table.dataset.uxClose);
        header.append(title, fullPage, close);

        let frame = null;
        dialog.append(header);
        document.body.append(dialog);

        let editUrl = null;
        let snapshot = null;

        const serialize = () => {
            const form = frame.contentDocument ? findEditForm(frame.contentDocument) : null;
            if (!form) {
                return null;
            }
            return JSON.stringify(
                [...new FormData(form)].map(([name, value]) => [name, 'string' === typeof value ? value : `${value.name}:${value.size}`])
            );
        };

        const hide = () => {
            const current = serialize();
            if (null !== snapshot && null !== current && current !== snapshot && !window.confirm(table.dataset.uxDiscard)) {
                return;
            }
            snapshot = null;
            editUrl = null;
            dialog.close();
            // A fresh frame drops the closed form without navigating it, which would trigger the
            // edit page's own unsaved-changes prompt a second time.
            frame.replaceWith(createFrame());
        };

        const onLoad = () => {
            if (!editUrl) {
                return;
            }

            let location;
            try {
                location = new URL(frame.contentWindow.location.href);
                if ('about:' === location.protocol) {
                    return;
                }
                if (location.origin !== window.location.origin) {
                    throw new Error('not embeddable');
                }
            } catch {
                // The application forbids framing this page, so use the normal edit page.
                window.location.assign(editUrl);
                return;
            }

            // EasyAdmin answers a successful save with a redirect and a failed one with the form.
            // The URL alone can't tell them apart because its redirects keep the drawer parameter.
            const [navigation] = frame.contentWindow.performance.getEntriesByType('navigation');
            if (navigation?.redirectCount > 0 || location.pathname !== new URL(editUrl).pathname) {
                snapshot = null;
                window.location.reload();
                return;
            }

            // Only the first load is the baseline: after a failed save the form holds unsaved input.
            snapshot ??= serialize();
            frame.contentDocument.addEventListener('keydown', (event) => {
                const inWidget = event.target instanceof frame.contentWindow.Element && event.target.closest('.ts-wrapper, .ts-dropdown');
                if ('Escape' === event.key && !event.defaultPrevented && !inWidget && !frame.contentDocument.querySelector('.modal.show, .dropdown-menu.show')) {
                    hide();
                }
            });
        };

        const createFrame = () => {
            frame = document.createElement('iframe');
            frame.title = table.dataset.uxDrawerTitle;
            frame.addEventListener('load', onLoad);
            return frame;
        };
        dialog.append(createFrame());

        close.addEventListener('click', hide);
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            hide();
        });

        return (url) => {
            const target = new URL(url, window.location.href);
            editUrl = target.href;
            fullPage.href = target.href;
            target.searchParams.set('ux_drawer', '1');
            snapshot = null;
            frame.contentWindow.location.replace(target.href);
            dialog.showModal();
            close.focus();
        };
    };

    const setUpCellEditing = (table, openDrawer) => {
        let active = null;
        let latestRequest = 0;

        const addButton = (cell, editUrl) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ux-cell-edit';
            button.textContent = '✎';
            button.setAttribute('aria-label', `${table.dataset.uxEdit}: ${cell.dataset.label}`);
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                startEditing(cell, editUrl, button);
            });
            cell.append(button);
        };

        const stopEditing = (restoreFocus = true) => {
            if (!active) {
                return;
            }
            const { cell, editor, trigger } = active;
            active = null;
            editor.remove();
            cell.classList.remove('is-editing');
            if (restoreFocus && trigger.isConnected) {
                trigger.focus();
            }
        };

        const refreshCell = async (cell, editUrl) => {
            const response = await fetch(window.location.href, { credentials: 'same-origin' });
            const doc = parseHtml(await response.text());
            const rowId = CSS.escape(cell.closest('tr').dataset.id);
            const fresh = doc.querySelector(`tr[data-id="${rowId}"] td[data-column="${CSS.escape(cell.dataset.column)}"]`);
            if (!response.ok || !fresh) {
                // The new value may move the row out of this page, sort, or filter.
                window.location.reload();
                return;
            }
            cell.replaceChildren(...fresh.childNodes);
            addButton(cell, editUrl);
            cell.querySelector('.ux-cell-edit').focus();
        };

        const save = async (state) => {
            const { cell, editUrl, control, message, submit } = state;
            message.textContent = '';
            submit.disabled = true;

            try {
                const form = await fetchEditForm(editUrl);
                const source = findControl(form, cell.dataset.column);
                if (!canEditInCell(source)) {
                    throw new Error('field no longer editable');
                }
                if (source.value !== state.original) {
                    state.original = source.value;
                    message.textContent = table.dataset.uxConflict;
                    return;
                }

                source.value = control.value;
                const data = new FormData(form);
                const button = [...form.elements].find((element) => 'submit' === element.type && 'saveAndReturn' === element.value);
                if (button) {
                    data.append(button.name, button.value);
                }

                const response = await fetch(editUrl, { method: 'POST', body: data, credentials: 'same-origin' });
                const doc = parseHtml(await response.text());

                if (422 === response.status) {
                    const failed = findEditForm(doc);
                    const field = failed ? findControl(failed, cell.dataset.column) : null;
                    const error = field?.closest('.form-group, .field-group, [class*="field-"]')?.querySelector('.invalid-feedback, .form-error-message');
                    message.textContent = error?.textContent.trim() || table.dataset.uxFailed;
                    return;
                }

                // A redirect to a page without the admin layout means the session ended.
                if (!response.ok || !response.redirected || !doc.body?.hasAttribute('data-ux-layout')) {
                    if (response.ok && response.redirected) {
                        window.location.reload();
                        return;
                    }
                    throw new Error('save failed');
                }

                stopEditing(false);
                await refreshCell(cell, editUrl);
            } catch {
                message.textContent = table.dataset.uxFailed;
            } finally {
                submit.disabled = false;
            }
        };

        const startEditing = async (cell, editUrl, trigger) => {
            stopEditing(false);
            const request = ++latestRequest;
            trigger.disabled = true;

            let source;
            try {
                source = findControl(await fetchEditForm(editUrl), cell.dataset.column);
            } catch {
                source = null;
            } finally {
                trigger.disabled = false;
            }

            // Another cell was opened while this form was loading.
            if (request !== latestRequest) {
                return;
            }

            if (!canEditInCell(source)) {
                openDrawer(editUrl);
                return;
            }

            const editor = document.createElement('form');
            editor.className = 'ux-cell-editor';
            editor.noValidate = false;
            const control = buildCellControl(source);
            control.setAttribute('aria-label', cell.dataset.label);
            const submit = document.createElement('button');
            submit.type = 'submit';
            submit.className = 'btn btn-primary btn-sm';
            submit.textContent = table.dataset.uxSave;
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-secondary btn-sm';
            cancel.textContent = table.dataset.uxCancel;
            const message = document.createElement('p');
            message.className = 'ux-cell-error';
            message.setAttribute('role', 'alert');
            editor.append(control, submit, cancel, message);

            const state = { cell, editUrl, editor, trigger, control, message, submit, original: source.value };
            active = state;

            editor.addEventListener('click', (event) => event.stopPropagation());
            editor.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!submit.disabled) {
                    save(state);
                }
            });
            editor.addEventListener('keydown', (event) => {
                event.stopPropagation();
                if ('Escape' === event.key) {
                    stopEditing();
                }
            });
            cancel.addEventListener('click', () => stopEditing());

            cell.classList.add('is-editing');
            cell.append(editor);

            // Near the table's far edge the editor opens toward the inside instead of being clipped.
            const bounds = (cell.closest('.datagrid-scroll') ?? document.documentElement).getBoundingClientRect();
            const box = editor.getBoundingClientRect();
            if (box.right > bounds.right || box.left < bounds.left) {
                editor.classList.add('is-flipped');
            }
            control.focus();
            control.select?.();
        };

        table.querySelectorAll('tbody tr[data-id]').forEach((row) => {
            const editUrl = row.querySelector('a[data-action-name="edit"][href]')?.href;
            if (!editUrl) {
                return;
            }
            row.querySelectorAll('td[data-column]:not(.has-switch)').forEach((cell) => addButton(cell, editUrl));
        });
    };

    const setUpEditing = (table) => {
        if (!('uxEditing' in table.dataset)) {
            return;
        }

        const openDrawer = setUpDrawer(table);
        setUpCellEditing(table, openDrawer);

        // Mirrors EasyAdmin's own row rules so the drawer opens only where it would have navigated.
        const INTERACTIVE = 'a, button, input, select, textarea, .form-check, .dropdown, .actions, [data-bs-toggle], .btn, .modal, .ux-cell-editor';
        const firstRow = table.querySelector('[data-default-action-url]');
        const rowTrigger = firstRow?.closest('[data-default-action-trigger]')?.dataset.defaultActionTrigger || 'single';

        const isPlainClick = (event) => 0 === event.button && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey;
        const rowEditUrl = (row) => {
            const editUrl = row.querySelector('a[data-action-name="edit"][href]')?.href;
            const defaultUrl = row.dataset.defaultActionUrl;
            // The attribute may be relative, while a link's href property is always absolute.
            return editUrl && defaultUrl && new URL(defaultUrl, window.location.href).href === editUrl ? editUrl : null;
        };
        const isSelectingText = (row) => {
            const selection = window.getSelection();
            return 'single' === rowTrigger && selection && selection.toString().length > 0
                && selection.rangeCount > 0 && row.contains(selection.getRangeAt(0).commonAncestorContainer);
        };
        const intercept = (event, url) => {
            event.preventDefault();
            event.stopPropagation();
            openDrawer(url);
        };

        // These run in the capture phase, before EasyAdmin's handlers navigate to the edit page.
        document.addEventListener('click', (event) => {
            if (isPlainClick(event) && event.target instanceof Element && table.contains(event.target)) {
                const link = event.target.closest('a[data-action-name="edit"][href]');
                if (link) {
                    intercept(event, link.href);
                }
            }
        }, true);

        document.addEventListener('double' === rowTrigger ? 'dblclick' : 'click', (event) => {
            if (!isPlainClick(event) || !(event.target instanceof Element) || !table.contains(event.target)) {
                return;
            }

            const row = event.target.closest('tbody tr[data-id]');
            if (!row || row.classList.contains('selected-row') || event.target.closest(INTERACTIVE) || isSelectingText(row)) {
                return;
            }

            const url = rowEditUrl(row);
            if (url) {
                intercept(event, url);
            }
        }, true);

        document.addEventListener('keydown', (event) => {
            const row = event.target instanceof Element && event.target.matches('tbody tr[data-id]') ? event.target : null;
            if ('Enter' === event.key && row && table.contains(row) && !row.classList.contains('selected-row')) {
                const url = rowEditUrl(row);
                if (url) {
                    intercept(event, url);
                }
            }
        }, true);
    };

    const setUpDashboard = (root) => {
        const form = root.querySelector('.ux-dashboard-toolbar');
        const grid = root.querySelector('.ux-dashboard-grid');
        const customize = root.querySelector('[data-ux-dashboard-customize]');
        const addButton = root.querySelector('[data-ux-dashboard-add]');
        const addMenu = root.querySelector('[data-ux-dashboard-add-menu]');
        const status = root.querySelector('[data-ux-dashboard-status]');
        const fields = root.querySelector('[data-ux-dashboard-fields]');
        const widgets = () => [...grid.querySelectorAll(':scope > .ux-widget')];
        const labelOf = (widget) => widget.querySelector('h2').textContent.trim();
        const announce = (template, values) => {
            // A replacer function keeps "$&" and similar sequences in labels literal.
            status.textContent = Object.entries(values).reduce((text, [key, value]) => text.replace(`%${key}%`, () => String(value)), template);
        };
        const serialize = () => JSON.stringify(widgets().map((widget) => [widget.dataset.widget, widget.dataset.size]));

        let saved = serialize();
        let dragged = null;
        let submitting = false;
        const isDirty = () => root.classList.contains('is-editing') && serialize() !== saved;

        customize.hidden = false;
        customize.addEventListener('click', () => {
            // Firefox can restore an old select value on reload, so the rendered size wins.
            widgets().forEach((widget) => {
                widget.querySelector('[data-ux-widget-size]').value = widget.dataset.size;
            });
            root.classList.add('is-editing');
            widgets().forEach((widget) => { widget.draggable = true; });
            saved = serialize();
            (addButton.disabled ? form.querySelector('[value=save]') : addButton).focus();
        });

        root.querySelector('[data-ux-dashboard-cancel]').addEventListener('click', () => {
            if (!isDirty() || window.confirm(root.dataset.uxDiscard)) {
                submitting = true;
                window.location.reload();
            }
        });

        root.querySelector('[data-ux-dashboard-reset]').addEventListener('click', (event) => {
            if (!window.confirm(root.dataset.uxReset)) {
                event.preventDefault();
            }
        });

        form.addEventListener('submit', () => {
            submitting = true;
            fields.replaceChildren(...widgets().flatMap((widget) => {
                const id = document.createElement('input');
                id.type = 'hidden';
                id.name = 'widgets[]';
                id.value = widget.dataset.widget;
                const size = document.createElement('input');
                size.type = 'hidden';
                size.name = `sizes[${widget.dataset.widget}]`;
                size.value = widget.dataset.size;
                return [id, size];
            }));
        });

        // A page restored from the back-forward cache after a failed save must warn again.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                submitting = false;
            }
        });

        window.addEventListener('beforeunload', (event) => {
            if (!submitting && isDirty()) {
                event.preventDefault();
            }
        });

        const move = (widget, step) => {
            const list = widgets();
            const target = list[list.indexOf(widget) + step];
            if (!target) {
                return;
            }
            if (step < 0) {
                target.before(widget);
            } else {
                target.after(widget);
            }
            announce(root.dataset.uxMoved, { label: labelOf(widget), position: widgets().indexOf(widget) + 1, count: list.length });
        };

        // Menu items are hidden rather than removed: EasyAdmin's dropdown hover handler still
        // looks up the menu of the item under the pointer a moment after a click.
        const hasAddableWidgets = () => null !== addMenu.querySelector('li:not([hidden])');

        const remove = (widget) => {
            let button = addMenu.querySelector(`[data-ux-add="${CSS.escape(widget.dataset.widget)}"]`);
            if (!button) {
                const item = document.createElement('li');
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'dropdown-item';
                button.dataset.uxAdd = widget.dataset.widget;
                button.textContent = labelOf(widget);
                item.append(button);
                addMenu.append(item);
            }
            button.closest('li').hidden = false;
            addButton.disabled = false;

            // Keeps the widget so adding it back restores it, already rendered.
            const template = root.querySelector(`template[data-ux-widget-template="${CSS.escape(widget.dataset.widget)}"]`) ?? document.createElement('template');
            template.dataset.uxWidgetTemplate = widget.dataset.widget;
            template.content.replaceChildren(widget);
            root.append(template);

            announce(root.dataset.uxRemoved, { label: button.textContent });
            addButton.focus();
        };

        const add = (button) => {
            const template = root.querySelector(`template[data-ux-widget-template="${CSS.escape(button.dataset.uxAdd)}"]`);
            const widget = template?.content.querySelector('.ux-widget');
            if (!widget) {
                return;
            }
            widget.draggable = true;
            grid.querySelector('.ux-dashboard-empty').before(widget);
            button.closest('li').hidden = true;
            addButton.disabled = !hasAddableWidgets();
            announce(root.dataset.uxAdded, { label: labelOf(widget) });
            widget.querySelector('[data-ux-widget-size]').focus();
        };

        root.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const widget = target?.closest('.ux-widget');
            if (target?.closest('[data-ux-widget-move]') && widget) {
                move(widget, Number(target.closest('[data-ux-widget-move]').dataset.uxWidgetMove));
                target.closest('button').focus();
            } else if (target?.closest('[data-ux-widget-remove]') && widget) {
                remove(widget);
            } else if (target?.closest('[data-ux-add]')) {
                add(target.closest('[data-ux-add]'));
            }
        });

        root.addEventListener('change', (event) => {
            if (event.target.matches('[data-ux-widget-size]')) {
                event.target.closest('.ux-widget').dataset.size = event.target.value;
            }
        });

        grid.addEventListener('dragstart', (event) => {
            dragged = root.classList.contains('is-editing') ? event.target.closest?.('.ux-widget') : null;
            if (!dragged) {
                event.preventDefault();
                return;
            }
            dragged.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', dragged.dataset.widget);
        });

        grid.addEventListener('dragover', (event) => {
            if (!dragged) {
                return;
            }
            event.preventDefault();
            const over = event.target.closest?.('.ux-widget');
            if (!over || over === dragged) {
                return;
            }
            // Cards flow along the inline axis, which runs right to left in RTL.
            const rect = over.getBoundingClientRect();
            const rtl = 'rtl' === getComputedStyle(grid).direction;
            const pastMiddle = rtl ? event.clientX < rect.left + rect.width / 2 : event.clientX > rect.left + rect.width / 2;
            if (pastMiddle) {
                over.after(dragged);
            } else {
                over.before(dragged);
            }
        });

        grid.addEventListener('drop', (event) => event.preventDefault());
        grid.addEventListener('dragend', () => {
            dragged?.classList.remove('is-dragging');
            dragged = null;
        });
    };

    const setUpSmartTable = () => {
        const table = document.querySelector('.ux-table');
        if (!table) {
            return;
        }

        table.querySelectorAll('.ux-columns').forEach(setUpColumnEditor);
        table.querySelectorAll('form[data-ux-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.uxConfirm)) {
                    event.preventDefault();
                }
            });
        });
        setUpBatchSelection(table);
        setUpEditing(table);
    };

    // The drawer shows one form inside a frame, so the page-level tools stay in the parent.
    if (!document.body.hasAttribute('data-ux-drawer')) {
        setUpTopbarSubmenus();
        setUpPalette();
        setUpSmartTable();
        document.querySelectorAll('.ux-dashboard').forEach(setUpDashboard);
    }
})();
