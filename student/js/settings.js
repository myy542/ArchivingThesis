// DOM Elements
const darkToggle = document.getElementById('darkmode');

// Dark Mode
if (darkToggle) {
    darkToggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', darkToggle.checked);
    });
    if (localStorage.getItem('darkMode') === 'true') {
        darkToggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Auto-hide messages after 5 seconds
const messages = document.querySelectorAll('.message');
messages.forEach(msg => {
    setTimeout(() => {
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 300);
    }, 5000);
});

console.log('Settings Page Initialized');