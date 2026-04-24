// Check for pending invitations
function checkPendingInvitations() {
    console.log('Checking for pending invitations...');
}

// Auto-hide messages
const messages = document.querySelectorAll('.alert-success, .alert-error');
messages.forEach(msg => {
    setTimeout(() => {
        msg.style.opacity = '0';
        setTimeout(() => msg.remove(), 300);
    }, 5000);
});

console.log('Check Invitations Page Initialized');