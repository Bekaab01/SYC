// Debug script to check wizard functionality
console.log('Debug: Starting wizard debug...');

// Check if wizard container exists
const wizard = document.querySelector('.wizard-container');
console.log('Wizard container found:', !!wizard);

// Check if required elements exist
if (wizard) {
    const nextBtn = wizard.querySelector('#wizard-next');
    const cargoDesc = wizard.querySelector('#cargo_description');
    const weightInput = wizard.querySelector('#weight_value');

    console.log('Next button found:', !!nextBtn);
    console.log('Cargo description field found:', !!cargoDesc);
    console.log('Weight input field found:', !!weightInput);

    // Check current values
    if (cargoDesc) console.log('Cargo description value:', '"' + cargoDesc.value + '"');
    if (weightInput) console.log('Weight value:', '"' + weightInput.value + '"');

    // Test validation manually
    function testValidation() {
        console.log('Testing validation...');

        // Test required fields
        const requiredFields = wizard.querySelectorAll('[required]');
        console.log('Required fields found:', requiredFields.length);

        let valid = true;
        requiredFields.forEach(field => {
            const isEmpty = !field.value.trim();
            console.log(`Field ${field.id}: value="${field.value}", empty=${isEmpty}`);
            if (isEmpty) valid = false;
        });

        // Test cargo details validation
        const cargoType = wizard.querySelector('#cargo_type').value;
        const cargoTypeSearch = wizard.querySelector('#cargo_type_search').value;
        const weight = wizard.querySelector('#weight_value').value;

        console.log('Cargo type hidden:', '"' + cargoType + '"');
        console.log('Cargo type search:', '"' + cargoTypeSearch + '"');
        console.log('Weight:', '"' + weight + '"');

        const finalCargoType = cargoType || cargoTypeSearch || 'General';
        console.log('Final cargo type:', '"' + finalCargoType + '"');

        const weightValid = weight && parseFloat(weight) > 0;
        console.log('Weight valid:', weightValid);

        const cargoValid = weightValid;
        console.log('Cargo validation result:', cargoValid);

        valid = valid && cargoValid;
        console.log('Overall validation result:', valid);

        return valid;
    }

    // Add debug click handler
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            console.log('Next button clicked');
            const isValid = testValidation();
            console.log('Validation result:', isValid);
            if (!isValid) {
                e.preventDefault();
                console.log('Preventing navigation due to validation failure');
            }
        });
    }

    // Run initial test
    testValidation();
} else {
    console.log('Wizard container not found - checking document structure...');
    console.log('Body children:', document.body.children.length);
    console.log('Document ready state:', document.readyState);
}

// Check if initPostLoadWizard function exists
console.log('initPostLoadWizard function exists:', typeof initPostLoadWizard === 'function');

// Check if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded - rechecking wizard...');
        const wizardAfterLoad = document.querySelector('.wizard-container');
        console.log('Wizard container after DOM load:', !!wizardAfterLoad);
    });
} else {
    console.log('DOM already loaded');
}

console.log('Debug script loaded successfully');
