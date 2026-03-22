// Dark mode handling
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
if (localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && prefersDark)) {
    document.body.classList.add('dark-mode');
}

// Optional: Add dark mode toggle if needed
document.addEventListener('keydown', function(e) {
    // Toggle dark mode with Ctrl+Shift+D
    if (e.ctrlKey && e.shiftKey && e.key === 'D') {
        e.preventDefault();
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-success, .alert-error');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Confirm delete actions
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this notification?')) {
            e.preventDefault();
        }
    });
});