// Password toggle functionality
const togglePassword = document.getElementById('togglePassword');
const toggleCPassword = document.getElementById('toggleCPassword');
const password = document.getElementById('password');
const cpassword = document.getElementById('cpassword');
const themeToggle = document.getElementById('themeToggle');

// Toggle password visibility
if (togglePassword && password) {
    togglePassword.addEventListener('click', () => {
        if (password.type === 'password') {
            password.type = 'text';
            togglePassword.textContent = 'visibility';
        } else {
            password.type = 'password';
            togglePassword.textContent = 'visibility_off';
        }
    });
}

if (toggleCPassword && cpassword) {
    toggleCPassword.addEventListener('click', () => {
        if (cpassword.type === 'password') {
            cpassword.type = 'text';
            toggleCPassword.textContent = 'visibility';
        } else {
            cpassword.type = 'password';
            toggleCPassword.textContent = 'visibility_off';
        }
    });
}

// Dark mode toggle
if (themeToggle) {
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        themeToggle.querySelector('span').textContent = 'light_mode';
    }
    
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const icon = themeToggle.querySelector('span');
        if (document.body.classList.contains('dark-mode')) {
            icon.textContent = 'light_mode';
            localStorage.setItem('darkMode', 'true');
        } else {
            icon.textContent = 'dark_mode';
            localStorage.setItem('darkMode', 'false');
        }
    });
}

// Auto-hide alerts
const alerts = document.querySelectorAll('.alert, .success');
alerts.forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }, 300);
    }, 5000);
});

// Form validation
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        let errors = [];
        
        const firstName = document.querySelector('input[name="first_name"]');
        const lastName = document.querySelector('input[name="last_name"]');
        const email = document.querySelector('input[name="email"]');
        const username = document.querySelector('input[name="username"]');
        const password = document.querySelector('input[name="password"]');
        const cpassword = document.querySelector('input[name="cpassword"]');
        const contact = document.querySelector('input[name="contact_number"]');
        const department = document.querySelector('select[name="department_id"]');
        
        if (!firstName.value.trim()) errors.push('First name is required.');
        if (!lastName.value.trim()) errors.push('Last name is required.');
        if (!email.value.trim()) errors.push('Email is required.');
        if (!username.value.trim()) errors.push('Username is required.');
        if (!password.value) errors.push('Password is required.');
        
        if (password && cpassword && password.value !== cpassword.value) {
            errors.push('Passwords do not match.');
        }
        if (password && password.value.length < 6 && password.value.length > 0) {
            errors.push('Password must be at least 6 characters.');
        }
        if (contact && contact.value) {
            const phoneRegex = /^[0-9]{10,11}$/;
            if (!phoneRegex.test(contact.value)) {
                errors.push('Contact number must be 10-11 digits.');
            }
        }
        if (email && email.value) {
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                errors.push('Please enter a valid email address.');
            }
        }
        if (!department.value) {
            errors.push('Please select a department.');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join('\n'));
        }
    });
}

console.log('Register page initialized');