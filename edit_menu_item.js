let customizationCounter = 0;
let imageData = null;
let currentItemId = null;

// Character counters
const shortDesc = document.getElementById('shortDescription');
const fullDesc = document.getElementById('fullDescription');
const shortCounter = document.getElementById('shortCounter');
const fullCounter = document.getElementById('fullCounter');

shortDesc.addEventListener('input', function() {
    const length = this.value.length;
    shortCounter.textContent = `${length} / 150 characters`;
    
    if (length > 120) {
        shortCounter.classList.add('warning');
    } else {
        shortCounter.classList.remove('warning');
    }
    
    if (length >= 150) {
        shortCounter.classList.add('error');
    } else {
        shortCounter.classList.remove('error');
    }
});

fullDesc.addEventListener('input', function() {
    const length = this.value.length;
    fullCounter.textContent = `${length} characters`;
});

// Image preview
document.getElementById('itemImage').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            imageData = event.target.result;
            const preview = document.getElementById('imagePreview');
            preview.src = imageData;
            preview.classList.add('show');
            document.getElementById('uploadArea').classList.add('has-image');
        };
        reader.readAsDataURL(file);
    }
});

// Load menu item data on page load
document.addEventListener('DOMContentLoaded', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const itemId = urlParams.get('id');
    
    if (!itemId) {
        showToast('No menu item ID provided', 'error');
        setTimeout(() => window.location.href = 'menu_management.html', 2000);
        return;
    }
    
    currentItemId = itemId;
    document.getElementById('itemId').value = itemId;
    
    await loadMenuItem(itemId);
});

async function loadMenuItem(itemId) {
    try {
        const response = await fetch(`get_menu_item_details.php?id=${itemId}`);
        const data = await response.json();
        
        if (!data.success) {
            showToast('Failed to load menu item', 'error');
            return;
        }
        
        const item = data.item;
        
        // Fill basic info
        document.getElementById('itemName').value = item.itemName;
        document.getElementById('category').value = item.category;
        document.getElementById('shortDescription').value = item.shortDescription || '';
        document.getElementById('fullDescription').value = item.fullDescription || '';
        document.getElementById('basePrice').value = item.basePrice;
        document.getElementById('prepTime').value = item.prepTime || '';
        document.getElementById('servingSize').value = item.servingSize || '';
        
        // Update character counters
        shortCounter.textContent = `${item.shortDescription?.length || 0} / 150 characters`;
        fullCounter.textContent = `${item.fullDescription?.length || 0} characters`;
        
        // Show existing image
        if (item.imagePath) {
            document.getElementById('existingImagePath').value = item.imagePath;
            const preview = document.getElementById('imagePreview');
            preview.src = item.imagePath;
            preview.classList.add('show');
            document.getElementById('uploadArea').classList.add('has-image');
        }
        
        // Load special features
        const featuresList = document.getElementById('specialFeaturesList');
        featuresList.innerHTML = '';
        if (item.specialFeatures && item.specialFeatures.length > 0) {
            item.specialFeatures.forEach(feature => {
                addFeatureWithValue(feature);
            });
        } else {
            addFeature();
        }
        
        // Load availability
        if (item.availability && item.availability.length > 0) {
            item.availability.forEach(day => {
                const checkbox = document.getElementById(day);
                if (checkbox) checkbox.checked = true;
            });
        }
        
        // Load dietary tags
        if (item.dietary && item.dietary.length > 0) {
            item.dietary.forEach(tag => {
                const checkbox = document.getElementById(tag);
                if (checkbox) checkbox.checked = true;
            });
        }
        
        // Load customizations
        const section = document.getElementById('customizationSection');
        if (item.customizations && item.customizations.length > 0) {
            section.innerHTML = '';
            item.customizations.forEach(custom => {
                addCustomizationGroupWithData(custom);
            });
        }
        
    } catch (error) {
        console.error('Error loading menu item:', error);
        showToast('Error loading menu item', 'error');
    }
}

function addFeature() {
    const list = document.getElementById('specialFeaturesList');
    const featureItem = document.createElement('div');
    featureItem.className = 'feature-item';
    featureItem.innerHTML = `
        <input type="text" placeholder="Add special feature" class="special-feature">
        <button type="button" class="remove-btn" onclick="removeFeature(this)">×</button>
    `;
    list.appendChild(featureItem);
}

function addFeatureWithValue(value) {
    const list = document.getElementById('specialFeaturesList');
    const featureItem = document.createElement('div');
    featureItem.className = 'feature-item';
    featureItem.innerHTML = `
        <input type="text" placeholder="Add special feature" class="special-feature" value="${value}">
        <button type="button" class="remove-btn" onclick="removeFeature(this)">×</button>
    `;
    list.appendChild(featureItem);
}

function removeFeature(button) {
    const list = document.getElementById('specialFeaturesList');
    if (list.children.length > 1) {
        button.parentElement.remove();
    }
}

function addCustomizationGroup() {
    customizationCounter++;
    const groupId = `customization_${customizationCounter}`;
    
    const customizationItem = document.createElement('div');
    customizationItem.className = 'customization-item';
    customizationItem.id = groupId;
    
    customizationItem.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">❓ Question to ask customers <span class="required-asterisk">*</span></label>
                <input type="text" class="form-input customization-name" placeholder='e.g., "What size would you like?" or "Choose your bread"'>
                <div class="form-hint">This is what customers will see when ordering</div>
            </div>
        </div>
        <div class="form-row">
            <div class="checkbox-group" style="background: #FFF3CD; padding: 12px; border-radius: 8px; border: 2px solid #FFC107;">
                <input type="checkbox" id="required_${customizationCounter}" class="checkbox-input customization-required" onchange="handleRequiredChange('${groupId}')">
                <label for="required_${customizationCounter}" style="font-weight: bold;">⚠️ Customer MUST choose an option (Required)</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="multiple_${customizationCounter}" class="checkbox-input customization-multiple" onchange="handleMultipleChange('${groupId}')">
                <label for="multiple_${customizationCounter}">Allow customer to pick multiple choices</label>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">📋 Answer Choices</label>
            <div class="form-hint">Add the options customers can choose from. Mark one as "Default" for Quick Add.</div>
            <div class="options-container" id="options_${customizationCounter}">
                <div class="option-row">
                    <input type="text" class="option-input option-name" placeholder='e.g., "Small" or "White Bread"' style="flex: 2;">
                    <input type="number" class="option-input price-input option-price" placeholder="Extra cost (₦)" step="0.01" value="0">
                    <div class="checkbox-group" style="background: #D4EDDA; padding: 8px 12px; border-radius: 8px;">
                        <input type="checkbox" class="checkbox-input option-default" onchange="handleDefaultChange(this, '${groupId}')">
                        <label style="font-weight: 600; color: #155724;">⭐ Default</label>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeOption(this, '${groupId}')">×</button>
                </div>
            </div>
            <button type="button" class="add-btn" onclick="addOption('${groupId}')">+ Add Another Choice</button>
        </div>
        <button type="button" class="remove-btn" style="margin-top: 15px; padding: 12px 20px;" onclick="removeCustomizationGroup('${groupId}')">🗑️ Remove This Question</button>
    `;
    
    const section = document.getElementById('customizationSection');
    if (section.children.length === 1 && section.children[0].tagName === 'P') {
        section.innerHTML = '';
    }
    section.appendChild(customizationItem);
}

function addCustomizationGroupWithData(custom) {
    customizationCounter++;
    const groupId = `customization_${customizationCounter}`;
    
    const customizationItem = document.createElement('div');
    customizationItem.className = 'customization-item';
    customizationItem.id = groupId;
    
    let optionsHtml = '';
    custom.options.forEach(opt => {
        optionsHtml += `
            <div class="option-row">
                <input type="text" class="option-input option-name" placeholder='e.g., "Small" or "White Bread"' style="flex: 2;" value="${opt.name}">
                <input type="number" class="option-input price-input option-price" placeholder="Extra cost (₦)" step="0.01" value="${opt.price}">
                <div class="checkbox-group" style="background: #D4EDDA; padding: 8px 12px; border-radius: 8px;">
                    <input type="checkbox" class="checkbox-input option-default" ${opt.isDefault ? 'checked' : ''} onchange="handleDefaultChange(this, '${groupId}')">
                    <label style="font-weight: 600; color: #155724;">⭐ Default</label>
                </div>
                <button type="button" class="remove-btn" onclick="removeOption(this, '${groupId}')">×</button>
            </div>
        `;
    });
    
    customizationItem.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">❓ Question to ask customers <span class="required-asterisk">*</span></label>
                <input type="text" class="form-input customization-name" placeholder='e.g., "What size would you like?" or "Choose your bread"' value="${custom.name}">
                <div class="form-hint">This is what customers will see when ordering</div>
            </div>
        </div>
        <div class="form-row">
            <div class="checkbox-group" style="background: #FFF3CD; padding: 12px; border-radius: 8px; border: 2px solid #FFC107;">
                <input type="checkbox" id="required_${customizationCounter}" class="checkbox-input customization-required" ${custom.required ? 'checked' : ''} onchange="handleRequiredChange('${groupId}')">
                <label for="required_${customizationCounter}" style="font-weight: bold;">⚠️ Customer MUST choose an option (Required)</label>
            </div>
            <div class="checkbox-group">
                <input type="checkbox" id="multiple_${customizationCounter}" class="checkbox-input customization-multiple" ${custom.multiple ? 'checked' : ''} onchange="handleMultipleChange('${groupId}')">
                <label for="multiple_${customizationCounter}">Allow customer to pick multiple choices</label>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">📋 Answer Choices</label>
            <div class="form-hint">Add the options customers can choose from. Mark one as "Default" for Quick Add.</div>
            <div class="options-container" id="options_${customizationCounter}">
                ${optionsHtml}
            </div>
            <button type="button" class="add-btn" onclick="addOption('${groupId}')">+ Add Another Choice</button>
        </div>
        <button type="button" class="remove-btn" style="margin-top: 15px; padding: 12px 20px;" onclick="removeCustomizationGroup('${groupId}')">🗑️ Remove This Question</button>
    `;
    
    document.getElementById('customizationSection').appendChild(customizationItem);
}

function handleRequiredChange(groupId) {
    const group = document.getElementById(groupId);
    const isRequired = group.querySelector('.customization-required').checked;
    const allowMultiple = group.querySelector('.customization-multiple').checked;
    
    if (isRequired && !allowMultiple) {
        ensureOneDefault(groupId);
    }
}

function handleMultipleChange(groupId) {
    const group = document.getElementById(groupId);
    const allowMultiple = group.querySelector('.customization-multiple').checked;
    const isRequired = group.querySelector('.customization-required').checked;
    
    if (!allowMultiple && isRequired) {
        ensureOneDefault(groupId);
    }
}

function handleDefaultChange(checkbox, groupId) {
    const group = document.getElementById(groupId);
    const allowMultiple = group.querySelector('.customization-multiple').checked;
    const isRequired = group.querySelector('.customization-required').checked;
    
    if (isRequired && !allowMultiple && checkbox.checked) {
        const allDefaults = group.querySelectorAll('.option-default');
        allDefaults.forEach(def => {
            if (def !== checkbox) {
                def.checked = false;
            }
        });
    }
}

function ensureOneDefault(groupId) {
    const group = document.getElementById(groupId);
    const allDefaults = group.querySelectorAll('.option-default');
    const checkedDefaults = Array.from(allDefaults).filter(def => def.checked);
    
    if (checkedDefaults.length === 0 && allDefaults.length > 0) {
        allDefaults[0].checked = true;
    } else if (checkedDefaults.length > 1) {
        checkedDefaults.forEach((def, index) => {
            if (index > 0) def.checked = false;
        });
    }
}

function addOption(groupId) {
    const container = document.querySelector(`#${groupId} .options-container`);
    const optionRow = document.createElement('div');
    optionRow.className = 'option-row';
    optionRow.innerHTML = `
        <input type="text" class="option-input option-name" placeholder='e.g., "Medium" or "Wheat Bread"' style="flex: 2;">
        <input type="number" class="option-input price-input option-price" placeholder="Extra cost (₦)" step="0.01" value="0">
        <div class="checkbox-group" style="background: #D4EDDA; padding: 8px 12px; border-radius: 8px;">
            <input type="checkbox" class="checkbox-input option-default" onchange="handleDefaultChange(this, '${groupId}')">
            <label style="font-weight: 600; color: #155724;">⭐ Default</label>
        </div>
        <button type="button" class="remove-btn" onclick="removeOption(this, '${groupId}')">×</button>
    `;
    container.appendChild(optionRow);
}

function removeOption(button, groupId) {
    const row = button.parentElement;
    const wasDefault = row.querySelector('.option-default').checked;
    row.remove();
    
    if (wasDefault) {
        const group = document.getElementById(groupId);
        const isRequired = group.querySelector('.customization-required').checked;
        const allowMultiple = group.querySelector('.customization-multiple').checked;
        
        if (isRequired && !allowMultiple) {
            ensureOneDefault(groupId);
        }
    }
}

function removeCustomizationGroup(groupId) {
    document.getElementById(groupId).remove();
    const section = document.getElementById('customizationSection');
    if (section.children.length === 0) {
        section.innerHTML = '<p style="text-align: center; color: #666; font-style: italic;">No customizations yet. Skip this if the item has no options.</p>';
    }
}

document.getElementById('editMenuItemForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = e.submitter || document.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';
    
    try {
        // Collect special features
        const specialFeatures = [];
        document.querySelectorAll('.special-feature').forEach(input => {
            if (input.value.trim()) {
                specialFeatures.push(input.value.trim());
            }
        });

        // Collect availability - use Set to ensure uniqueness
        const availability = [];
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        const availabilitySet = new Set();
        
        days.forEach(day => {
            const checkbox = document.getElementById(day);
            if (checkbox && checkbox.checked) {
                availabilitySet.add(day);
            }
        });
        availability.push(...availabilitySet);

        // Collect dietary tags - use Set to ensure uniqueness
        const dietary = [];
        const dietaryTypes = ['vegetarian', 'vegan', 'glutenFree', 'dairyFree', 'nutFree', 'spicy'];
        const dietarySet = new Set();
        
        dietaryTypes.forEach(type => {
            const checkbox = document.getElementById(type);
            if (checkbox && checkbox.checked) {
                dietarySet.add(type);
            }
        });
        dietary.push(...dietarySet);

        // Collect customizations
        const customizations = [];
        document.querySelectorAll('.customization-item').forEach(item => {
            const groupName = item.querySelector('.customization-name').value.trim();
            const isRequired = item.querySelector('.customization-required').checked;
            const allowMultiple = item.querySelector('.customization-multiple').checked;
            const options = [];
            
            item.querySelectorAll('.option-row').forEach(row => {
                const optionName = row.querySelector('.option-name').value.trim();
                const additionalPrice = row.querySelector('.option-price').value;
                const isDefault = row.querySelector('.option-default').checked;
                if (optionName) {
                    options.push({ 
                        optionName: optionName, 
                        additionalPrice: additionalPrice ? parseFloat(additionalPrice) : 0,
                        isDefault: isDefault
                    });
                }
            });

            if (groupName && options.length > 0) {
                // Validate: if required and not multiple, ensure exactly one default
                if (isRequired && !allowMultiple) {
                    const defaultCount = options.filter(opt => opt.isDefault).length;
                    if (defaultCount === 0) {
                        options[0].isDefault = true;
                    } else if (defaultCount > 1) {
                        let foundFirst = false;
                        options.forEach(opt => {
                            if (opt.isDefault && !foundFirst) {
                                foundFirst = true;
                            } else if (opt.isDefault) {
                                opt.isDefault = false;
                            }
                        });
                    }
                }
                
                customizations.push({ 
                    groupName: groupName, 
                    isRequired: isRequired, 
                    allowMultiple: allowMultiple, 
                    options: options 
                });
            }
        });

        const formData = {
            id: currentItemId,
            itemName: document.getElementById('itemName').value.trim(),
            category: document.getElementById('category').value,
            shortDescription: document.getElementById('shortDescription').value.trim(),
            fullDescription: document.getElementById('fullDescription').value.trim(),
            specialFeatures: specialFeatures,
            basePrice: parseFloat(document.getElementById('basePrice').value),
            prepTime: document.getElementById('prepTime').value || null,
            servingSize: document.getElementById('servingSize').value.trim() || null,
            imageData: imageData,
            existingImagePath: document.getElementById('existingImagePath').value,
            availability: availability,
            dietary: dietary,
            customizations: customizations
        };

        console.log('Submitting update:', formData);

        const response = await fetch('update_menu_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Menu item updated successfully!');
            setTimeout(() => {
                window.location.href = 'menu_management.html';
            }, 2000);
        } else {
            showToast(result.message || 'Error updating menu item', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Changes';
    }
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}