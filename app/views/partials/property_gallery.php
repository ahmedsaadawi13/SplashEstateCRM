<?php
// FILE: /app/views/partials/property_gallery.php
/**
 * Property Image Gallery Component
 * Include this in property view to display image gallery
 *
 * Required variables:
 * - $propertyId: Property ID
 * - $images: Array of property images (optional, will be loaded via AJAX if not provided)
 */

$propertyId = isset($propertyId) ? $propertyId : null;
$images = isset($images) ? $images : array();
$canEdit = isset($canEdit) ? $canEdit : true;
?>

<div class="property-gallery-container">
    <div class="gallery-header">
        <h3>Property Images</h3>
        <?php if ($canEdit): ?>
        <button type="button" class="btn btn-primary" onclick="showUploadModal()">
            📷 Upload Images
        </button>
        <?php endif; ?>
    </div>

    <div id="propertyGallery" class="property-gallery">
        <?php if (empty($images)): ?>
        <p class="no-images">No images uploaded yet.</p>
        <?php else: ?>
        <?php foreach ($images as $image): ?>
        <div class="gallery-item" data-image-id="<?php echo $image['id']; ?>">
            <img src="<?php echo BASE_URL . '/' . $image['file_path']; ?>"
                 alt="<?php echo View::escape($image['caption'] ?: $image['file_name']); ?>"
                 onclick="openLightbox(<?php echo $image['id']; ?>)">

            <?php if ($image['is_primary']): ?>
            <span class="primary-badge">Primary</span>
            <?php endif; ?>

            <?php if ($canEdit): ?>
            <div class="gallery-item-actions">
                <button type="button" class="btn-icon" onclick="setPrimaryImage(<?php echo $image['id']; ?>)"
                        title="Set as primary">
                    ⭐
                </button>
                <button type="button" class="btn-icon" onclick="editCaption(<?php echo $image['id']; ?>, '<?php echo View::escape($image['caption']); ?>')"
                        title="Edit caption">
                    ✏️
                </button>
                <button type="button" class="btn-icon btn-danger" onclick="deleteImage(<?php echo $image['id']; ?>)"
                        title="Delete">
                    🗑️
                </button>
            </div>
            <?php endif; ?>

            <?php if ($image['caption']): ?>
            <div class="gallery-item-caption">
                <?php echo View::escape($image['caption']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeUploadModal()">&times;</span>
        <h3>Upload Property Images</h3>

        <form id="imageUploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="imageFiles">Select Images (Max 10 images, 5MB each)</label>
                <input type="file" id="imageFiles" name="images[]" accept="image/*" multiple
                       class="form-control">
                <small class="form-text">Supported formats: JPG, PNG, GIF, WebP</small>
            </div>

            <div id="imagePreview" class="image-preview"></div>

            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="uploadImages()">
                    Upload Images
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">
                    Cancel
                </button>
            </div>
        </form>

        <div id="uploadProgress" style="display: none;">
            <div class="progress-bar">
                <div id="progressFill" class="progress-fill"></div>
            </div>
            <p id="progressText">Uploading...</p>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lightbox" style="display: none;" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <div class="lightbox-content" onclick="event.stopPropagation()">
        <img id="lightboxImage" src="" alt="">
        <div class="lightbox-caption" id="lightboxCaption"></div>
        <div class="lightbox-nav">
            <button class="lightbox-btn prev" onclick="navigateLightbox(-1)">❮</button>
            <button class="lightbox-btn next" onclick="navigateLightbox(1)">❯</button>
        </div>
    </div>
</div>

<!-- Caption Edit Modal -->
<div id="captionModal" class="modal" style="display: none;">
    <div class="modal-content modal-sm">
        <span class="close" onclick="closeCaptionModal()">&times;</span>
        <h3>Edit Image Caption</h3>

        <form id="captionForm" onsubmit="saveCaptionsubmit(event)">
            <input type="hidden" id="captionImageId">

            <div class="form-group">
                <label for="captionText">Caption</label>
                <input type="text" id="captionText" class="form-control"
                       placeholder="Enter image caption">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeCaptionModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.property-gallery-container {
    margin: 20px 0;
}

.gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.gallery-header h3 {
    margin: 0;
    color: #2c3e50;
}

.property-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.gallery-item {
    position: relative;
    aspect-ratio: 4/3;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: transform 0.2s;
}

.gallery-item:hover {
    transform: scale(1.05);
}

.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.primary-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #f39c12;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.gallery-item-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 5px;
    opacity: 0;
    transition: opacity 0.2s;
}

.gallery-item:hover .gallery-item-actions {
    opacity: 1;
}

.btn-icon {
    background: rgba(255,255,255,0.9);
    border: none;
    padding: 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background 0.2s;
}

.btn-icon:hover {
    background: white;
}

.btn-icon.btn-danger:hover {
    background: #e74c3c;
    color: white;
}

.gallery-item-caption {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 8px;
    font-size: 13px;
}

.no-images {
    text-align: center;
    padding: 40px;
    color: #95a5a6;
}

.image-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 10px;
    margin: 15px 0;
}

.preview-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 4px;
    overflow: hidden;
}

.preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.progress-bar {
    width: 100%;
    height: 30px;
    background: #ecf0f1;
    border-radius: 15px;
    overflow: hidden;
    margin: 15px 0;
}

.progress-fill {
    height: 100%;
    background: #3498db;
    transition: width 0.3s;
    width: 0%;
}

/* Lightbox */
.lightbox {
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.95);
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-close {
    position: absolute;
    top: 20px;
    right: 40px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.lightbox-content img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
}

.lightbox-caption {
    color: white;
    text-align: center;
    padding: 15px;
    font-size: 16px;
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    width: calc(100% + 100px);
    left: -50px;
    display: flex;
    justify-content: space-between;
    transform: translateY(-50%);
}

.lightbox-btn {
    background: rgba(255,255,255,0.3);
    color: white;
    border: none;
    padding: 15px 20px;
    font-size: 24px;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.2s;
}

.lightbox-btn:hover {
    background: rgba(255,255,255,0.5);
}

.modal-sm {
    max-width: 400px;
}

@media (max-width: 768px) {
    .property-gallery {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }

    .lightbox-nav {
        width: 100%;
        left: 0;
    }
}
</style>

<script>
const PROPERTY_ID = <?php echo $propertyId; ?>;
const BASE_URL = '<?php echo BASE_URL; ?>';
let currentImages = <?php echo json_encode($images); ?>;
let currentLightboxIndex = 0;

// Show upload modal
function showUploadModal() {
    document.getElementById('uploadModal').style.display = 'flex';
    document.getElementById('imageFiles').value = '';
    document.getElementById('imagePreview').innerHTML = '';
}

// Close upload modal
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
}

// Preview selected images
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('imageFiles');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';

            const files = Array.from(e.target.files);
            files.slice(0, 10).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    div.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                    preview.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });
    }
});

// Upload images
function uploadImages() {
    const fileInput = document.getElementById('imageFiles');
    const files = fileInput.files;

    if (files.length === 0) {
        alert('Please select images to upload');
        return;
    }

    if (files.length > 10) {
        alert('Maximum 10 images allowed');
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('images[]', files[i]);
    }

    // Show progress
    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('imageUploadForm').style.display = 'none';

    fetch(BASE_URL + '/propertygallery/upload/' + PROPERTY_ID, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Uploaded ${data.uploaded} image(s)`);
            closeUploadModal();
            reloadGallery();
        } else {
            alert('Error: ' + (data.error || 'Upload failed'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to upload images');
    })
    .finally(() => {
        document.getElementById('uploadProgress').style.display = 'none';
        document.getElementById('imageUploadForm').style.display = 'block';
    });
}

// Reload gallery
function reloadGallery() {
    fetch(BASE_URL + '/propertygallery/getImages/' + PROPERTY_ID)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentImages = data.images;
                renderGallery(data.images);
            }
        })
        .catch(error => console.error('Error:', error));
}

// Render gallery
function renderGallery(images) {
    const gallery = document.getElementById('propertyGallery');
    if (images.length === 0) {
        gallery.innerHTML = '<p class="no-images">No images uploaded yet.</p>';
        return;
    }

    gallery.innerHTML = images.map((img, index) => `
        <div class="gallery-item" data-image-id="${img.id}">
            <img src="${BASE_URL}/${img.file_path}" alt="${img.caption || img.file_name}"
                 onclick="openLightbox(${index})">
            ${img.is_primary ? '<span class="primary-badge">Primary</span>' : ''}
            <div class="gallery-item-actions">
                <button type="button" class="btn-icon" onclick="setPrimaryImage(${img.id})" title="Set as primary">⭐</button>
                <button type="button" class="btn-icon" onclick="editCaption(${img.id}, '${img.caption || ''}')" title="Edit caption">✏️</button>
                <button type="button" class="btn-icon btn-danger" onclick="deleteImage(${img.id})" title="Delete">🗑️</button>
            </div>
            ${img.caption ? `<div class="gallery-item-caption">${img.caption}</div>` : ''}
        </div>
    `).join('');
}

// Open lightbox
function openLightbox(index) {
    currentLightboxIndex = index;
    const image = currentImages[index];

    document.getElementById('lightboxImage').src = BASE_URL + '/' + image.file_path;
    document.getElementById('lightboxCaption').textContent = image.caption || image.file_name;
    document.getElementById('lightbox').style.display = 'flex';
}

// Close lightbox
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}

// Navigate lightbox
function navigateLightbox(direction) {
    event.stopPropagation();
    currentLightboxIndex = (currentLightboxIndex + direction + currentImages.length) % currentImages.length;
    const image = currentImages[currentLightboxIndex];

    document.getElementById('lightboxImage').src = BASE_URL + '/' + image.file_path;
    document.getElementById('lightboxCaption').textContent = image.caption || image.file_name;
}

// Set primary image
function setPrimaryImage(imageId) {
    if (!confirm('Set this as the primary image?')) return;

    fetch(BASE_URL + '/propertygallery/setPrimary', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image_id: imageId, property_id: PROPERTY_ID })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            reloadGallery();
        } else {
            alert('Error: ' + (data.error || 'Failed to set primary'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to set primary image');
    });
}

// Delete image
function deleteImage(imageId) {
    if (!confirm('Are you sure you want to delete this image?')) return;

    fetch(BASE_URL + '/propertygallery/deleteImage/' + imageId, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            reloadGallery();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete image');
    });
}

// Edit caption
function editCaption(imageId, currentCaption) {
    document.getElementById('captionModal').style.display = 'flex';
    document.getElementById('captionImageId').value = imageId;
    document.getElementById('captionText').value = currentCaption || '';
}

// Close caption modal
function closeCaptionModal() {
    document.getElementById('captionModal').style.display = 'none';
}

// Save caption
function saveCaption(event) {
    event.preventDefault();

    const imageId = document.getElementById('captionImageId').value;
    const caption = document.getElementById('captionText').value;

    fetch(BASE_URL + '/propertygallery/updateCaption', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image_id: imageId, caption: caption })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeCaptionModal();
            reloadGallery();
        } else {
            alert('Error: ' + (data.error || 'Failed to save'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save caption');
    });
}

// Keyboard navigation for lightbox
document.addEventListener('keydown', function(e) {
    const lightbox = document.getElementById('lightbox');
    if (lightbox.style.display === 'flex') {
        if (e.key === 'ArrowLeft') {
            navigateLightbox(-1);
        } else if (e.key === 'ArrowRight') {
            navigateLightbox(1);
        } else if (e.key === 'Escape') {
            closeLightbox();
        }
    }
});
</script>
