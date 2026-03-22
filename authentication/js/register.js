// Form validation and enhancements
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const password = document.querySelector('input[name="password"]');
    const cpassword = document.querySelector('input[name="cpassword"]');
    const contact = document.querySelector('input[name="contact_number"]');
    const email = document.querySelector('input[name="email"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let errors = [];
            
            // Password validation
            if (password && cpassword) {
                if (password.value !== cpassword.value) {
                    errors.push('Password and Confirm Password do not match.');
                }
                if (password.value.length < 6 && password.value.length > 0) {
                    errors.push('Password must be at least 6 characters.');
                }
            }
            
            // Contact number validation
            if (contact && contact.value) {
                const phoneRegex = /^[0-9]{10,11}$/;
                if (!phoneRegex.test(contact.value)) {
                    errors.push('Contact number must be 10-11 digits and numeric only.');
                }
            }
            
            // Email validation
            if (email && email.value) {
                const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
                if (!emailRegex.test(email.value)) {
                    errors.push('Please enter a valid email address.');
                }
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            }
        });
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert, .success');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});