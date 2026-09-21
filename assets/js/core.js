// Core JavaScript File

// HTMX event names come from window.HTMX_EVENTS, emitted server-side in
// views/core/header.php from \Dashboard\Core\HtmxEvents (classes/Core/HtmxEvents.class.php).

// Modal Management System
const ModalManager = (() => {
    let activeModalId = null;

    function handleModalOpen(event) {
        const openModalButton = event.target.closest('.open-modal-btn');
        if (!openModalButton) return;

        // Ignore clicks on interactive elements nested inside an
        // .open-modal-btn (e.g. Pause/Delete inside a clickable schedule card)
        // so both actions don't fire at once.
        const interactive = event.target.closest('button, a, input, select, textarea, form');
        if (interactive && interactive !== openModalButton && openModalButton.contains(interactive)) {
            return;
        }

        const modalSelector = openModalButton.dataset.modalTarget;
        if (modalSelector) {
            const modal = document.querySelector(modalSelector);
            if (modal) {
                openModal(modal, event.clientX, event.clientY);
            }
            activeModalId = modalSelector.startsWith('#') ? modalSelector.substring(1) : modalSelector;
        }
    }

    function handleModalClose(event) {
        if (event.target.classList.contains('close-modal-btn')) {
            const modal = event.target.closest('.modal-container');
            closeModal(modal);
            activeModalId = null;
        }
    }

    function handleOutsideClick(event) {
        if (event.target.classList.contains('modal-container')) {
            closeModal(event.target);
            activeModalId = null;
        }
    }

    function openModal(modalContainer, x, y) {
        if (!modalContainer) return;

        modalContainer.style.display = 'flex';
        const dialog = modalContainer.querySelector('.dialog');
        if (dialog) {
            dialog.classList.remove('closing');
            dialog.classList.add('opening');
        }
        modalContainer.classList.add('show');
    }

    function closeModal(modalContainer) {
        if (!modalContainer) return;
        const dialog = modalContainer.querySelector('.dialog');

        if (dialog) {
            dialog.classList.add('closing');
            dialog.addEventListener('animationend', function handler() {
                modalContainer.style.display = 'none';
                dialog.classList.remove('closing');
                modalContainer.remove();
                dialog.removeEventListener('animationend', handler);
            }, { once: true });
        } else {
            modalContainer.style.display = 'none';
        }
    }

    function init() {
        document.body.addEventListener('click', (event) => {
            handleModalOpen(event);
            handleModalClose(event);
        });

        document.addEventListener('mousedown', handleOutsideClick);

        document.body.addEventListener(HTMX_EVENTS.CLOSE_MODAL, (event) => {
            const detail = event.detail;
            if (detail && detail.modalId) {
                const modal = document.getElementById(detail.modalId);
                if (modal) {
                    closeModal(modal);
                }
                if (activeModalId === detail.modalId) {
                    activeModalId = null;
                }
            } else if (activeModalId) {
                const modal = document.getElementById(activeModalId);
                closeModal(modal);
                activeModalId = null;
            }
        });

        document.body.addEventListener(HTMX_EVENTS.CLOSE_SPECIFIC_MODAL, (event) => {
            let modalIds = event.detail && Array.isArray(event.detail.value) ? event.detail.value : [];
            modalIds.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal) {
                    closeModal(modal);
                    if (activeModalId === modalId) {
                        activeModalId = null;
                    }
                }
            });
        });
    }

    return { init };
})();


// Small Popup Management
const SmallPopupManager = (() => {
    let activePopup = null;
    let activeInput = null;
    let isMouseDown = false;
    let isPopupInteraction = false;
    let isHtmxRequestInProgress = false;

    /**
     * Walk up from a target checking for data-popup-keep-open attributes
     * and whether we're inside the notifications popup.
     * Shared by handleMouseDown / handleClick / htmx:beforeRequest.
     */
    function closestKeepOpen(target) {
        let keepOpen = false;
        let isInsideNotificationsPopup = false;
        let node = target;

        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('data-popup-keep-open')) {
                keepOpen = true;
            }
            if (node.id === 'notifications-list-box' ||
                (node.hasAttribute && node.getAttribute('data-popup-content') === 'notifications')) {
                isInsideNotificationsPopup = true;
            }
            node = node.parentNode;
        }

        return { keepOpen, isInsideNotificationsPopup };
    }

    function handleMouseDown(event) {
        isMouseDown = true;
        if (activePopup) {
            const target = event.target;
            const { keepOpen, isInsideNotificationsPopup } = closestKeepOpen(target);

            if (isDescendant(activePopup, target) || target === activeInput || keepOpen || isInsideNotificationsPopup) {
                isPopupInteraction = true;
            }
        }
    }

    function handleMouseUp() {
        isMouseDown = false;
        setTimeout(() => {
            isPopupInteraction = false;
        }, 0);
    }

    function handleClick(event) {
        const target = event.target;
        const { keepOpen, isInsideNotificationsPopup } = closestKeepOpen(target);

        if (target.matches('[data-type="small-popup"]')) {
            showDropdown(target);
        } else if (activePopup && !isPopupInteraction && !keepOpen && !isInsideNotificationsPopup && !isHtmxRequestInProgress) {
            if (!isDescendant(activePopup, target) && (!activeInput || !activeInput.contains(target))) {
                hideDropdown();
            }
        }
    }

    function handleFocusIn(event) {
        if (event.target.matches('[data-type="small-popup"]')) {
            showDropdown(event.target);
        }
    }
    
    function handleFocusOut(event) {
        if (isMouseDown || isPopupInteraction || isHtmxRequestInProgress) {
            return;
        }

        requestAnimationFrame(() => {
            if (!document.activeElement.matches('[data-type="small-popup"]') && 
                activePopup && 
                !isDescendant(activePopup, document.activeElement)) {
                hideDropdown();
            }
        });
    }

    // Named handlers so removeEventListener actually detaches them
    // (previously new arrow functions were created per call - they never matched,
    // so listeners accumulated on every popup open).
    function onPopupMouseEnter() {
        isPopupInteraction = true;
    }

    function onPopupMouseLeave() {
        isPopupInteraction = false;
    }

    function showDropdown(searchInput) {
        const popupWrapperId = searchInput.getAttribute('data-popup-wrapper');
        const searchResult = document.getElementById(popupWrapperId);

        if (!searchResult) {
            console.error('Could not find popup wrapper:', popupWrapperId);
            return;
        }

        if (activePopup && activePopup !== searchResult) {
            hideDropdown();
        }

        searchResult.style.display = "block";
        activePopup = searchResult;
        activeInput = searchInput;

        searchResult.addEventListener('mouseenter', onPopupMouseEnter);
        searchResult.addEventListener('mouseleave', onPopupMouseLeave);
    }

    function hideDropdown() {
        if (activePopup) {
            activePopup.style.display = "none";
            activePopup.innerHTML = "";
            activePopup.removeEventListener('mouseenter', onPopupMouseEnter);
            activePopup.removeEventListener('mouseleave', onPopupMouseLeave);
            activePopup = null;
            activeInput = null;
        }
    }

    function isDescendant(parent, child) {
        let node = child;
        while (node) {
            if (node === parent) return true;
            node = node.parentNode;
        }
        return false;
    }

    function init() {
        document.addEventListener('mousedown', handleMouseDown);
        document.addEventListener('mouseup', handleMouseUp);
        document.addEventListener('click', handleClick);
        document.addEventListener('focusin', handleFocusIn);
        document.addEventListener('focusout', handleFocusOut);
        
        // Handle HTMX requests from popup buttons to keep popup open
        document.body.addEventListener('htmx:beforeRequest', function(event) {
            if (event.detail && event.detail.elt) {
                const { keepOpen, isInsideNotificationsPopup } = closestKeepOpen(event.detail.elt);
                if (keepOpen) {
                    isPopupInteraction = true;
                }
                if (isInsideNotificationsPopup) {
                    isPopupInteraction = true;
                    isHtmxRequestInProgress = true;
                }
            }
        });
        
        // Reset after HTMX request completes
        document.body.addEventListener('htmx:afterRequest', function() {
            setTimeout(() => {
                isPopupInteraction = false;
                isHtmxRequestInProgress = false;
            }, 100);
        });
        
        // Also handle after settle (when content is actually swapped)
        document.body.addEventListener('htmx:afterSettle', function() {
            setTimeout(() => {
                isHtmxRequestInProgress = false;
            }, 50);
        });
    }

    return { init };
})();


// Global System Message Popup
const GlobalMessagePopup = (() => {
    function handleGlobalMessagePopupUpdate(event) {
        const { message, type } = event.detail;
        const messagePopup = document.getElementById('global-system-message');
        
        if (messagePopup) {
            const formattedMessage = message.replace(/\\n/g, '\n');
            
            messagePopup.innerText = formattedMessage || "Default message";
            messagePopup.className = type === 'success' ? 'success-style' : 'error-style';

            messagePopup.style.display = 'block';
            messagePopup.style.opacity = 1;

            setTimeout(() => {
                messagePopup.style.opacity = 0;
                messagePopup.addEventListener('transitionend', () => messagePopup.style.display = 'none', { once: true });
            }, 4000);
        }
    }

    function init() {
        document.body.addEventListener(HTMX_EVENTS.GLOBAL_MESSAGE, handleGlobalMessagePopupUpdate);
    }

    return { init };
})();


// TinyMCE Editor Management
const TinyMCEManager = (() => {
    function setupTinyMCEObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach(checkAndInitTinyMCE);
            });
        });

        const config = { childList: true, subtree: true };
        observer.observe(document.body, config);
    }

    function checkAndInitTinyMCE(node) {
        if (node.nodeType !== 1) return;

        // TinyMCE rewrites its own UI constantly (toolbars, menus, iframes) and
        // moves the source textarea into .tox-tinymce. Scanning those subtrees
        // is wasted work and used to re-initialise the editor on every change.
        if (node.closest && node.closest('.tox-tinymce')) return;

        if (node.matches('.tinymce_editor')) {
            initTinyMCE(node);
        } else if (node.hasChildNodes()) {
            Array.from(node.querySelectorAll('.tinymce_editor')).forEach(initTinyMCE);
        }
    }

    /**
     * The submit control that belongs to a form.
     *
     * Dialog save buttons sit outside the form (linked with form="…") and are
     * <button type="submit">, while a form can also contain other submit inputs
     * (e.g. the resolved-task "Move task" button) — so the primary control is
     * marked with data-form-submit.
     *
     * @param {HTMLFormElement} form
     * @return {HTMLElement|null}
     */
    function findFormSubmit(form) {
        if (!form) return null;

        const scope = form.closest('.modal-container') || document;
        const marked = scope.querySelector('[data-form-submit]');
        if (marked) return marked;

        return form.querySelector('input[type="submit"], button[type="submit"]')
            || (form.id ? document.querySelector(`[form="${form.id}"][type="submit"]`) : null);
    }

    function initTinyMCE(element) {
        const existingInstance = element.id ? tinymce.get(element.id) : null;

        if (existingInstance) {
            // Same element → already live. The MutationObserver sees it again
            // once TinyMCE has moved it into its own UI, so bail out instead of
            // tearing the editor down and rebuilding it.
            if (existingInstance.getElement() === element) {
                return;
            }
            // Genuinely stale instance: htmx replaced the modal, so tinymce
            // still references the removed element.
            existingInstance.remove();
            console.log('removing stale TinyMCE instance for #' + element.id);
        }

            const mceFontSize = window.innerWidth <= 2000 ? '13px' : '15px';
            
            tinymce.init({
            target: element,
            relative_urls: false,
            height: 300,
            plugins: 'autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help image',
            toolbar: 'undo redo | styles | bold italic backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | removeformat | image | fullscreen | savetask',
            menubar: false,
            toolbar_mode: 'sliding',
            statusbar: false,
            content_style: 'body { font-size: ' + mceFontSize + ';  }',
            setup: (editor) => {
                editor.ui.registry.addButton('savetask', {
                    text: 'Save',
                    onAction: (_) => {
                        const submitButton = findFormSubmit(editor.getElement().closest('form'));
                        if (submitButton) submitButton.click();
                    }
                });
            },
            images_upload_handler: function (blobInfo, success, failure) {
                const base64str = "data:" + blobInfo.blob().type + ";base64," + blobInfo.base64();
                return Promise.resolve(base64str);
            },
            license_key: 'gpl',
            init_instance_callback: function(editor) {
                editor.getElement().style.display = 'none'; 

                editor.addShortcut("ctrl+s", "Custom Ctrl+S", "custom_ctrl_s");
                editor.addCommand("custom_ctrl_s", function() {
                    const submitButton = findFormSubmit(editor.getElement().closest('form'));
                    if (submitButton) submitButton.click();
                });
            }
        });
    }

    function init() {
        setupTinyMCEObserver();
    }

    return { init };
})();


// Panel Modal Manager - For card-style popups (notifications, chat)
const PanelModalManager = (() => {
    let activeModal = null;
    let activeTrigger = null;
    let pendingModalId = null;
    let isHtmxRequestInProgress = false;

    function init() {
        // Handle close clicks
        document.body.addEventListener('click', handleCloseClick);
        
        // Handle outside clicks
        document.addEventListener('mousedown', handleOutsideClick);
        
        // Handle tab switching
        document.body.addEventListener('click', handleTabClick);
        
        // Handle notification item clicks
        document.body.addEventListener('click', handleNotificationClick);
        
        // Handle mark as read action
        document.body.addEventListener('click', handleMarkAsReadClick);

        // Handle HTMX beforeRequest - track if this is a panel modal trigger
        document.body.addEventListener('htmx:beforeRequest', function(event) {
            const trigger = event.detail.elt;
            if (trigger.hasAttribute && trigger.hasAttribute('data-panel-modal')) {
                pendingModalId = trigger.getAttribute('data-panel-modal');
                if (activeModal) {
                    closeModal(activeModal);
                }
            }
            
            // Check if request is from inside panel modal
            if (event.detail && event.detail.elt) {
                let node = event.detail.elt;
                while (node && node !== document.body) {
                    if (node.closest && node.closest('.panel-modal')) {
                        isHtmxRequestInProgress = true;
                        break;
                    }
                    node = node.parentNode;
                }
            }
        });
        
        // Handle HTMX afterSwap - show modal after content is swapped
        document.body.addEventListener('htmx:afterSwap', function(event) {
            // If we have a pending modal ID, try to find and show it after a short delay
            if (pendingModalId) {
                setTimeout(() => {
                    const modal = document.getElementById(pendingModalId);
                    if (modal) {
                        // Find the trigger element
                        const trigger = document.querySelector(`[data-panel-modal="${pendingModalId}"]`);
                        if (trigger) {
                            activeTrigger = trigger;
                        }
                        activeModal = modal;
                        modal.classList.add('opening');
                    }
                    pendingModalId = null;
                }, 10);
            }
            
            setTimeout(() => {
                isHtmxRequestInProgress = false;
            }, 50);
        });
        
        // Handle HTMX beforeSwap on panel-modal-close to keep modal open
        document.body.addEventListener('htmx:beforeSwap', function(event) {
            // Reset after request
            setTimeout(() => {
                isHtmxRequestInProgress = false;
            }, 100);
        });
    }

    function closeModal(modal) {
        modal.classList.remove('opening');
        if (activeModal === modal) {
            activeModal = null;
            activeTrigger = null;
        }
        // Remove old modal from DOM after animation completes to prevent stale elements
        setTimeout(() => {
            if (modal && modal.parentNode) {
                modal.remove();
            }
        }, 250);
    }

    function handleCloseClick(event) {
        const closeBtn = event.target.closest('.panel-modal-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.panel-modal');
            closeModal(modal);
        }
    }

    function handleOutsideClick(event) {
        if (!activeModal || isHtmxRequestInProgress) return;
        
        const isInside = activeModal.contains(event.target);
        const isTrigger = activeTrigger && activeTrigger.contains(event.target);
        
        if (!isInside && !isTrigger) {
            closeModal(activeModal);
        }
    }

    function handleTabClick(event) {
        const tab = event.target.closest('.panel-modal-tab');
        if (!tab) return;
        
        const modal = tab.closest('.panel-modal');
        const filter = tab.getAttribute('data-filter');
        
        // Update active state
        modal.querySelectorAll('.panel-modal-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Load filtered content
        const contentContainer = modal.querySelector('.panel-modal-content');
        if (contentContainer) {
            htmx.ajax('GET', `${APP_ROUTES.NOTIFICATIONS_LIST}/${filter}`, { 
                target: contentContainer, 
                swap: 'innerHTML' 
            });
        }
    }

    function getCsrfToken() {
        // Delegate to the shared Csrf helper (http.js)
        return Csrf.getToken();
    }

    function getCsrfTokenFromItem(item) {
        // Get from the notification item itself
        if (item && item.dataset.csrfToken) return item.dataset.csrfToken;

        // Fallback to shared helper
        return Csrf.getToken();
    }

    function handleNotificationClick(event) {
        const item = event.target.closest('.notification-item');
        if (!item) return;
        
        // Don't expand if clicking action buttons
        if (event.target.closest('.notification-actions')) return;
        
        const notificationId = item.getAttribute('data-notification-id');
        const isUnread = item.classList.contains('unread');
        
        // Toggle expanded state
        const isExpanded = item.classList.contains('expanded');
        
        // Close all other expanded items in this modal
        const modal = item.closest('.panel-modal');
        if (modal) {
            modal.querySelectorAll('.notification-item.expanded').forEach(el => {
                if (el !== item) el.classList.remove('expanded');
            });
        }
        
        if (!isExpanded) {
            item.classList.add('expanded');
        } else {
            item.classList.remove('expanded');
        }
        
        // Mark as read if unread
        if (isUnread) {
            // Create form data
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            formData.append('csrf_token', getCsrfTokenFromItem(item));

            Http.postForm(APP_ROUTES.NOTIFICATIONS_MARK_READ, formData, {
                errorMessage: 'Failed to mark notification as read'
            }).then(() => {
                // Trigger refresh
                document.body.dispatchEvent(new CustomEvent(HTMX_EVENTS.NOTIFICATIONS_UPDATE));
            }).catch(() => {
                // Toast already shown by Http helper
            });
        }
    }

    function handleMarkAsReadClick(event) {
        const btn = event.target.closest('[data-mark-read]');
        if (!btn) return;
        
        event.stopPropagation();
        const notificationId = btn.getAttribute('data-notification-id');
        const item = btn.closest('.notification-item');
        
        htmx.ajax('POST', APP_ROUTES.NOTIFICATIONS_MARK_READ, {
            vals: { notification_id: notificationId, csrf_token: getCsrfTokenFromItem(item) },
            swap: 'none'
        });
    }

    return { init };
})();

// Generic Table Select Manager (For Multi-Select & Batch Actions)
const TableSelectManager = (() => {
    function init() {
        document.querySelectorAll('[data-selectable-table]').forEach(bindTable);
    }

    function bindTable(table) {
        if (table.dataset.selectManagerBound === 'true') return;
        table.dataset.selectManagerBound = 'true';

        const tableId = table.id;
        const selectAll = table.querySelector('[data-table-select-all]');
        const rowCheckboxes = table.querySelectorAll('.job-row-checkbox, [data-row-checkbox]');
        const batchBar = document.querySelector(`[data-batch-bar="${tableId}"]`) || document.querySelector('[data-batch-bar]');
        const counter = document.querySelector(`[data-selected-count="${tableId}"]`) || document.querySelector('[data-selected-count]');

        function updateState() {
            const currentRows = table.querySelectorAll('.job-row-checkbox, [data-row-checkbox]');
            const checked = table.querySelectorAll('.job-row-checkbox:checked, [data-row-checkbox]:checked');
            const count = checked.length;

            if (counter) counter.textContent = count;

            if (batchBar) {
                if (count > 0) batchBar.classList.add('visible');
                else batchBar.classList.remove('visible');
            }

            if (selectAll) {
                if (count === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (count === currentRows.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }
        }

        if (selectAll) {
            selectAll.onchange = (e) => {
                const isChecked = e.target.checked;
                table.querySelectorAll('.job-row-checkbox, [data-row-checkbox]').forEach(cb => {
                    cb.checked = isChecked;
                    const row = cb.closest('tr');
                    if (row) {
                        if (isChecked) row.classList.add('row-selected');
                        else row.classList.remove('row-selected');
                    }
                });
                updateState();
            };
        }

        rowCheckboxes.forEach(cb => {
            cb.onchange = () => {
                const row = cb.closest('tr');
                if (row) {
                    if (cb.checked) row.classList.add('row-selected');
                    else row.classList.remove('row-selected');
                }
                updateState();
            };
        });

        const deselectButtons = document.querySelectorAll(`[data-deselect-all="${tableId}"], [data-deselect-all]`);
        deselectButtons.forEach(btn => {
            btn.onclick = () => {
                table.querySelectorAll('.job-row-checkbox, [data-row-checkbox]').forEach(cb => {
                    cb.checked = false;
                    cb.closest('tr')?.classList.remove('row-selected');
                });
                if (selectAll) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
                updateState();
            };
        });
    }

    return { init, bindTable };
})();

// Generic Table Filter Manager (Client-side fast search & dropdown filtering)
const TableFilterManager = (() => {
    function init() {
        document.querySelectorAll('[data-filter-target]').forEach(bindFilterGroup);
    }

    function bindFilterGroup(group) {
        const targetSelector = group.dataset.filterTarget;
        const table = document.querySelector(targetSelector);
        if (!table) return;

        const searchInput = group.querySelector('[data-filter-search]');
        const selectFilters = group.querySelectorAll('[data-filter-key]');
        const togglePills = group.querySelectorAll('[data-filter-toggle]');
        const emptyState = document.querySelector(`[data-filter-empty="${targetSelector.replace('#', '')}"]`) || table.querySelector('[data-filter-empty]');

        function applyFilters() {
            const rows = table.querySelectorAll('tbody tr[data-filter-row]');
            if (!rows.length) return;

            const searchQuery = (searchInput?.value || '').toLowerCase().trim();
            let visibleCount = 0;

            rows.forEach(row => {
                let matches = true;

                // 1. Text search
                if (searchQuery) {
                    const rowText = row.textContent.toLowerCase();
                    if (!rowText.includes(searchQuery)) matches = false;
                }

                // 2. Select dropdown filters
                if (matches) {
                    selectFilters.forEach(select => {
                        const key = select.dataset.filterKey;
                        const val = select.value;
                        if (val && val !== 'all') {
                            const rowVal = row.getAttribute(`data-${key}`) || '';
                            if (rowVal !== val) matches = false;
                        }
                    });
                }

                // 3. Toggle pills
                if (matches) {
                    togglePills.forEach(pill => {
                        if (pill.classList.contains('active')) {
                            const key = pill.dataset.filterToggle;
                            const rowVal = row.getAttribute(`data-${key}`);
                            if (rowVal !== 'true') matches = false;
                        }
                    });
                }

                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        if (searchInput) searchInput.oninput = applyFilters;
        selectFilters.forEach(sel => sel.onchange = applyFilters);
        togglePills.forEach(pill => {
            pill.onclick = (e) => {
                e.preventDefault();
                pill.classList.toggle('active');
                applyFilters();
            };
        });
    }

    return { init, bindFilterGroup };
})();

// ============================================================================
// Modal tabs (Description / Checklist / Attachments) + attachment uploads
// Tabs appear conditionally: Checklist/Attachments tabs are hidden until the
// content exists or the user initiates adding one. Badges show item counts.
// ============================================================================

const ModalTabsManager = (() => {

    /** Tab currently being dragged (see the drag handlers below). */
    let draggedTab = null;

    /**
     * Activate a tab within a tab group.
     * @param {HTMLElement} tabsContainer - element with [data-modal-tabs]
     * @param {string} tabName - value of data-tab to activate
     */
    function activateTab(tabsContainer, tabName) {
        if (!tabsContainer) return;

        tabsContainer.querySelectorAll('.modal-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });

        const modal = tabsContainer.closest('.modal-container');
        if (modal) {
            modal.querySelectorAll('.modal-tab-pane').forEach(pane => {
                pane.classList.toggle('active', pane.dataset.tabPane === tabName);
            });
        }

        hideEmptyConditionalTabs(tabsContainer, tabName);
    }

    /**
     * Retire conditional tabs ([data-tab-hide-when-empty]) that are no longer
     * active and hold nothing, so an emptied pane stops advertising itself.
     * The active tab is never hidden - its pane would become unreachable - and
     * tabs without a badge are left alone (nothing to measure).
     */
    function hideEmptyConditionalTabs(tabsContainer, activeTabName) {
        tabsContainer.querySelectorAll('.modal-tab[data-tab-hide-when-empty]').forEach(tab => {
            if (tab.dataset.tab === activeTabName) return;

            const badge = tab.querySelector('.modal-tab-badge');
            if (!badge || (parseInt(badge.textContent, 10) || 0) > 0) return;

            tab.hidden = true;
            badge.hidden = true;
        });
    }

    /**
     * Reveal a conditional tab (remove hidden) and activate it.
     * Used when the user adds a checklist / first attachment.
     */
    function revealTab(tabsContainer, tabName) {
        if (!tabsContainer) return;
        const tab = tabsContainer.querySelector(`.modal-tab[data-tab="${tabName}"]`);
        if (tab) {
            tab.hidden = false;
            const badge = tab.querySelector('.modal-tab-badge');
            if (badge) badge.hidden = false;
        }
        activateTab(tabsContainer, tabName);
    }

    /**
     * Update a tab badge count. The badge mirrors its tab: it is visible
     * whenever the tab is visible (a "0" still tells the user the pane exists
     * but is empty) and hidden together with a hidden tab. A tab that was
     * retired while empty comes back as soon as it has content again.
     */
    function setBadge(tabsContainer, tabName, count) {
        if (!tabsContainer) return;
        const badge = tabsContainer.querySelector(`.modal-tab-badge[data-tab-badge="${tabName}"]`);
        if (!badge) return;
        badge.textContent = String(count);

        const tab = tabsContainer.querySelector(`.modal-tab[data-tab="${tabName}"]`);
        if (tab && tab.hidden && count > 0) {
            tab.hidden = false;
        }
        badge.hidden = !!tab && tab.hidden;
    }

    /**
     * Add to a tab badge count (e.g. one more attachment) and reveal the tab
     * when this is the first item — the tab itself appears only once content
     * exists, so a count of 1 must also unhide it.
     */
    function incrementBadge(tabsContainer, tabName, amount = 1) {
        if (!tabsContainer) return;
        const badge = tabsContainer.querySelector(`.modal-tab-badge[data-tab-badge="${tabName}"]`);
        if (!badge) return;

        const tab = tabsContainer.querySelector(`.modal-tab[data-tab="${tabName}"]`);
        if (tab && tab.hidden) {
            tab.hidden = false;
        }

        setBadge(tabsContainer, tabName, (parseInt(badge.textContent, 10) || 0) + amount);
    }

    // --- Drag to reorder tabs ------------------------------------------------
    // Native HTML5 drag & drop: SortableJS is only loaded on the board page,
    // while the tab bar appears in dialogs on three different pages. The
    // resulting order is persisted per user + context (UiPreferenceService).

    /**
     * Mark tabs as draggable — called for tabs already in the DOM and after
     * swaps. Only tab bars carrying a saved-order context are reorderable:
     * the "New recurring task" dialog renders a fixed order, so dragging there
     * would have nothing to persist.
     */
    function enableTabDragging(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('[data-modal-tabs][data-tab-order-context] .modal-tab')
            .forEach(tab => { tab.draggable = true; });
    }

    function handleDragStart(event) {
        const tab = event.target.closest('.modal-tab');
        const tabsContainer = tab && tab.closest('[data-modal-tabs]');
        if (!tab || tab.hidden || !tabsContainer || !tabsContainer.dataset.tabOrderContext) return;

        draggedTab = tab;
        tab.classList.add('modal-tab-dragging');

        if (event.dataTransfer) {
            // Firefox only starts a drag once some data is set.
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', tab.dataset.tab || '');
        }
    }

    /** Live preview: keep the dragged tab next to the one being hovered. */
    function handleDragOver(event) {
        if (!draggedTab) return;

        const tabsContainer = draggedTab.closest('[data-modal-tabs]');
        const target = event.target.closest('.modal-tab');
        if (!tabsContainer || !target || target === draggedTab || target.hidden) return;
        if (target.parentElement !== tabsContainer) return;

        event.preventDefault();
        const box = target.getBoundingClientRect();
        const insertAfter = event.clientX > box.left + box.width / 2;
        tabsContainer.insertBefore(draggedTab, insertAfter ? target.nextSibling : target);
    }

    function handleDrop(event) {
        if (draggedTab) event.preventDefault();
    }

    function handleDragEnd() {
        const tab = draggedTab;
        draggedTab = null;
        if (!tab) return;

        tab.classList.remove('modal-tab-dragging');

        const tabsContainer = tab.closest('[data-modal-tabs]');
        if (tabsContainer) saveTabOrder(tabsContainer);
    }

    /** Persist the visual order of a tab bar for its context. */
    function saveTabOrder(tabsContainer) {
        const context = tabsContainer.dataset.tabOrderContext;
        if (!context || typeof Http === 'undefined' || typeof APP_ROUTES === 'undefined') return;

        const tabs = Array.from(tabsContainer.querySelectorAll('.modal-tab'))
            .map(tab => tab.dataset.tab)
            .filter(Boolean);

        // fetchJson already shows an error toast on transport/HTTP failure; the
        // endpoint can also answer 200 with success:false (e.g. the preference
        // table is missing), which must not pass silently either.
        Http.postJson(APP_ROUTES.TAB_ORDER, { context, tabs }, {
            errorMessage: 'Failed to save the tab order'
        }).then((result) => {
            if (result && result.success === false) {
                Http.toastError(result.message || 'Failed to save the tab order');
            }
        }).catch(() => {});
    }

    /**
     * Keep the Attachments badge in step with the list. Uploads and deletes both
     * re-render .attachment-list-container (attachmentsUpdate), so the row count
     * in that container is the authoritative number — deleting a file drops the
     * badge without a page reload.
     */
    function syncAttachmentsBadge(listContainer) {
        if (!listContainer) return;

        // Nothing rendered yet: the container is filled by its own
        // hx-trigger="load" request, so keep the server-rendered count until
        // the list (or its empty state) actually arrives.
        if (!listContainer.querySelector('.attachment-list, .attachment-list-empty')) return;

        const modal = listContainer.closest('.modal-container');
        const tabs = modal && modal.querySelector('[data-modal-tabs]');
        if (!tabs) return;

        setBadge(tabs, 'attachments', listContainer.querySelectorAll('.attachment-item').length);
    }

    /**
     * htmx:afterSwap — an attachment list was just (re-)rendered. Both
     * detail.elt and detail.target are inspected: they are the same element
     * for the list container itself, but a swap triggered elsewhere can still
     * carry the container in its subtree.
     * @param {CustomEvent} event
     */
    function handleAfterSwap(event) {
        const detail = event.detail || {};

        [detail.elt, detail.target].forEach(node => {
            if (!node || !node.classList) return;

            // Newly swapped dialogs carry a fresh tab bar.
            enableTabDragging(node);

            if (node.classList.contains('attachment-list-container')) {
                syncAttachmentsBadge(node);
                return;
            }

            if (node.querySelectorAll) {
                node.querySelectorAll('.attachment-list-container').forEach(syncAttachmentsBadge);
            }
        });
    }

    /** Tab click handling (delegated — modal content is swapped dynamically). */
    function handleClick(event) {
        const tab = event.target.closest('.modal-tab');
        if (tab && tab.closest('[data-modal-tabs]')) {
            event.preventDefault();
            activateTab(tab.closest('[data-modal-tabs]'), tab.dataset.tab);
            return;
        }

        // Sidebar buttons ("Checklist" / "Attachments") switch to their tab.
        const switchBtn = event.target.closest('[data-switch-tab]');
        if (switchBtn) {
            const modal = switchBtn.closest('.modal-container');
            const tabs = modal && modal.querySelector('[data-modal-tabs]');
            if (tabs) {
                revealTab(tabs, switchBtn.dataset.switchTab);
            }
        }
    }

    /**
     * Parse the HX-Trigger header of an XHR into a plain object.
     *
     * The upload endpoint returns HTTP 200 for logical failures too (it signals
     * them with a toast trigger), so callers must decide success from the
     * payload, not from xhr.status.
     *
     * @param {XMLHttpRequest} xhr
     * @return {Object} Event name => detail map, or {} when absent/unparsable.
     */
    function parseTriggerHeader(xhr) {
        const header = xhr.getResponseHeader('HX-Trigger');
        if (!header) return {};

        try {
            return JSON.parse(header) || {};
        } catch (parseError) {
            console.error('Invalid HX-Trigger header', parseError);
            return {};
        }
    }

    /** Re-dispatch server triggers as DOM events (htmx does this for htmx calls). */
    function dispatchTriggers(triggers) {
        Object.entries(triggers).forEach(([eventName, detail]) => {
            document.body.dispatchEvent(new CustomEvent(eventName, { detail }));
        });
    }

    /**
     * Attachment uploads: file input change → POST via fetch with progress.
     * The upload URL lives on the hidden file input (data-upload-url).
     */
    function handleFileSelected(event) {
        const input = event.target.closest('.attachment-file-input');
        if (!input || !input.files || input.files.length === 0) return;

        // One upload per pane at a time: a second XHR would race the first and
        // leave the badge/list out of step.
        if (input.dataset.uploading === 'true') return;

        const file = input.files[0];
        const url = input.dataset.uploadUrl;
        if (!url) return;

        const pane = input.closest('.attachments-pane');
        const progress = pane && pane.querySelector('.attachment-progress');
        const progressBar = pane && pane.querySelector('.attachment-progress-bar');
        const progressLabel = pane && pane.querySelector('.attachment-progress-label');
        const uploadBtn = pane && pane.querySelector('.attachment-upload-btn');

        // Client-side size check for immediate feedback. Uses the effective
        // server ceiling (min of app limit and php.ini post_max_size) so an
        // oversized file is rejected before the request is even sent.
        const maxBytes = window.ATTACHMENT_SERVER_MAX_BYTES || window.ATTACHMENT_MAX_BYTES || 0;
        if (maxBytes > 0 && file.size > maxBytes) {
            Http.toastError(`File is too large — the server accepts at most ${Math.round(maxBytes / 1048576 * 10) / 10} MB.`);
            input.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('attachment', file, file.name);
        formData.append('csrf_token', Csrf.getToken());

        input.dataset.uploading = 'true';
        input.disabled = true;
        if (uploadBtn) uploadBtn.disabled = true;
        if (progress) progress.hidden = false;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('X-CSRF-Token', Csrf.getToken());
        xhr.setRequestHeader('HX-Request', 'true');

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable && progressBar) {
                const pct = Math.round((e.loaded / e.total) * 100);
                progressBar.style.setProperty('--progress', pct + '%');
                if (progressLabel) progressLabel.textContent = `Uploading… ${pct}%`;
            }
        });

        /** Shared cleanup for the load/error paths. */
        const finish = () => {
            delete input.dataset.uploading;
            input.disabled = false;
            if (uploadBtn) uploadBtn.disabled = false;
            if (progress) progress.hidden = true;
            if (progressBar) progressBar.style.setProperty('--progress', '0%');
            input.value = '';
        };

        xhr.addEventListener('load', () => {
            finish();

            const triggers = parseTriggerHeader(xhr);
            dispatchTriggers(triggers);

            // Success is signalled by the attachmentsUpdate trigger, NOT by the
            // status code: validation errors also come back as HTTP 200 with an
            // error toast (and must not bump the badge).
            const accepted = Boolean(triggers[HTMX_EVENTS.ATTACHMENTS_UPDATE]);
            const serverToasted = Boolean(triggers[HTMX_EVENTS.GLOBAL_MESSAGE]);

            if (!accepted && !serverToasted) {
                Http.toastError(xhr.status === 200 ? 'Upload failed.' : `Upload failed (HTTP ${xhr.status})`);
            }

            maybeStorePendingToken(pane, xhr);

            if (accepted) {
                // The server-rendered badge count is only correct on first paint —
                // keep it in step with the file that was just accepted. The list
                // refresh (attachmentsUpdate) re-syncs it to the exact count.
                const modal = input.closest('.modal-container');
                incrementBadge(modal && modal.querySelector('[data-modal-tabs]'), 'attachments');
            }
        });

        xhr.addEventListener('error', () => {
            finish();
            Http.toastError('Network error during upload');
        });

        xhr.send(formData);
    }

    /** "Add file…" button opens the hidden file input. */
    function handleUploadButtonClick(event) {
        const btn = event.target.closest('.attachment-upload-btn');
        if (!btn) return;
        const pane = btn.closest('.attachments-pane');
        const input = pane && pane.querySelector('.attachment-file-input');
        if (input) input.click();
    }

    /**
     * After a pending upload (item not yet created, itemId = 0), append a
     * hidden token field to the item-create form so the token is posted and
     * claimed server-side when the item is saved.
     */
    function appendPendingToken(pane, token) {
        if (!pane || !token) return;
        const holder = pane.querySelector('.attachment-pending-tokens');
        if (!holder) return;
        if (holder.querySelector(`input[value="${token}"]`)) return; // dedupe

        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'pending_attachments[]';
        field.value = token;
        holder.appendChild(field);
    }

    /**
     * Extract the pending token from an upload response. The server fires
     * the attachmentsUpdate event with itemId 0 for pending uploads; the
     * token itself rides in a custom header.
     */
    function maybeStorePendingToken(pane, xhr) {
        if (!pane) return;
        const itemId = pane.dataset.itemId;
        if (itemId !== '0') return;
        appendPendingToken(pane, xhr.getResponseHeader('X-Attachment-Token'));
    }

    /**
     * Global drag-and-drop on the whole modal: dragging a file anywhere over
     * the task dialog switches to the Attachments tab and shows an overlay;
     * dropping starts the upload. preventDefault stops the browser from
     * opening the file.
     */
    /**
     * Is this drag event carrying files? Only those may show the drop overlay —
     * the tab bar uses HTML5 drag events too (ModalTabsManager reorder), and a
     * tab drag must not advertise "Drop file to attach".
     *
     * @param {DragEvent} event
     * @return {boolean}
     */
    function isFileDrag(event) {
        if (!event.dataTransfer) return false;

        const types = Array.from(event.dataTransfer.types || []);
        return types.includes('Files');
    }

    function initDragAndDrop() {
        document.body.addEventListener('dragover', (event) => {
            if (draggedTab || !isFileDrag(event)) return;

            const modal = event.target.closest && event.target.closest('.modal-container');
            if (!modal || !modal.querySelector('.attachments-pane')) return;
            event.preventDefault();
            showOverlay(modal);
        });

        document.body.addEventListener('dragleave', (event) => {
            if (draggedTab) return;

            const modal = event.target.closest && event.target.closest('.modal-container');
            if (!modal) return;
            // Only hide when the pointer actually left the modal
            if (!modal.contains(event.relatedTarget)) hideOverlay(modal);
        });

        document.body.addEventListener('drop', (event) => {
            const modal = event.target.closest && event.target.closest('.modal-container');
            if (!modal) return;
            hideOverlay(modal);
            if (draggedTab || !isFileDrag(event)) return;
            if (!modal.querySelector('.attachments-pane')) return;
            event.preventDefault();

            const files = event.dataTransfer && event.dataTransfer.files;
            if (!files || files.length === 0) return;

            // Switch to the Attachments tab, then feed the file through the input
            const tabs = modal.querySelector('[data-modal-tabs]');
            revealTab(tabs, 'attachments');

            const input = modal.querySelector('.attachment-file-input');
            if (!input) return;

            // DataTransfer.files is assignable in modern browsers; if it is not,
            // say so instead of silently doing nothing.
            try {
                input.files = files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {
                Http.toastError('Your browser could not attach the dropped file. Use "Add file…" instead.');
            }
        });
    }

    function showOverlay(modal) {
        if (modal.querySelector('.modal-drop-overlay')) return;
        const dialog = modal.querySelector('.dialog');
        if (!dialog) return;
        if (getComputedStyle(dialog).position === 'static') {
            dialog.style.position = 'relative';
        }
        const overlay = document.createElement('div');
        overlay.className = 'modal-drop-overlay';
        overlay.textContent = 'Drop file to attach';
        dialog.appendChild(overlay);
    }

    function hideOverlay(modal) {
        const overlay = modal.querySelector('.modal-drop-overlay');
        if (overlay) overlay.remove();
    }

    function init() {
        // Idempotent: guards against double-binding (e.g. if init is invoked
        // from more than one bootstrap path), which would duplicate uploads.
        if (ModalTabsManager._initialized) return;
        ModalTabsManager._initialized = true;

        document.body.addEventListener('click', handleClick);
        document.body.addEventListener('change', (e) => {
            if (e.target.closest && e.target.closest('.attachment-file-input')) {
                handleFileSelected(e);
            }
        });
        document.body.addEventListener('click', handleUploadButtonClick);
        document.body.addEventListener('htmx:afterSwap', handleAfterSwap);
        document.body.addEventListener('dragstart', handleDragStart);
        document.body.addEventListener('dragover', handleDragOver);
        document.body.addEventListener('drop', handleDrop);
        document.body.addEventListener('dragend', handleDragEnd);
        enableTabDragging(document.body);
        initDragAndDrop();
    }

    return { init, activateTab, revealTab, setBadge, incrementBadge };
})();

// ModalTabsManager.init() is invoked from the main DOMContentLoaded bootstrap
// below — no separate listener here, or every upload would fire twice.

document.addEventListener('DOMContentLoaded', () => {
    if (typeof ContextMenuManager !== 'undefined') {
        ContextMenuManager.init();
    }
    ModalManager.init();
    SmallPopupManager.init();
    PanelModalManager.init();
    GlobalMessagePopup.init();
    TinyMCEManager.init();
    TableSelectManager.init();
    TableFilterManager.init();
    ModalTabsManager.init();
    
    // Process HTMX attributes on panel-modal triggers after HTMX is loaded
    if (typeof htmx !== 'undefined') {
        document.querySelectorAll('[data-panel-modal]').forEach(el => {
            htmx.process(el);
        });
    }
});

// Attach CSRF token to every HTMX request globally (from meta tag)
// and flush TinyMCE editor content to textareas before form serialization
document.body.addEventListener('htmx:configRequest', (event) => {
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) {
        event.detail.headers['X-CSRF-Token'] = meta.content;
    }
});

// Re-init generic table handlers after HTMX content swap
document.body.addEventListener('htmx:afterSwap', () => {
    TableSelectManager.init();
    TableFilterManager.init();
});