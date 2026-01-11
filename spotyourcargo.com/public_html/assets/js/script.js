document.addEventListener('DOMContentLoaded', function() {
    // Submenu toggle - only on chevron click
    const chevrons = document.querySelectorAll('.sidebar-chevron');
    chevrons.forEach(chevron => {
        chevron.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.closest('.sidebar-parent-toggle').classList.toggle('active');
        });
    });

    // Prevent toggle on parent link click (text area)
    const parentToggles = document.querySelectorAll('.sidebar-parent-toggle');
    parentToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
        });
    });

    // Mobile backdrop click to close sidebar
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.remove('active');
            backdrop.classList.remove('active');
        });
    }

    // Close sidebar on navigation (mobile) - disabled to keep sidebar open for submenu interactions
    // const navButtons = document.querySelectorAll('.sidebar-nav button[data-section]');
    // navButtons.forEach(button => {
    //     button.addEventListener('click', function() {
    //         if (window.innerWidth <= 768) {
    //             const sidebar = document.querySelector('.sidebar');
    //             const backdrop = document.querySelector('.sidebar-backdrop');
    //             sidebar.classList.remove('active');
    //             if (backdrop) backdrop.classList.remove('active');
    //         }
    //     });
    // });
});

// Utility functions for showing success and error messages
function showSuccessMessage(message) {
    console.log('=== SHOW SUCCESS MESSAGE ===');
    console.log('Creating custom success modal');

    // Create a custom modal that stays open until manually closed
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content success-modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Success</h3>
                <button class="modal-close" onclick="console.log('=== MODAL CLOSE VIA CLOSE BUTTON ==='); console.log('Success modal close button clicked'); this.closest('.modal').remove(); document.body.style.overflow = '';">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="success-content">
                    <i class="fas fa-check-circle"></i>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="console.log('=== MODAL CLOSE VIA CLOSE BUTTON ==='); console.log('Success modal close button clicked'); this.closest('.modal').remove(); document.body.style.overflow = '';">Close</button>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.querySelector('.modal');
    if (existingModal) {
        console.log('=== REMOVING EXISTING MODAL ===');
        console.log('Existing modal found and removed');
        existingModal.remove();
    }

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    // Add event listener for escape key
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            console.log('=== MODAL CLOSE VIA ESCAPE KEY ===');
            console.log('Success modal closed via escape key');
            modal.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}

function showErrorMessage(message) {
    console.log('=== SHOW ERROR MESSAGE ===');
    console.log('Creating custom error modal');

    // Create a custom modal that stays open until manually closed
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content error-modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Error</h3>
                <button class="modal-close" onclick="console.log('=== MODAL CLOSE VIA CLOSE BUTTON ==='); console.log('Error modal close button clicked'); this.closest('.modal').remove(); document.body.style.overflow = '';">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="error-content">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="console.log('=== MODAL CLOSE VIA CLOSE BUTTON ==='); console.log('Error modal close button clicked'); this.closest('.modal').remove(); document.body.style.overflow = '';">Close</button>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.querySelector('.modal');
    if (existingModal) {
        console.log('=== REMOVING EXISTING MODAL ===');
        console.log('Existing modal found and removed');
        existingModal.remove();
    }

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    // Add event listener for escape key
    const escapeHandler = (e) => {
        if (e.key === 'Escape') {
            console.log('=== MODAL CLOSE VIA ESCAPE KEY ===');
            console.log('Error modal closed via escape key');
            modal.remove();
            document.body.style.overflow = '';
            document.removeEventListener('keydown', escapeHandler);
        }
    };
    document.addEventListener('keydown', escapeHandler);
}
