// Post Load Wizard Functionality
function initPostLoadWizard() {
    console.log('=== initPostLoadWizard called ===');
    const wizard = document.querySelector('.wizard-container');
    console.log('Wizard container found:', !!wizard);
    if (!wizard) {
        console.log('Wizard container not found - exiting');
        return;
    }

    const steps = wizard.querySelectorAll('.wizard-step');
    const progressFill = wizard.querySelector('#progress-fill');
    const progressSteps = wizard.querySelectorAll('.progress-steps .step');
    const prevBtn = wizard.querySelector('#wizard-prev');
    const nextBtn = wizard.querySelector('#wizard-next');
    const publishBtn = wizard.querySelector('#wizard-publish');
    const stepIndicator = wizard.querySelector('#current-step-indicator');

    let currentStep = 1;
    const totalSteps = steps.length;

    // Initialize wizard
    function initWizard() {
        showStep(1);
        setupEventListeners();
        setupCargoTypeSearch();
        setupPricingModels();
        setupVehicleSelector();
        setupMapIntegration();
    }

    // Show specific step
    function showStep(step) {
        // Hide all steps
        steps.forEach(s => s.classList.remove('active'));
        progressSteps.forEach(s => {
            s.classList.remove('active', 'completed');
        });

        // Show current step
        const currentStepEl = wizard.querySelector(`.wizard-step[data-step="${step}"]`);
        if (currentStepEl) {
            currentStepEl.classList.add('active');
        }

        // Update progress bar
        const progress = ((step - 1) / (totalSteps - 1)) * 100;
        progressFill.style.width = progress + '%';

        // Update progress steps
        progressSteps.forEach((stepEl, index) => {
            const stepNum = index + 1;
            if (stepNum < step) {
                stepEl.classList.add('completed');
            } else if (stepNum === step) {
                stepEl.classList.add('active');
            }
        });

        // Update navigation
        prevBtn.style.display = step > 1 ? 'inline-flex' : 'none';
        nextBtn.style.display = step < totalSteps ? 'inline-flex' : 'none';
        publishBtn.style.display = step === totalSteps ? 'inline-flex' : 'none';

        // Update step indicator
        stepIndicator.textContent = `Step ${step} of ${totalSteps}`;

        currentStep = step;
    }

    // Navigation handlers
    function setupEventListeners() {
        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        });

        nextBtn.addEventListener('click', (e) => {
            console.log('Next button clicked - Step:', currentStep);
            const isValid = validateStep(currentStep);
            console.log('Validation result:', isValid);
            if (isValid) {
                showStep(currentStep + 1);
            } else {
                console.log('Validation failed - preventing navigation');
                e.preventDefault();
            }
        });

        // Handle form submission via AJAX
        publishBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Publish button clicked - submitting form via AJAX');

            const isValid = validateStep(4); // Final validation
            if (!isValid) {
                console.log('Final validation failed');
                return;
            }

            submitFormViaAjax();
        });
    }

    // Step validation
    function validateStep(step) {
        console.log(`Validating step ${step}`);
        const currentStepEl = wizard.querySelector(`.wizard-step[data-step="${step}"]`);
        const requiredFields = currentStepEl.querySelectorAll('[required]');
        let valid = true;

        console.log(`Found ${requiredFields.length} required fields`);
        requiredFields.forEach(field => {
            const fieldValue = field.value.trim();
            console.log(`Checking field ${field.id}: "${fieldValue}"`);
            if (!fieldValue) {
                console.log(`Field ${field.id} is empty - marking invalid`);
                field.style.borderColor = '#dc3545';
                field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.25)';
                valid = false;
            } else {
                field.style.borderColor = '';
                field.style.boxShadow = '';
            }
        });

        // Additional validations
        if (step === 1) {
            console.log('Running cargo details validation');
            const cargoValid = validateCargoDetails();
            console.log('Cargo details validation result:', cargoValid);
            valid = cargoValid && valid;
        } else if (step === 2) {
            console.log('Running route details validation');
            const routeValid = validateRouteDetails();
            console.log('Route details validation result:', routeValid);
            valid = routeValid && valid;
        } else if (step === 3) {
            console.log('Running vehicle specs validation');
            const vehicleValid = validateVehicleSpecs();
            console.log('Vehicle specs validation result:', vehicleValid);
            valid = vehicleValid && valid;
        } else if (step === 4) {
            console.log('Running pricing model validation');
            const pricingValid = validatePricingModel();
            console.log('Pricing model validation result:', pricingValid);
            valid = pricingValid && valid;
        }

        console.log(`Step ${step} validation final result:`, valid);
        return valid;
    }

    // Step-specific validations
    function validateCargoDetails() {
        console.log('=== validateCargoDetails called ===');
        const cargoDesc = wizard.querySelector('#cargo_description');
        const cargoType = wizard.querySelector('#cargo_type').value;
        const cargoTypeSearch = wizard.querySelector('#cargo_type_search').value;
        const weight = wizard.querySelector('#weight_value').value;

        console.log('Cargo description element:', cargoDesc);
        console.log('Cargo description value:', cargoDesc ? '"' + cargoDesc.value + '"' : 'null');
        console.log('Cargo type hidden:', '"' + cargoType + '"');
        console.log('Cargo type search:', '"' + cargoTypeSearch + '"');
        console.log('Weight value:', '"' + weight + '"');

        // Check cargo description specifically
        if (!cargoDesc || !cargoDesc.value.trim()) {
            console.log('Cargo description validation failed - empty');
            showError('Please enter a cargo description');
            return false;
        }

        // Use cargo_type if set, otherwise use search input, default to 'General'
        const finalCargoType = cargoType || cargoTypeSearch || 'General';
        console.log('Final cargo type:', '"' + finalCargoType + '"');

        // Set the hidden field if not set
        if (!cargoType) {
            wizard.querySelector('#cargo_type').value = finalCargoType;
        }

        if (!weight || parseFloat(weight) <= 0) {
            console.log('Weight validation failed - invalid weight');
            showError('Please enter a valid weight greater than 0');
            return false;
        }

        console.log('Cargo details validation passed');
        return true;
    }

    function validateRouteDetails() {
        const pickup = wizard.querySelector('#pickup_location').value;
        const dropoff = wizard.querySelector('#dropoff_location').value;

        if (!pickup || !dropoff) {
            showError('Please enter both pickup and dropoff locations');
            return false;
        }

        if (pickup.toLowerCase() === dropoff.toLowerCase()) {
            showError('Pickup and dropoff locations cannot be the same');
            return false;
        }

        return true;
    }

    function validateVehicleSpecs() {
        const selectedVehicle = wizard.querySelector('input[name="vehicle_type"]:checked');
        if (!selectedVehicle) {
            showError('Please select a vehicle type');
            return false;
        }
        return true;
    }

    function validatePricingModel() {
        const pricingModel = wizard.querySelector('input[name="pricing_model"]:checked');
        if (!pricingModel) {
            showError('Please select a pricing model');
            return false;
        }

        if (pricingModel.value === 'auction') {
            const floorPrice = wizard.querySelector('#floor_price').value;
            if (!floorPrice || floorPrice <= 0) {
                showError('Please enter a valid floor price for auction');
                return false;
            }
        }

        return true;
    }

    // Cargo type search functionality
    function setupCargoTypeSearch() {
        const searchInput = wizard.querySelector('#cargo_type_search');
        const dropdown = wizard.querySelector('#cargo_type_dropdown');
        const cargoTypeInput = wizard.querySelector('#cargo_type');
        const cargoCategoryInput = wizard.querySelector('#cargo_category');
        const attributesContainer = wizard.querySelector('#cargo_attributes .attribute-tags');

        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = dropdown.querySelectorAll('.dropdown-item');

            let hasVisible = false;
            items.forEach(item => {
                const type = item.querySelector('.cargo-type-name').textContent.toLowerCase();
                const category = item.querySelector('.cargo-category').textContent.toLowerCase();

                if (query.length === 0 || type.includes(query) || category.includes(query)) {
                    item.style.display = 'block';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });

            dropdown.style.display = hasVisible ? 'block' : 'none';
        });

        searchInput.addEventListener('focus', function() {
            const query = this.value.toLowerCase();
            const items = dropdown.querySelectorAll('.dropdown-item');

            let hasVisible = false;
            items.forEach(item => {
                const type = item.querySelector('.cargo-type-name').textContent.toLowerCase();
                const category = item.querySelector('.cargo-category').textContent.toLowerCase();

                if (query.length === 0 || type.includes(query) || category.includes(query)) {
                    item.style.display = 'block';
                    hasVisible = true;
                } else {
                    item.style.display = 'none';
                }
            });

            dropdown.style.display = hasVisible ? 'block' : 'none';
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Handle dropdown item selection
        dropdown.addEventListener('click', function(e) {
            const item = e.target.closest('.dropdown-item');
            if (!item) return;

            const type = item.dataset.type;
            const category = item.dataset.category;
            const attributes = item.dataset.attributes.split(',');

            searchInput.value = type;
            cargoTypeInput.value = type;
            cargoCategoryInput.value = category;

            // Show attributes
            attributesContainer.innerHTML = '';
            if (attributes.length > 0 && attributes[0] !== '') {
                attributes.forEach(attr => {
                    const tag = document.createElement('span');
                    tag.className = 'attribute-tag';
                    tag.textContent = attr;
                    attributesContainer.appendChild(tag);
                });
                attributesContainer.parentElement.style.display = 'block';
            } else {
                attributesContainer.parentElement.style.display = 'none';
            }

            dropdown.style.display = 'none';
        });
    }

    // Pricing model toggle
    function setupPricingModels() {
        const models = wizard.querySelectorAll('input[name="pricing_model"]');
        const auctionSettings = wizard.querySelector('#auction_settings');
        const directSettings = wizard.querySelector('#direct_settings');

        models.forEach(model => {
            model.addEventListener('change', function() {
                if (this.value === 'auction') {
                    auctionSettings.style.display = 'block';
                    directSettings.style.display = 'none';
                } else {
                    auctionSettings.style.display = 'none';
                    directSettings.style.display = 'block';
                }
            });
        });

        // Urgency toggle
        const urgencyRadios = wizard.querySelectorAll('input[name="urgency"]');
        const auctionDuration = wizard.querySelector('#auction_duration');

        urgencyRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                auctionDuration.textContent = this.value === 'flash' ? '2 hours' : '24 hours';
            });
        });
    }

    // Vehicle selector
    function setupVehicleSelector() {
        const vehicleOptions = wizard.querySelectorAll('.vehicle-option');

        vehicleOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all
                vehicleOptions.forEach(opt => opt.classList.remove('selected'));

                // Add selected class to clicked
                this.classList.add('selected');

                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    // Map integration (placeholder)
    function setupMapIntegration() {
        const pinPickupBtn = wizard.querySelector('#pin-pickup');
        const pinDropoffBtn = wizard.querySelector('#pin-dropoff');

        if (pinPickupBtn) {
            pinPickupBtn.addEventListener('click', function() {
                // Placeholder for map integration
                alert('Map integration would open here to pin pickup location');
            });
        }

        if (pinDropoffBtn) {
            pinDropoffBtn.addEventListener('click', function() {
                // Placeholder for map integration
                alert('Map integration would open here to pin dropoff location');
            });
        }
    }

    // Hub shortcuts
    wizard.addEventListener('click', function(e) {
        if (e.target.classList.contains('hub-shortcut')) {
            const location = e.target.dataset.location;
            const input = e.target.closest('.form-group').querySelector('input[type="text"]');
            if (input) {
                input.value = location;
            }
        }
    });

    // Error display
    function showError(message) {
        console.log('showError called with message:', message);

        // Remove existing error
        const existingError = wizard.querySelector('.wizard-error');
        if (existingError) {
            console.log('Removing existing error');
            existingError.remove();
        }

        // Create new error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'wizard-error';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        errorDiv.style.display = 'block';
        errorDiv.style.visibility = 'visible';

        console.log('Created error div:', errorDiv);
        console.log('Wizard navigation element:', wizard.querySelector('.wizard-navigation'));

        wizard.insertBefore(errorDiv, wizard.querySelector('.wizard-navigation'));

        // Force visibility check
        setTimeout(() => {
            console.log('Error div after insertion:', errorDiv);
            console.log('Error div display:', getComputedStyle(errorDiv).display);
            console.log('Error div visibility:', getComputedStyle(errorDiv).visibility);
        }, 100);

        // Auto-remove after 10 seconds (increased for debugging)
        setTimeout(() => {
            if (errorDiv.parentNode) {
                console.log('Auto-removing error after timeout');
                errorDiv.remove();
            }
        }, 10000);
    }

    // AJAX form submission
    function submitFormViaAjax() {
        console.log('=== submitFormViaAjax called ===');

        // Collect form data
        const formData = new FormData();

        // Get all form inputs
        const inputs = wizard.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) {
                    formData.append(input.name, input.value);
                }
            } else {
                formData.append(input.name, input.value);
            }
        });

        // Add action
        formData.append('publish_load', '1');

        console.log('Form data collected, sending AJAX request...');

        // Send AJAX request
        fetch('shipper-tabs/post_load.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response received:', response);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.status === 'success') {
                showSuccess('Load published successfully!');
                // Reset form or redirect
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showError(data.message || 'Failed to publish load');
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            showError('Network error occurred. Please try again.');
        });
    }

    // Success display
    function showSuccess(message) {
        console.log('showSuccess called with message:', message);

        // Remove existing messages
        const existingError = wizard.querySelector('.wizard-error');
        const existingSuccess = wizard.querySelector('.wizard-success');
        if (existingError) existingError.remove();
        if (existingSuccess) existingSuccess.remove();

        // Create success message
        const successDiv = document.createElement('div');
        successDiv.className = 'wizard-success';
        successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        successDiv.style.display = 'block';
        successDiv.style.visibility = 'visible';

        wizard.insertBefore(successDiv, wizard.querySelector('.wizard-navigation'));

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.remove();
            }
        }, 5000);
    }

    // Initialize
    initWizard();
}

// Make function globally available
window.initPostLoadWizard = initPostLoadWizard;
