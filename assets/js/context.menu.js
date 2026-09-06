/**
 * Context Menu Manager — reusable right-click menu component.
 *
 * Windows-style context menu, fully config-driven so any section of the site
 * can register its own menu for a CSS selector:
 *
 *   ContextMenuManager.register('.tm-task', [
 *       { id: 'edit', label: 'Edit', action: () => openEditDialog(el) },
 *       { separator: true },
 *       { id: 'priority', label: 'Change priority', submenu: [
 *           { id: 'p0', label: 'Lowest', action: () => setPriority(el, 0) },
 *           ...
 *       ]},
 *       { id: 'delete', label: 'Delete task', danger: true, action: ... },
 *   ]);
 *
 * Item descriptor fields:
 *   - id        (string)  unique id within the menu (for debugging/aria)
 *   - label     (string)  display text
 *   - icon      (string)  optional unicode glyph (or inline text), e.g. '↻'
 *   - danger    (bool)    render in the danger (red) style
 *   - disabled  (bool)    render as non-interactive
 *   - submenu   (array)   nested items; opens as a flyout on hover/click
 *   - action    (fn)      called on click with (targetElement, event)
 *   - separator (bool)    render a divider instead of an item
 *
 * Behaviour: closes on outside click, Escape, scroll, resize, or item
 * activation. Position flips near viewport edges. Keyboard: Escape closes;
 * arrow keys move between items, Enter/Space activate, Right opens submenu,
 * Left/Escape closes it.
 *
 * Loaded on every page (after http.js, before core.js) so any module can
 * register menus during its own init.
 */
const ContextMenuManager = (() => {
    /** @type {Array<{selector: string, items: Array|Function}>} */
    const registrations = [];

    /** @type {HTMLElement|null} the root menu currently shown */
    let menuEl = null;
    /** @type {Array<{el: HTMLElement, parent: HTMLElement|null}>} open submenus, deepest last */
    const submenuStack = [];
    /** @type {HTMLElement|null} element the menu was opened for */
    let targetEl = null;

    const MENU_CLASS = 'context-menu';

    /**
     * Register a context menu for elements matching `selector`.
     * `items` is an array of item descriptors, or a function
     * (targetElement) => array evaluated at open time.
     */
    function register(selector, items) {
        registrations.push({ selector, items });
    }

    function buildMenuItem(item, isSubmenu) {
        if (item.separator) {
            const sep = document.createElement('div');
            sep.className = 'context-menu-separator';
            return sep;
        }

        const el = document.createElement('div');
        el.className = 'context-menu-item';
        el.setAttribute('role', 'menuitem');
        el.setAttribute('tabindex', '-1');
        if (item.id) el.dataset.menuItemId = item.id;
        if (item.danger) el.classList.add('context-menu-danger');
        if (item.disabled) el.classList.add('context-menu-disabled');

        const icon = document.createElement('span');
        icon.className = 'context-menu-icon';
        if (item.icon) {
            // Icon is a unicode glyph — render it as text, not a class name.
            icon.textContent = item.icon;
        }
        el.appendChild(icon);

        const label = document.createElement('span');
        label.className = 'context-menu-label';
        label.textContent = item.label;
        el.appendChild(label);

        if (item.submenu && item.submenu.length) {
            el.classList.add('context-menu-has-submenu');
            const arrow = document.createElement('span');
            arrow.className = 'context-menu-arrow';
            arrow.innerHTML = '&#9656;'; // ▸
            el.appendChild(arrow);
        }

        return el;
    }

    /**
     * Render a menu element from item descriptors and wire item interactions.
     * @param {Array} items
     * @param {string} extraClass
     * @returns {HTMLElement}
     */
    function renderMenu(items, extraClass) {
        const menu = document.createElement('div');
        menu.className = MENU_CLASS + (extraClass ? ' ' + extraClass : '');
        menu.setAttribute('role', 'menu');

        const itemEls = [];
        items.forEach(item => {
            const el = buildMenuItem(item);
            menu.appendChild(el);
            itemEls.push({ el, item });
        });

        let hoverTimer = null;
        itemEls.forEach(({ el, item }) => {
            if (item.separator) return;

            if (item.submenu && item.submenu.length) {
                el.addEventListener('mouseenter', () => {
                    clearTimeout(hoverTimer);
                    hoverTimer = setTimeout(() => openSubmenu(el, item.submenu), 120);
                });
                el.addEventListener('mouseleave', () => {
                    hoverTimer = setTimeout(() => closeSubmenusAbove(el), 250);
                });
                el.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openSubmenu(el, item.submenu);
                });
            } else if (!item.disabled && item.action) {
                el.addEventListener('click', (event) => {
                    event.stopPropagation();
                    // Capture the target BEFORE close() — close() nulls
                    // targetEl, so passing it after would hand every action
                    // a null element.
                    const actionTarget = targetEl;
                    close();
                    item.action(actionTarget, event);
                });
            }
        });

        return menu;
    }

    /**
     * Depth of an element within the open submenu stack (0 = root menu).
     */
    function depthOf(el) {
        for (let i = 0; i < submenuStack.length; i++) {
            if (submenuStack[i].parent === el) return i + 1;
        }
        return 0;
    }

    /**
     * Close all submenus that are deeper than (i.e. not ancestors of) `el`.
     */
    function closeSubmenusAbove(el) {
        const keepDepth = depthOf(el);
        while (submenuStack.length > keepDepth) {
            submenuStack.pop().el.remove();
        }
    }

    /**
     * Open a submenu anchored to the right of `parentItem`.
     */
    function openSubmenu(parentItem, items) {
        // If this submenu is already open, do nothing.
        if (submenuStack.some(s => s.parent === parentItem)) return;
        closeSubmenusAbove(parentItem);

        const sub = renderMenu(items, 'context-menu-submenu');
        document.body.appendChild(sub);
        const rect = parentItem.getBoundingClientRect();
        positionMenu(sub, rect.right - 4, rect.top);
        submenuStack.push({ el: sub, parent: parentItem });
    }

    /**
     * Position a menu at viewport coords, flipping near edges.
     */
    function positionMenu(menu, x, y) {
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';

        const rect = menu.getBoundingClientRect();
        let left = x;
        let top = y;

        if (x + rect.width > window.innerWidth - 8) {
            left = Math.max(8, x - rect.width);
        }
        if (y + rect.height > window.innerHeight - 8) {
            top = Math.max(8, window.innerHeight - rect.height - 8);
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
        menu.style.visibility = '';
        menu.classList.add('context-menu-open');
    }

    /**
     * Open the context menu for `element` at viewport coords.
     * @returns {boolean} true if a menu was opened
     */
    function open(element, x, y, itemsOverride) {
        close();

        targetEl = element;
        const items = itemsOverride !== undefined
            ? (typeof itemsOverride === 'function' ? itemsOverride(element) : itemsOverride)
            : (() => {
                const reg = registrations.find(r => element.closest(r.selector));
                if (!reg) return null;
                targetEl = element.closest(reg.selector);
                return typeof reg.items === 'function' ? reg.items(targetEl) : reg.items;
            })();

        if (!items || !items.length) return false;

        menuEl = renderMenu(items);
        document.body.appendChild(menuEl);
        positionMenu(menuEl, x, y);
        return true;
    }

    function close() {
        if (menuEl) {
            menuEl.remove();
            menuEl = null;
        }
        while (submenuStack.length) {
            submenuStack.pop().el.remove();
        }
        targetEl = null;
    }

    function isOpen() {
        return menuEl !== null;
    }

    function handleContextMenu(event) {
        const reg = registrations.find(r => event.target.closest(r.selector));
        if (!reg) return;

        event.preventDefault();
        open(event.target, event.clientX, event.clientY);
    }

    /**
     * Left-click trigger handlers, added via registerTrigger().
     * The menu opens anchored to the clicked element (dropdown-style).
     */
    const clickTriggers = [];

    /**
     * Register a left-click dropdown for elements matching `selector`.
     * Uses the same rendering/submenu engine as right-click menus —
     * the menu opens anchored below the clicked element.
     *
     * @param {string} selector
     * @param {Array|Function} items array of item descriptors, or (targetEl) => array
     */
    function registerTrigger(selector, items) {
        clickTriggers.push({ selector, items });
    }

    function handleClickTrigger(event) {
        const trigger = clickTriggers.find(t => event.target.closest(t.selector));
        if (!trigger) return;

        // Toggle: clicking the trigger again closes the open menu.
        const triggerEl = event.target.closest(trigger.selector);
        if (menuEl && triggerEl === targetEl) {
            close();
            return;
        }

        event.preventDefault();
        // stopImmediatePropagation: handleGlobalClose is also bound to
        // document click — without this it would instantly close the menu
        // we just opened (both listeners share the same target).
        event.stopImmediatePropagation();

        const items = typeof trigger.items === 'function' ? trigger.items(triggerEl) : trigger.items;
        const rect = triggerEl.getBoundingClientRect();
        open(triggerEl, rect.left, rect.bottom + 4, items);
    }

    function handleGlobalClose(event) {
        if (!menuEl) return;

        if (event.type === 'keydown') {
            if (event.key === 'Escape') close();
            return;
        }

        // Clicks inside the menu (root or any submenu) are handled by item listeners.
        const inside = (menuEl && menuEl.contains(event.target)) ||
            submenuStack.some(s => s.el.contains(event.target));
        if (!inside) close();
    }

    function init() {
        document.addEventListener('contextmenu', handleContextMenu);
        document.addEventListener('click', handleClickTrigger);
        document.addEventListener('click', handleGlobalClose);
        document.addEventListener('keydown', handleGlobalClose);
        window.addEventListener('scroll', close, true);
        window.addEventListener('resize', close);
        window.addEventListener('blur', close);
    }

    return { register, registerTrigger, open, close, isOpen, init };
})();