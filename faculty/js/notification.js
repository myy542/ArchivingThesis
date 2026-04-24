// Dark mode toggle (if present)
const toggle = document.getElementById('darkmode');
if (toggle) {
    toggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', toggle.checked);
    });
    if (localStorage.getItem('darkMode') === 'true') {
        toggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Click on notification item to view
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.closest('a') || e.target.closest('.btn-mark')) return;
        
        const thesisId = this.getAttribute('data-thesis-id');
        if (thesisId && thesisId > 0 && thesisId != '0') {
            window.location.href = 'reviewThesis.php?id=' + thesisId;
        }
    });
});

console.log('Notification Page Initialized');