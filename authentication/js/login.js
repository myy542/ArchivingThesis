// DOM Elements
const loginToggle = document.getElementById('login-toggle');
const loginPass = document.getElementById('password');
const themeToggle = document.getElementById('themeToggle');

// Password toggle functionality
if (loginToggle && loginPass) {
    loginToggle.addEventListener('click', () => {
        if (loginPass.type === 'password') {
            loginPass.type = 'text';
            loginToggle.textContent = 'visibility';
        } else {
            loginPass.type = 'password';
            loginToggle.textContent = 'visibility_off';
        }
    });
}

// Dark mode toggle functionality
if (themeToggle) {
    // Check localStorage for saved preference
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

// Auto-hide alerts after 5 seconds
const alerts = document.querySelectorAll('.message');
alerts.forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }, 300);
    }, 5000);
});

// Form validation
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        const username = document.getElementById('username');
        const password = document.getElementById('password');
        
        if (!username.value.trim()) {
            e.preventDefault();
            alert('Please enter username or email');
            username.focus();
            return false;
        }
        
        if (!password.value) {
            e.preventDefault();
            alert('Please enter password');
            password.focus();
            return false;
        }
    });
}

console.log('Login page initialized');