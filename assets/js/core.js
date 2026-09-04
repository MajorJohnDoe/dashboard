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
        if (node.nodeType === 1 && node.matches('.tinymce_editor')) {
            initTinyMCE(node);
        } else if (node.nodeType === 1 && node.hasChildNodes()) {
            Array.from(node.querySelectorAll('.tinymce_editor')).forEach(initTinyMCE);
        }
    }

    function initTinyMCE(element) {
        const existingInstance = tinymce.get(element.id);

        if (existingInstance) {
            existingInstance.remove();
            console.log('removing existing instance, initializing a new instance');
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
                        const form = editor.getElement().closest('form');
                        if (form) {
                            const submitButton = form.querySelector('input[type="submit"]');
                            if (submitButton) submitButton.click();
                        }
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
                    const form = editor.getElement().closest('form');
                    if (form) {
                        const submitButton = form.querySelector('input[type="submit"]');
                        if (submitButton) submitButton.click();
                    }
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