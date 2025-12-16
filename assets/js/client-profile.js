document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.getElementById('edit-profile-btn');
    const saveContainer = document.getElementById('save-btn-container');
    const profileForm = document.getElementById('profile-form');
    const phoneInput = document.getElementById('phone');
    const phoneError = document.getElementById('phone-error');
    const editableFields = ['fullName', 'phone', 'dateOfBirth', 'height', 'weight', 'gender'];

    // Edit Toggle Logic
    if (editBtn) {
        editBtn.addEventListener('click', (e) => {
            e.preventDefault(); // Prevent default if it's in a form context
            const isEditable = !document.getElementById('fullName').readOnly;
            
            if (isEditable) {
                // Currently editable, switching to read-only (Cancel)
                editableFields.forEach(id => {
                    const el = document.getElementById(id);
                    if(el) {
                        el.readOnly = true;
                        el.disabled = true; // For select inputs
                    }
                });
                if(saveContainer) saveContainer.classList.add('d-none');
                editBtn.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Profile';
                editBtn.classList.remove('btn-danger');
                editBtn.classList.add('btn-primary');
                // Reload to reset values
                location.reload(); 
            } else {
                // Currently read-only, switching to editable
                editableFields.forEach(id => {
                    const el = document.getElementById(id);
                    if(el) {
                        el.readOnly = false;
                        el.disabled = false; // For select inputs
                    }
                });
                if(saveContainer) saveContainer.classList.remove('d-none');
                editBtn.innerHTML = '<i class="fas fa-times me-2"></i>Cancel';
                editBtn.classList.remove('btn-primary');
                editBtn.classList.add('btn-danger');
            }
        });
    }

    // Client-side Validation on Submit
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            // ... (validation logic remains same) ...
            
            // Check if phone field is editable (meaning we are in edit mode)
            if (phoneInput && !phoneInput.readOnly) {
                const phoneValue = phoneInput.value.trim();
                const phoneRegex = /^01\d-\d{3,4} \d{4}$/;

                if (phoneValue && !phoneRegex.test(phoneValue)) {
                    e.preventDefault(); // Stop submission
                    
                    // Show error
                    phoneInput.classList.add('is-invalid');
                    if (phoneError) {
                        phoneError.classList.remove('d-none');
                        phoneError.style.display = 'block'; 
                    }
                    
                    // Focus for user
                    phoneInput.focus();
                } else {
                    // Valid
                    phoneInput.classList.remove('is-invalid');
                    if (phoneError) phoneError.classList.add('d-none');
                }
            }
        });
    }

    // Real-time validation removal (optional UX improvement)
    if (phoneInput) {
        // ... (phone input logic remains same) ...
    }

    // Password Visibility Toggle
    // ... (password toggle remains same) ...

    // Profile Picture Auto-Upload
    const profileInput = document.getElementById('profilePicture');
    if(profileInput) {
        profileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Enable gender field temporarily so it gets submitted
                const genderSelect = document.getElementById('gender');
                if(genderSelect) genderSelect.disabled = false;
                
                document.getElementById('profile-form').submit();
            }
        });
    }

    // Toast Feedback
    const feedbackMessage = window.profileConfig.feedbackMessage;
    const feedbackType = window.profileConfig.feedbackType;
    if (feedbackMessage) {
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${feedbackType === 'success' ? 'success' : 'danger'} border-0 position-fixed top-0 end-0 p-3 m-3`;
        toastEl.style.zIndex = '1100';
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${feedbackMessage}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toastEl);
        new bootstrap.Toast(toastEl).show();
    }
});