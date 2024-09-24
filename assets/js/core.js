// Core JavaScript File

// Modal Management System
const ModalManager = (() => {
    let activeModalId = null;

    function handleModalOpen(event) {
        const openModalButton = event.target.closest('.open-modal-btn');
        if (openModalButton) {
            const modalSelector = openModalButton.dataset.modalTarget;
            const modal = document.querySelector(modalSelector);
            openModal(modal, event.clientX, event.clientY);
            
            if (modalSelector != undefined) {
                activeModalId = modalSelector.substring(1);
            }
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

        document.body.addEventListener('closeModalEvent', () => {
            if (activeModalId) {
                const modal = document.getElementById(activeModalId);
                closeModal(modal);
                activeModalId = null;
            }
        });

        document.body.addEventListener('closeSpecificModalEvent', (event) => {
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

    function handleMouseDown(event) {
        isMouseDown = true;
        if (activePopup && (isDescendant(activePopup, event.target) || event.target === activeInput)) {
            isPopupInteraction = true;
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
        
        if (target.matches('[data-type="small-popup"]')) {
            showDropdown(target);
        } else if (activePopup && !isPopupInteraction) {
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
        if (isMouseDown || isPopupInteraction) {
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

        searchResult.addEventListener('mouseenter', () => {
            isPopupInteraction = true;
        });
        searchResult.addEventListener('mouseleave', () => {
            isPopupInteraction = false;
        });
    }

    function hideDropdown() {
        if (activePopup) {
            activePopup.style.display = "none";
            activePopup.innerHTML = "";
            activePopup.removeEventListener('mouseenter', () => {
                isPopupInteraction = true;
            });
            activePopup.removeEventListener('mouseleave', () => {
                isPopupInteraction = false;
            });
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
        document.body.addEventListener('globalMessagePopupUpdate', handleGlobalMessagePopupUpdate);
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

        tinymce.init({
            target: element,
            relative_urls: false,
            height: 300,
            plugins: 'autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help image',
            toolbar: 'undo redo | styles | formatselect | bold italic backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | removeformat | image | fullscreen | savetask',
            menubar: false,
            toolbar_mode: 'false',
            statusbar: false,
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






// Initialize all modules
document.addEventListener('DOMContentLoaded', () => {
    ModalManager.init();
    SmallPopupManager.init();
    GlobalMessagePopup.init();
    TinyMCEManager.init();
});