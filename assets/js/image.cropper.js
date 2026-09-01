/**
 * Image Cropper - profile photo cropping UI.
 *
 * Only loaded by the account settings modal (views/core/partial/modal.profile.php).
 * Depends on http.js (Csrf, Http) and core.js (HTMX_EVENTS).
 */

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
            Http.toastError('Only JPG/JPEG images are allowed');
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

            Http.postForm(APP_ROUTES.ACCOUNT_SETTINGS, formData, {
                errorMessage: 'Failed to update profile photo'
            })
            .then(data => {
                if (data.success) {
                    const cropperModal = document.getElementById('modal-image-cropper');
                    if (cropperModal) {
                        cropperModal.remove();
                    }
                    document.body.dispatchEvent(new Event(HTMX_EVENTS.REFRESH_PROFILE_MODAL));
                    const headerImg = document.getElementById('header-profile-img');
                    if (headerImg) {
                        headerImg.src = headerImg.src.split('?')[0] + '?t=' + Date.now();
                    }
                    document.body.dispatchEvent(new CustomEvent(HTMX_EVENTS.GLOBAL_MESSAGE, {
                        detail: { type: 'success', message: 'Profile photo updated successfully' }
                    }));
                } else {
                    Http.toastError(data.message || 'Failed to update profile photo');
                }
            })
            .catch(() => {
                // Toast already shown by Http helper
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
