<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Special Offers - Brioche & Brew Admin</title>
    <link href="css/admin_special.css" rel="stylesheet">
</head>
<body>
    <div class="admin-header">
        <h1>📢 Special Offers Management</h1>
        <p>Create and manage promotional offers for your customers</p>
    </div>

    <div class="container">
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="openAddModal()">+ Add New Offer</button>
            <a href="menucustomize.html" class="btn btn-secondary" style="text-decoration: none;">Back to Menu Management</a>
        </div>

        <div class="offers-grid" id="offersGrid">
            <p style="text-align: center; color: #666;">Loading offers...</p>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal" id="offerModal">
        <div class="modal-content">
            <h2 class="modal-header" id="modalTitle">Add New Offer</h2>
            <form id="offerForm">
                <input type="hidden" id="offerId">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" id="title" class="form-input" required placeholder="e.g., Weekend Brunch Special">
                </div>

                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <textarea id="description" class="form-textarea" required placeholder="Describe the offer details..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Badge Text *</label>
                    <input type="text" id="badge" class="form-input" required placeholder="e.g., HOT DEAL, LIMITED TIME">
                </div>

                <div class="form-group">
                    <label class="form-label">Original Price (₦) *</label>
                    <input type="number" id="originalPrice" class="form-input" step="0.01" required placeholder="10000">
                </div>

                <div class="form-group">
                    <label class="form-label">Discounted Price (₦) *</label>
                    <input type="number" id="discountedPrice" class="form-input" step="0.01" required placeholder="7000">
                </div>

                <div class="form-group">
                    <label class="form-label">Discount Percentage *</label>
                    <input type="number" id="discountPercentage" class="form-input" required placeholder="30">
                </div>

                <div class="form-group">
                    <label class="form-label">Valid From *</label>
                    <input type="date" id="validFrom" class="form-input" required>
                    <small style="color: #666; font-size: 12px;">Must be today or a future date</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Valid Until *</label>
                    <input type="date" id="validUntil" class="form-input" required>
                    <small style="color: #666; font-size: 12px;">Must be after the start date</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Display Order</label>
                    <select id="displayOrder" class="form-input">
                        <option value="0">First (Top Priority)</option>
                        <option value="1">Second</option>
                        <option value="2">Third</option>
                        <option value="3">Fourth</option>
                        <option value="4">Fifth</option>
                        <option value="5">Sixth</option>
                        <option value="6">Seventh</option>
                        <option value="7">Eighth</option>
                        <option value="8">Ninth</option>
                        <option value="9">Tenth</option>
                    </select>
                    <small style="color: #666; font-size: 12px;">Lower numbers appear first in the carousel</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Offer Image *</label>
                    <input type="file" id="offerImage" class="form-input" accept="image/*">
                    <img id="imagePreview" class="image-preview" style="display: none;">
                </div>

                <div class="form-group" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Offer</button>
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        let offers = [];
        let editingOfferId = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadOffers();
            setupImagePreview();
            setMinDateForInputs();
        });

        function setMinDateForInputs() {
            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];
            
            // Set minimum date for "Valid From" to today
            const validFromInput = document.getElementById('validFrom');
            validFromInput.setAttribute('min', today);
            
            // Add event listener to update "Valid Until" minimum when "Valid From" changes
            validFromInput.addEventListener('change', function() {
                const validUntilInput = document.getElementById('validUntil');
                // "Valid Until" must be at least the same as "Valid From"
                validUntilInput.setAttribute('min', this.value);
                
                // If "Valid Until" is already set but is before the new "Valid From", clear it
                if (validUntilInput.value && validUntilInput.value < this.value) {
                    validUntilInput.value = '';
                }
            });
        }

        function setupImagePreview() {
            document.getElementById('offerImage').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const preview = document.getElementById('imagePreview');
                        preview.src = event.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        async function loadOffers() {
            try {
                const response = await fetch('get_all_offers.php');
                const data = await response.json();

                if (data.success) {
                    offers = data.offers;
                    displayOffers();
                } else {
                    document.getElementById('offersGrid').innerHTML = '<p style="text-align: center; color: #666;">No offers found</p>';
                }
            } catch (error) {
                console.error('Error loading offers:', error);
                showToast('Error loading offers', 'error');
                document.getElementById('offersGrid').innerHTML = '<p style="text-align: center; color: #dc3545;">Error loading offers. Check console for details.</p>';
            }
        }

        function displayOffers() {
            const grid = document.getElementById('offersGrid');

            if (offers.length === 0) {
                grid.innerHTML = '<p style="text-align: center; color: #666;">No offers yet. Create your first offer!</p>';
                return;
            }

            let html = '';
            const today = new Date().toISOString().split('T')[0];

            offers.forEach(offer => {
                const validFrom = new Date(offer.validFrom).toLocaleDateString();
                const validUntil = new Date(offer.validUntil).toLocaleDateString();
                
                // Determine if offer is expired
                const isExpired = offer.validUntil < today;
                const statusClass = offer.isActive ? (isExpired ? 'status-expired' : 'status-active') : 'status-inactive';
                const statusText = offer.isActive ? (isExpired ? 'Expired' : 'Active') : 'Inactive';

                html += `
                    <div class="offer-card">
                        <img src="${offer.image}" alt="${offer.title}" class="offer-image" onerror="this.src='./image/placeholder.jpg'">
                        <div class="offer-content">
                            <div class="offer-badge">${offer.badge}</div>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                            <h3 class="offer-title">${offer.title}</h3>
                            <p class="offer-description">${offer.description}</p>
                            <div class="offer-price-info">
                                <span class="original-price">₦${offer.originalPrice.toLocaleString()}</span>
                                <span class="discounted-price">₦${offer.discountedPrice.toLocaleString()}</span>
                                <span class="discount-badge">${offer.discountPercentage}% OFF</span>
                            </div>
                            <div class="offer-dates">
                                📅 ${validFrom} - ${validUntil}
                            </div>
                            <div class="offer-actions">
                                <button class="btn btn-edit" onclick="editOffer(${offer.id})">Edit</button>
                                <button class="btn btn-toggle" onclick="toggleStatus(${offer.id}, ${offer.isActive})">${offer.isActive ? 'Deactivate' : 'Activate'}</button>
                                <button class="btn btn-delete" onclick="deleteOffer(${offer.id})">Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            grid.innerHTML = html;
        }

        function openAddModal() {
            editingOfferId = null;
            document.getElementById('modalTitle').textContent = 'Add New Offer';
            document.getElementById('offerForm').reset();
            document.getElementById('offerId').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('offerImage').required = true;
            
            // Reset date constraints for new offer
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('validFrom').setAttribute('min', today);
            document.getElementById('validUntil').setAttribute('min', today);
            
            document.getElementById('offerModal').classList.add('active');
        }

        function editOffer(id) {
            const offer = offers.find(o => String(o.id) === String(id));
            
            if (!offer) {
                alert('Offer not found!');
                console.error('Offer with ID', id, 'not found in:', offers);
                return;
            }

            editingOfferId = id;
            document.getElementById('modalTitle').textContent = 'Edit Offer';
            document.getElementById('offerId').value = offer.id;
            document.getElementById('title').value = offer.title;
            document.getElementById('description').value = offer.description;
            document.getElementById('badge').value = offer.badge;
            document.getElementById('originalPrice').value = offer.originalPrice;
            document.getElementById('discountedPrice').value = offer.discountedPrice;
            document.getElementById('discountPercentage').value = offer.discountPercentage;
            document.getElementById('validFrom').value = offer.validFrom;
            document.getElementById('validUntil').value = offer.validUntil;
            document.getElementById('displayOrder').value = offer.displayOrder || 0;
            
            // For editing, allow keeping existing dates (even if past)
            // but new dates must still be valid
            const today = new Date().toISOString().split('T')[0];
            const offerStartDate = offer.validFrom;
            
            // Set min date to the earlier of today or the offer's current start date
            const minDate = offerStartDate < today ? offerStartDate : today;
            document.getElementById('validFrom').setAttribute('min', minDate);
            document.getElementById('validUntil').setAttribute('min', offer.validFrom);
            
            const preview = document.getElementById('imagePreview');
            preview.src = offer.image;
            preview.style.display = 'block';
            document.getElementById('offerImage').required = false;

            document.getElementById('offerModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('offerModal').classList.remove('active');
            editingOfferId = null;
        }

        document.getElementById('offerForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            // Additional validation
            const validFrom = document.getElementById('validFrom').value;
            const validUntil = document.getElementById('validUntil').value;
            
            if (validUntil < validFrom) {
                showToast('End date must be after start date', 'error');
                return;
            }

            const imageFile = document.getElementById('offerImage').files[0];

            if (imageFile) {
                const reader = new FileReader();
                reader.onload = async function(event) {
                    await submitOffer(event.target.result);
                };
                reader.readAsDataURL(imageFile);
            } else if (editingOfferId) {
                await submitOffer(null);
            } else {
                showToast('Please select an image', 'error');
            }
        });

        async function submitOffer(imageData) {
            const offerData = {
                id: editingOfferId,
                title: document.getElementById('title').value,
                description: document.getElementById('description').value,
                badge: document.getElementById('badge').value,
                originalPrice: parseFloat(document.getElementById('originalPrice').value),
                discountedPrice: parseFloat(document.getElementById('discountedPrice').value),
                discountPercentage: parseInt(document.getElementById('discountPercentage').value),
                validFrom: document.getElementById('validFrom').value,
                validUntil: document.getElementById('validUntil').value,
                displayOrder: parseInt(document.getElementById('displayOrder').value),
                imageData: imageData
            };

            try {
                const response = await fetch('save_offer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(offerData)
                });

                const result = await response.json();

                if (result.success) {
                    showToast(editingOfferId ? 'Offer updated successfully!' : 'Offer added successfully!');
                    closeModal();
                    loadOffers();
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error saving offer:', error);
                showToast('Error saving offer: ' + error.message, 'error');
            }
        }

        async function toggleStatus(id, currentStatus) {
            if (!confirm(`Are you sure you want to ${currentStatus ? 'deactivate' : 'activate'} this offer?`)) {
                return;
            }

            try {
                const response = await fetch('toggle_offer_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id, isActive: !currentStatus })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Status updated successfully!');
                    loadOffers();
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error toggling status:', error);
                showToast('Error updating status: ' + error.message, 'error');
            }
        }

        async function deleteOffer(id) {
            if (!confirm('Are you sure you want to delete this offer? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('delete_offer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Offer deleted successfully!');
                    loadOffers();
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error deleting offer:', error);
                showToast('Error deleting offer: ' + error.message, 'error');
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.background = type === 'error' ? '#dc3545' : '#28a745';
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
    
    <style>
        /* Add styling for expired status badge */
        .status-expired {
            background: #ff9800;
            color: white;
        }
    </style>
</body>
</html>