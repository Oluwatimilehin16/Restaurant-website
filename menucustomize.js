// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    
    let customizationCounter = 0;
    let imageData = null;
    let currentStep = 1;
    const totalSteps = 4;

    // Character counters
    const shortDesc = document.getElementById('shortDescription');
    const fullDesc = document.getElementById('fullDescription');
    const shortCounter = document.getElementById('shortCounter');
    const fullCounter = document.getElementById('fullCounter');

    if (shortDesc && shortCounter) {
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
    }

    if (fullDesc && fullCounter) {
        fullDesc.addEventListener('input', function() {
            const length = this.value.length;
            fullCounter.textContent = `${length} characters`;
        });
    }

    // Image preview
    const itemImageInput = document.getElementById('itemImage');
    if (itemImageInput) {
        itemImageInput.addEventListener('change', function(e) {
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
    }

    // Make functions global so they can be called from onclick attributes
    window.changeStep = function(direction) {
        console.log('Changing step from', currentStep, 'direction:', direction);
        
        // Validate current step before moving forward
        if (direction > 0 && !validateCurrentStep()) {
            return;
        }
        
        const newStep = currentStep + direction;
        
        if (newStep < 1 || newStep > totalSteps) {
            return;
        }
        
        // Hide current step
        const currentStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
        const currentProgressStep = document.querySelector(`.progress-step[data-step="${currentStep}"]`);
        
        if (currentStepElement) currentStepElement.classList.remove('active');
        if (currentProgressStep) currentProgressStep.classList.remove('active');
        
        // Show new step
        currentStep = newStep;
        const newStepElement = document.querySelector(`.form-step[data-step="${currentStep}"]`);
        const newProgressStep = document.querySelector(`.progress-step[data-step="${currentStep}"]`);
        
        if (newStepElement) {
            newStepElement.classList.add('active');
            console.log('Step', currentStep, 'is now active');
        } else {
            console.error('Could not find step element for step', currentStep);
        }
        
        if (newProgressStep) newProgressStep.classList.add('active');
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.skipStep = function() {
        if (currentStep === 4) {
            changeStep(1); // Move to next step without validation
        }
    };

    function updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const skipBtn = document.getElementById('skipBtn');
        
        if (!prevBtn || !nextBtn || !submitBtn || !skipBtn) return;
        
        // Previous button
        prevBtn.style.display = currentStep === 1 ? 'none' : 'inline-block';
        
        // Skip button (only on customization step)
        skipBtn.style.display = currentStep === 4 ? 'inline-block' : 'none';
        
        // Next/Submit buttons
        if (currentStep === totalSteps) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-block';
        } else {
            nextBtn.style.display = 'inline-block';
            submitBtn.style.display = 'none';
        }
    }

    function validateCurrentStep() {
        if (currentStep === 1) {
            const itemName = document.getElementById('itemName');
            const category = document.getElementById('category');
            const shortDescription = document.getElementById('shortDescription');
            const fullDescription = document.getElementById('fullDescription');
            
            if (!itemName || !itemName.value.trim()) {
                showToast('Please enter an item name', 'error');
                if (itemName) itemName.focus();
                return false;
            }
            if (!category || !category.value) {
                showToast('Please select a category', 'error');
                if (category) category.focus();
                return false;
            }
            if (!shortDescription || !shortDescription.value.trim()) {
                showToast('Please enter a short description', 'error');
                if (shortDescription) shortDescription.focus();
                return false;
            }
            if (!fullDescription || !fullDescription.value.trim()) {
                showToast('Please enter a full description', 'error');
                if (fullDescription) fullDescription.focus();
                return false;
            }
        } else if (currentStep === 2) {
            const basePrice = document.getElementById('basePrice');
            
            if (!basePrice || !basePrice.value || parseFloat(basePrice.value) <= 0) {
                showToast('Please enter a valid price', 'error');
                if (basePrice) basePrice.focus();
                return false;
            }
            if (!imageData) {
                showToast('Please upload an image', 'error');
                return false;
            }
        }
        
        return true;
    }

    window.addFeature = function() {
        const list = document.getElementById('specialFeaturesList');
        if (!list) return;
        
        const featureItem = document.createElement('div');
        featureItem.className = 'feature-item';
        featureItem.innerHTML = `
            <input type="text" placeholder="Add another special feature" class="special-feature">
            <button type="button" class="remove-btn" onclick="removeFeature(this)">×</button>
        `;
        list.appendChild(featureItem);
    };

    window.removeFeature = function(button) {
        const list = document.getElementById('specialFeaturesList');
        if (!list) return;
        
        if (list.children.length > 1) {
            button.parentElement.remove();
        }
    };

    window.addCustomizationGroup = function(type) {
        customizationCounter++;
        const groupId = `customization_${customizationCounter}`;
        const section = document.getElementById('customizationSection');
        const typeSelector = document.getElementById('typeSelector');
        
        if (!section) return;
        
        if (section.querySelector('p')) {
            section.innerHTML = '';
        }
        
        if (customizationCounter === 1 && typeSelector) {
            typeSelector.style.display = 'grid';
        }
        
        const isReplaceType = type === 'replace';
        const typeName = isReplaceType ? 'Replace Base Price' : 'Add-Ons (Extras)';
        const typeIcon = isReplaceType ? '🔄' : '➕';
        
        const basePriceElement = document.getElementById('basePrice');
        const basePrice = basePriceElement ? basePriceElement.value : '0';
        
        const customItem = document.createElement('div');
        customItem.className = 'customization-item';
        customItem.id = groupId;
        customItem.setAttribute('data-type', type);
        customItem.innerHTML = `
            <div class="customization-header">
                <h4>${typeIcon} Customization Group #${customizationCounter}</h4>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span class="customization-type-badge">${typeName}</span>
                    <button type="button" class="remove-btn" onclick="removeCustomizationGroup('${groupId}')" style="background: white; color: #8B0000;">×</button>
                </div>
            </div>

            ${isReplaceType ? `
                <div class="info-badge">
                    <div class="info-badge-title">
                        <span>ℹ️</span>
                        <span>How "Replace Base Price" Works</span>
                    </div>
                    <div class="info-badge-content">
                        Customer must choose ONE option. The price you set for that option becomes the FINAL price (your base price of ₦${basePrice} will be ignored). Perfect for sizes like Small/Medium/Large.
                    </div>
                </div>
            ` : `
                <div class="info-badge">
                    <div class="info-badge-title">
                        <span>ℹ️</span>
                        <span>How "Add-Ons" Works</span>
                    </div>
                    <div class="info-badge-content">
                        Customer can choose MULTIPLE items. Each selection ADDS to your base price of ₦${basePrice}. Use "0" for free add-ons or customizations that don't change the price.
                    </div>
                </div>
            `}

            <div class="form-group">
                <label class="form-label">Group Name <span class="required-asterisk">*</span></label>
                <div class="form-hint">${isReplaceType ? 'e.g., "Choose Size", "Select Portion"' : 'e.g., "Extra Toppings", "Add Proteins", "Premium Add-ons"'}</div>
                <input type="text" class="form-input customization-name" placeholder="What should customers see as the heading?">
            </div>

            ${!isReplaceType ? `
                <div class="form-row">
                    <div class="checkbox-group" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFE5E5 100%); padding: 12px; border-radius: 8px; border: 2px solid #8B0000;">
                        <input type="checkbox" id="required_${customizationCounter}" class="checkbox-input customization-required">
                        <label for="required_${customizationCounter}" style="font-weight: 600;">Customer MUST choose at least one (Required)</label>
                    </div>
                </div>
            ` : ''}

            <div class="form-group">
                <label class="form-label">Options</label>
                <div class="form-hint">
                    ${isReplaceType ? 
                        '⚠️ The price you enter here REPLACES your base price. One option will be selected by default.' : 
                        '💡 Enter the EXTRA cost to add (use 0 for free options). Customer can select multiple.'
                    }
                </div>
                <div class="options-container" id="options_${customizationCounter}">
                    <div class="option-row">
                        <input type="text" class="option-input option-name" placeholder="${isReplaceType ? 'e.g., Small, Medium, Large' : 'e.g., Extra Cheese, Bacon'}" style="flex: 2;">
                        <input type="number" class="option-input option-price price-input" placeholder="${isReplaceType ? 'Final Price' : 'Extra Cost'}" step="0.01" value="${isReplaceType ? '' : '0'}">
                        <div class="checkbox-group" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFE5E5 100%); padding: 8px 12px; border-radius: 8px; border: 2px solid #8B0000;">
                            <input type="checkbox" class="checkbox-input option-default" ${isReplaceType ? 'checked' : ''} onchange="handleDefaultChange(this, '${groupId}')">
                            <label style="margin: 0; font-weight: 600; white-space: nowrap;" title="${isReplaceType ? 'This option will be pre-selected' : 'This option will be pre-checked'}">Default</label>
                        </div>
                        <button type="button" class="remove-btn" onclick="removeOption(this, '${groupId}')">×</button>
                    </div>
                </div>
                <button type="button" class="add-btn" onclick="addOption('${groupId}')">+ Add Option</button>
            </div>

            <div class="simple-explanation">
                <strong>💡 What is "Default"?</strong><br>
                ${isReplaceType ? 
                    'The option marked as "Default" will be PRE-SELECTED when the customer opens this menu item. For "Replace Base Price", exactly ONE option must be default.' :
                    'Options marked as "Default" will be PRE-CHECKED (already added) when the customer opens this menu item. They can uncheck them if they don\'t want them.'
                }
            </div>
        `;
        
        section.appendChild(customItem);
        
        if (isReplaceType) {
            ensureOneDefault(groupId);
        }
    };

    window.handleDefaultChange = function(checkbox, groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        
        const isReplaceType = group.getAttribute('data-type') === 'replace';
        
        if (isReplaceType && checkbox.checked) {
            const allDefaults = group.querySelectorAll('.option-default');
            allDefaults.forEach(def => {
                if (def !== checkbox) {
                    def.checked = false;
                }
            });
        }
    };

    function ensureOneDefault(groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        
        const isReplaceType = group.getAttribute('data-type') === 'replace';
        
        if (!isReplaceType) return;
        
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

    window.removeCustomizationGroup = function(groupId) {
        if (confirm('Remove this entire customization group?')) {
            const group = document.getElementById(groupId);
            if (group) group.remove();
            
            const section = document.getElementById('customizationSection');
            if (section && section.children.length === 0) {
                section.innerHTML = '<p>No customizations added yet. Choose a type below to get started.</p>';
            }
        }
    };

    window.addOption = function(groupId) {
        const group = document.getElementById(groupId);
        if (!group) return;
        
        const isReplaceType = group.getAttribute('data-type') === 'replace';
        const container = document.querySelector(`#${groupId} .options-container`);
        if (!container) return;
        
        const optionRow = document.createElement('div');
        optionRow.className = 'option-row';
        optionRow.innerHTML = `
            <input type="text" class="option-input option-name" placeholder="${isReplaceType ? 'Option name' : 'Add-on name'}" style="flex: 2;">
            <input type="number" class="option-input option-price price-input" placeholder="${isReplaceType ? 'Final Price' : 'Extra Cost'}" step="0.01" value="${isReplaceType ? '' : '0'}">
            <div class="checkbox-group" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFE5E5 100%); padding: 8px 12px; border-radius: 8px; border: 2px solid #8B0000;">
                <input type="checkbox" class="checkbox-input option-default" onchange="handleDefaultChange(this, '${groupId}')">
                <label style="margin: 0; font-weight: 600; white-space: nowrap;">Default</label>
            </div>
            <button type="button" class="remove-btn" onclick="removeOption(this, '${groupId}')">×</button>
        `;
        container.appendChild(optionRow);
    };

    window.removeOption = function(btn, groupId) {
        const row = btn.parentElement;
        const container = document.querySelector(`#${groupId} .options-container`);
        
        if (container && container.children.length > 1) {
            const wasDefault = row.querySelector('.option-default').checked;
            row.remove();
            
            if (wasDefault) {
                const group = document.getElementById(groupId);
                if (group) {
                    const isReplaceType = group.getAttribute('data-type') === 'replace';
                    
                    if (isReplaceType) {
                        ensureOneDefault(groupId);
                    }
                }
            }
        }
    };

    // Form submission
    const menuForm = document.getElementById('menuItemForm');
    if (menuForm) {
        menuForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }
            
            try {
                const specialFeatures = [];
                document.querySelectorAll('.special-feature').forEach(input => {
                    if (input.value.trim()) {
                        specialFeatures.push(input.value.trim());
                    }
                });

                const availability = [];
                const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                days.forEach(day => {
                    const checkbox = document.getElementById(day);
                    if (checkbox && checkbox.checked) {
                        availability.push(day);
                    }
                });

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

                const customizations = [];
                document.querySelectorAll('.customization-item').forEach(item => {
                    const nameInput = item.querySelector('.customization-name');
                    const groupName = nameInput ? nameInput.value.trim() : '';
                    const customizationType = item.getAttribute('data-type');
                    const isReplaceType = customizationType === 'replace';
                    
                    const requiredCheckbox = item.querySelector('.customization-required');
                    const isRequired = isReplaceType ? true : (requiredCheckbox ? requiredCheckbox.checked : false);
                    const allowMultiple = !isReplaceType;
                    
                    const options = [];
                    
                    item.querySelectorAll('.option-row').forEach(row => {
                        const nameInput = row.querySelector('.option-name');
                        const priceInput = row.querySelector('.option-price');
                        const defaultCheckbox = row.querySelector('.option-default');
                        
                        const optionName = nameInput ? nameInput.value.trim() : '';
                        const additionalPrice = priceInput ? priceInput.value : '0';
                        const isDefault = defaultCheckbox ? defaultCheckbox.checked : false;
                        
                        if (optionName) {
                            options.push({ 
                                optionName: optionName, 
                                additionalPrice: additionalPrice ? parseFloat(additionalPrice) : 0,
                                isDefault: isDefault
                            });
                        }
                    });

                    if (groupName && options.length > 0) {
                        if (isReplaceType) {
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
                            customizationType: customizationType,
                            options: options 
                        });
                    }
                });

                const formData = {
                    itemName: document.getElementById('itemName').value.trim(),
                    category: document.getElementById('category').value,
                    shortDescription: document.getElementById('shortDescription').value.trim(),
                    fullDescription: document.getElementById('fullDescription').value.trim(),
                    specialFeatures: specialFeatures,
                    basePrice: parseFloat(document.getElementById('basePrice').value),
                    prepTime: document.getElementById('prepTime').value || null,
                    servingSize: document.getElementById('servingSize').value.trim() || null,
                    imageData: imageData,
                    availability: availability,
                    dietary: dietary,
                    customizations: customizations
                };

                console.log('Submitting form data:', formData);

                const response = await fetch('save_menu_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
    showToast('Menu item saved successfully!', 'success');
    setTimeout(() => {
        window.location.href = 'menu_management.html';
    }, 2000);
}       else {
                    showToast(result.message || 'Error saving menu item', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '💾 Save Menu Item';
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '💾 Save Menu Item';
                }
            }
        });
    }
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.className = 'toast show ' + type;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
    // Initialize
    updateNavigationButtons();
    
    // Debug: Log all form steps
    console.log('Form initialized');
    console.log('Step 1:', document.querySelector('.form-step[data-step="1"]'));
    console.log('Step 2:', document.querySelector('.form-step[data-step="2"]'));
    console.log('Step 3:', document.querySelector('.form-step[data-step="3"]'));
    console.log('Step 4:', document.querySelector('.form-step[data-step="4"]'));
});