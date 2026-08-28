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

        document.body.addEventListener('closeModalEvent', (event) => {
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
    let isHtmxRequestInProgress = false;

    function handleMouseDown(event) {
        isMouseDown = true;
        if (activePopup) {
            // Check if click is inside popup or any HTMX button with keep-open attribute
            const target = event.target;
            let keepOpen = false;
            let isInsideNotificationsPopup = false;
            let node = target;
            
            while (node && node !== document.body) {
                // Check for data-popup-keep-open attribute
                if (node.hasAttribute && node.hasAttribute('data-popup-keep-open')) {
                    keepOpen = true;
                }
                // Check if inside notifications popup
                if (node.id === 'notifications-list-box' || 
                    (node.hasAttribute && node.getAttribute('data-popup-content') === 'notifications')) {
                    isInsideNotificationsPopup = true;
                }
                node = node.parentNode;
            }
            
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
        
        // Check if the click or any parent has data-popup-keep-open attribute (for HTMX elements)
        // Also check if inside notifications popup
        let keepOpen = false;
        let isInsideNotificationsPopup = false;
        let node = target;
        
        while (node && node !== document.body) {
            if (node.hasAttribute && node.hasAttribute('data-popup-keep-open')) {
                keepOpen = true;
            }
            // Check if inside notifications popup
            if (node.id === 'notifications-list-box' || 
                (node.hasAttribute && node.getAttribute('data-popup-content') === 'notifications')) {
                isInsideNotificationsPopup = true;
            }
            node = node.parentNode;
        }
        
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
        
        // Handle HTMX requests from popup buttons to keep popup open
        document.body.addEventListener('htmx:beforeRequest', function(event) {
            if (event.detail && event.detail.elt) {
                let node = event.detail.elt;
                while (node && node !== document.body) {
                    if (node.hasAttribute && node.hasAttribute('data-popup-keep-open')) {
                        isPopupInteraction = true;
                        break;
                    }
                    // Check if request is from inside notifications popup
                    if (node.id === 'notifications-list-box' || 
                        (node.hasAttribute && node.getAttribute('data-popup-content') === 'notifications')) {
                        isPopupInteraction = true;
                        isHtmxRequestInProgress = true;
                        break;
                    }
                    node = node.parentNode;
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

            const mceFontSize = window.innerWidth <= 2000 ? '13px' : '15px';
            
            tinymce.init({
            target: element,
            relative_urls: false,
            height: 300,
            plugins: 'autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help image',
            toolbar: 'undo redo | styles | formatselect | bold italic backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | removeformat | image | fullscreen | savetask',
            menubar: false,
            toolbar_mode: 'false',
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


// Image Cropper Class
// Image Cropper Class
class ImageCropper {
    constructor(fileInputId, previewContainerId) {
        this.fileInput = document.getElementById(fileInputId);
        this.previewContainer = document.getElementById(previewContainerId);
        this.isDragging = false;
        this.isResizing = false;
        this.dragStartX = 0;
        this.dragStartY = 0;
        this.cropX = 0;
        this.cropY = 0;
        this.cropSize = 200;
        this.minCropSize = 100;
        this.maxCropSize = 350;
        this.scale = 1;
        this.resizeHandleSize = 12;
        this.setupEventListeners();
    }

    setupEventListeners() {
        if (!this.fileInput) {
            return;
        }
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
    }

    handleFileSelect(event) {
        const file = event.target.files[0];
        
        if (!file) {
            return;
        }

        if (!file.type.match(/^image\/(jpeg|jpg)$/)) {
            alert('Only JPG/JPEG images are allowed');
            if (this.fileInput) this.fileInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            this.createCropInterface(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    createCropInterface(imageUrl) {
        if (!this.previewContainer) {
            return;
        }
        this.previewContainer.innerHTML = '';
        
        const container = document.createElement('div');
        container.className = 'crop-container';
        container.style.cssText = 'position: relative; width: 100%; height: 400px; overflow: hidden; border: 1px solid #ccc; background: #000;';

        // Create canvas for image display and cropping
        this.canvas = document.createElement('canvas');
        this.canvas.style.cssText = 'position: absolute; cursor: move;';
        this.ctx = this.canvas.getContext('2d');

        // Load image
        this.image = new Image();
        this.image.onload = () => {
            const containerWidth = container.clientWidth;
            const containerHeight = container.clientHeight;
            const imageAspect = this.image.width / this.image.height;
            const containerAspect = containerWidth / containerHeight;

            if (imageAspect > containerAspect) {
                this.canvas.width = containerWidth;
                this.canvas.height = containerWidth / imageAspect;
                this.scale = containerWidth / this.image.width;
            } else {
                this.canvas.height = containerHeight;
                this.canvas.width = containerHeight * imageAspect;
                this.scale = containerHeight / this.image.height;
            }

            // Center the crop area initially
            this.cropX = (this.canvas.width - this.cropSize) / 2;
            this.cropY = (this.canvas.height - this.cropSize) / 2;

            this.draw();
        };
        this.image.src = imageUrl;

        container.appendChild(this.canvas);

        // Add mouse event listeners
        this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(this.getEventPos(e)));
        this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(this.getEventPos(e)));
        this.canvas.addEventListener('mouseup', () => this.onMouseUp());
        this.canvas.addEventListener('mouseleave', () => this.onMouseUp());

        // Add touch event listeners
        this.canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            this.onMouseDown(this.getEventPos(e.touches[0]));
        });
        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            this.onMouseMove(this.getEventPos(e.touches[0]));
        });
        this.canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.onMouseUp();
        });

        // Add controls with size display
        const controls = document.createElement('div');
        controls.style.cssText = 'margin-top: 1rem; display: flex; align-items: center; gap: 1rem;';
        controls.innerHTML = `
            <button type="button" class="btn btn-green" id="saveCrop">Save</button>
            <button type="button" class="btn btn-dark-gray" id="cancelCrop">Cancel</button>
            <span id="crop-size-display" style="color: #666; font-size: 0.9rem;">200x200px</span>
        `;

        this.previewContainer.appendChild(container);
        this.previewContainer.appendChild(controls);

        document.getElementById('saveCrop').addEventListener('click', () => this.saveCrop());
        document.getElementById('cancelCrop').addEventListener('click', () => this.cancelCrop());
    }

    getEventPos(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    getResizeHandle(pos) {
        const handleSize = this.resizeHandleSize;
        const half = handleSize / 2;
        
        // Check corners
        const corners = [
            { x: this.cropX - half, y: this.cropY - half, cursor: 'nw-resize', dx: -1, dy: -1 },
            { x: this.cropX + this.cropSize - half, y: this.cropY - half, cursor: 'ne-resize', dx: 1, dy: -1 },
            { x: this.cropX - half, y: this.cropY + this.cropSize - half, cursor: 'sw-resize', dx: -1, dy: 1 },
            { x: this.cropX + this.cropSize - half, y: this.cropY + this.cropSize - half, cursor: 'se-resize', dx: 1, dy: 1 }
        ];
        
        for (const corner of corners) {
            if (pos.x >= corner.x && pos.x <= corner.x + handleSize &&
                pos.y >= corner.y && pos.y <= corner.y + handleSize) {
                return corner;
            }
        }
        return null;
    }

    onMouseDown(pos) {
        const handle = this.getResizeHandle(pos);
        
        if (handle) {
            this.isResizing = true;
            this.resizeHandle = handle;
            this.dragStartX = pos.x;
            this.dragStartY = pos.y;
            this.startCropX = this.cropX;
            this.startCropY = this.cropY;
            this.startCropSize = this.cropSize;
        } else if (pos.x >= this.cropX && pos.x <= this.cropX + this.cropSize &&
                   pos.y >= this.cropY && pos.y <= this.cropY + this.cropSize) {
            this.isDragging = true;
            this.dragStartX = pos.x - this.cropX;
            this.dragStartY = pos.y - this.cropY;
        }
    }

    onMouseMove(pos) {
        // Update cursor
        const handle = this.getResizeHandle(pos);
        if (handle) {
            this.canvas.style.cursor = handle.cursor;
        } else if (pos.x >= this.cropX && pos.x <= this.cropX + this.cropSize &&
                   pos.y >= this.cropY && pos.y <= this.cropY + this.cropSize) {
            this.canvas.style.cursor = 'move';
        } else {
            this.canvas.style.cursor = 'default';
        }

        if (this.isResizing) {
            const dx = pos.x - this.dragStartX;
            const dy = pos.y - this.dragStartY;
            
            // Calculate new size based on diagonal movement
            const diagonal = (dx * this.resizeHandle.dx + dy * this.resizeHandle.dy) * 1.5;
            let newSize = this.startCropSize + diagonal;
            
            // Constrain size
            newSize = Math.max(this.minCropSize, Math.min(this.maxCropSize, newSize));
            
            // Adjust position to keep centered when resizing
            const sizeDiff = newSize - this.startCropSize;
            this.cropX = this.startCropX - (sizeDiff / 2) * this.resizeHandle.dx;
            this.cropY = this.startCropY - (sizeDiff / 2) * this.resizeHandle.dy;
            this.cropSize = newSize;
            
            // Keep within bounds
            this.cropX = Math.max(0, Math.min(this.cropX, this.canvas.width - this.cropSize));
            this.cropY = Math.max(0, Math.min(this.cropY, this.canvas.height - this.cropSize));
            
            // Update size display
            const sizeDisplay = document.getElementById('crop-size-display');
            if (sizeDisplay) {
                sizeDisplay.textContent = `${Math.round(this.cropSize)}x${Math.round(this.cropSize)}px`;
            }
            
            this.draw();
        } else if (this.isDragging) {
            this.cropX = Math.max(0, Math.min(pos.x - this.dragStartX, this.canvas.width - this.cropSize));
            this.cropY = Math.max(0, Math.min(pos.y - this.dragStartY, this.canvas.height - this.cropSize));
            this.draw();
        }
    }

    onMouseUp() {
        this.isDragging = false;
        this.isResizing = false;
        this.resizeHandle = null;
    }

    draw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Draw image
        this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);

        // Draw dark overlay
        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.65)';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Create clipping region for crop area
        this.ctx.save();
        this.ctx.beginPath();
        this.ctx.rect(this.cropX, this.cropY, this.cropSize, this.cropSize);
        this.ctx.clip();
        
        // Draw the image again in the crop area (bright/clear)
        this.ctx.drawImage(this.image, 0, 0, this.canvas.width, this.canvas.height);
        this.ctx.restore();

        // Draw white border around crop area
        this.ctx.strokeStyle = '#fff';
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(this.cropX, this.cropY, this.cropSize, this.cropSize);

        // Draw grid lines (rule of thirds)
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.5)';
        this.ctx.lineWidth = 1;
        this.ctx.setLineDash([5, 5]);
        
        // Vertical lines
        const thirdX = this.cropSize / 3;
        this.ctx.beginPath();
        this.ctx.moveTo(this.cropX + thirdX, this.cropY);
        this.ctx.lineTo(this.cropX + thirdX, this.cropY + this.cropSize);
        this.ctx.moveTo(this.cropX + thirdX * 2, this.cropY);
        this.ctx.lineTo(this.cropX + thirdX * 2, this.cropY + this.cropSize);
        this.ctx.stroke();
        
        // Horizontal lines
        const thirdY = this.cropSize / 3;
        this.ctx.beginPath();
        this.ctx.moveTo(this.cropX, this.cropY + thirdY);
        this.ctx.lineTo(this.cropX + this.cropSize, this.cropY + thirdY);
        this.ctx.moveTo(this.cropX, this.cropY + thirdY * 2);
        this.ctx.lineTo(this.cropX + this.cropSize, this.cropY + thirdY * 2);
        this.ctx.stroke();
        
        this.ctx.setLineDash([]);

        // Draw resize handles
        const handleSize = this.resizeHandleSize;
        const half = handleSize / 2;
        const handles = [
            { x: this.cropX, y: this.cropY },
            { x: this.cropX + this.cropSize, y: this.cropY },
            { x: this.cropX, y: this.cropY + this.cropSize },
            { x: this.cropX + this.cropSize, y: this.cropY + this.cropSize }
        ];
        
        handles.forEach(handle => {
            // White square handle
            this.ctx.fillStyle = '#fff';
            this.ctx.fillRect(handle.x - half, handle.y - half, handleSize, handleSize);
            // Dark border
            this.ctx.strokeStyle = '#333';
            this.ctx.lineWidth = 1;
            this.ctx.strokeRect(handle.x - half, handle.y - half, handleSize, handleSize);
        });
    }

    saveCrop() {
        const cropCanvas = document.createElement('canvas');
        cropCanvas.width = 200;
        cropCanvas.height = 200;
        const cropCtx = cropCanvas.getContext('2d');

        const sourceX = this.cropX / this.scale;
        const sourceY = this.cropY / this.scale;
        const sourceSize = this.cropSize / this.scale;

        cropCtx.drawImage(
            this.image,
            sourceX, sourceY, sourceSize, sourceSize,
            0, 0, 200, 200
        );

        cropCanvas.toBlob((blob) => {
            const formData = new FormData();
            formData.append('action', 'update_photo');
            formData.append('profile_photo', blob, 'profile.jpg');

            fetch('/account/settings', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    const cropperModal = document.getElementById('modal-image-cropper');
                    if (cropperModal) {
                        cropperModal.remove();
                    }
                    document.body.dispatchEvent(new Event('refreshProfileModal'));
                    const headerImg = document.getElementById('header-profile-img');
                    if (headerImg) {
                        headerImg.src = headerImg.src.split('?')[0] + '?t=' + Date.now();
                    }
                    document.body.dispatchEvent(new CustomEvent('globalMessagePopupUpdate', {
                        detail: { type: 'success', message: 'Profile photo updated successfully' }
                    }));
                } else {
                    alert(data.message || 'Failed to update profile photo');
                }
            })
            .catch(error => {
                alert('Failed to update profile photo: ' + error.message);
            });
        }, 'image/jpeg', 0.9);
    }

    cancelCrop() {
        if (this.fileInput) {
            this.fileInput.value = '';
        }
        this.previewContainer.innerHTML = '';
    }
}


// ImageCropper helper for creating cropper from a File object
function ImageCropperFromFile(file, previewContainerId) {
    // Create a minimal cropper instance
    var cropper = new ImageCropper('__dummy_input__', previewContainerId);
    
    // Set the file and trigger processing
    cropper.fileInput = null; // No file input element
    
    // Read the file and create interface
    var reader = new FileReader();
    reader.onload = function(e) {
        cropper.createCropInterface(e.target.result);
    };
    reader.readAsDataURL(file);
    
    return cropper;
}

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
            htmx.ajax('GET', `/notifications/list/${filter}`, { 
                target: contentContainer, 
                swap: 'innerHTML' 
            });
        }
    }

    function getCsrfToken() {
        // Try to get from panel-modal data attribute
        const panelModal = document.getElementById('panel-modal-notifications');
        if (panelModal && panelModal.dataset.csrfToken) return panelModal.dataset.csrfToken;
        
        // Try from meta tag
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        
        // Try from body data attribute
        const body = document.body;
        if (body && body.dataset.csrfToken) return body.dataset.csrfToken;
        
        return '';
    }

    function getCsrfTokenFromItem(item) {
        // Get from the notification item itself
        if (item && item.dataset.csrfToken) return item.dataset.csrfToken;
        
        // Fallback to general getCsrfToken
        return getCsrfToken();
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
            
            fetch('/notifications/mark-read', {
                method: 'POST',
                body: formData
            }).then(response => {
                console.log('Mark as read response:', response.status);
                // Trigger refresh
                document.body.dispatchEvent(new CustomEvent('notificationsUpdate'));
            }).catch(err => {
                console.error('Error marking as read:', err);
            });
        }
    }

    function handleMarkAsReadClick(event) {
        const btn = event.target.closest('[data-mark-read]');
        if (!btn) return;
        
        event.stopPropagation();
        const notificationId = btn.getAttribute('data-notification-id');
        const item = btn.closest('.notification-item');
        
        htmx.ajax('POST', '/notifications/mark-read', {
            vals: { notification_id: notificationId, csrf_token: getCsrfTokenFromItem(item) },
            swap: 'none'
        });
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    ModalManager.init();
    SmallPopupManager.init();
    PanelModalManager.init();
    GlobalMessagePopup.init();
    TinyMCEManager.init();
    
    // Process HTMX attributes on panel-modal triggers after HTMX is loaded
    if (typeof htmx !== 'undefined') {
        document.querySelectorAll('[data-panel-modal]').forEach(el => {
            htmx.process(el);
        });
    }
});