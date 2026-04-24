// DOM Elements
const darkToggle = document.getElementById('darkmode');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const mobileBtn = document.getElementById('mobileMenuBtn');
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const fileInput = document.getElementById('manuscript');
const fileNameSpan = document.getElementById('file-name');

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

// Sidebar toggle
function toggleSidebar() {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', toggleSidebar);

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('show')) {
        toggleSidebar();
    }
});

// Avatar Dropdown
if (avatarBtn && dropdownMenu) {
    avatarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });
}

document.addEventListener('click', (e) => {
    if (dropdownMenu && !avatarBtn?.contains(e.target)) {
        dropdownMenu.classList.remove('show');
    }
});

// File input display
if (fileInput && fileNameSpan) {
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileNameSpan.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + this.files[0].name;
            fileNameSpan.style.color = '#10b981';
        } else {
            fileNameSpan.innerHTML = '';
        }
    });
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validate co-author emails
function validateCoAuthorEmails(emailsString) {
    if (!emailsString.trim()) return { valid: true, errors: [] };
    
    const emails = emailsString.split(',').map(e => e.trim()).filter(e => e);
    const errors = [];
    
    emails.forEach(email => {
        if (!isValidEmail(email)) {
            errors.push(`"${email}" is not a valid email address`);
        }
    });
    
    return { valid: errors.length === 0, errors };
}

// Form validation
const submissionForm = document.getElementById('submissionForm');
if (submissionForm) {
    submissionForm.addEventListener('submit', function(e) {
        const title = document.getElementById('title');
        const abstract = document.getElementById('abstract');
        const file = document.getElementById('manuscript');
        const inviteEmails = document.getElementById('invite_emails');
        
        // Validate title
        if (title && title.value.trim().length < 5) {
            e.preventDefault();
            alert('Thesis title must be at least 5 characters.');
            title.focus();
            return false;
        }
        
        // Validate abstract
        if (abstract && abstract.value.trim().length < 50) {
            e.preventDefault();
            alert('Abstract must be at least 50 characters.');
            abstract.focus();
            return false;
        }
        
        // Validate file
        if (file && (!file.files || file.files.length === 0)) {
            e.preventDefault();
            alert('Please select a manuscript file (PDF).');
            return false;
        }
        
        if (file && file.files.length > 0) {
            const fileType = file.files[0].type;
            if (fileType !== 'application/pdf') {
                e.preventDefault();
                alert('Only PDF files are allowed.');
                return false;
            }
            
            const fileSize = file.files[0].size;
            if (fileSize > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must not exceed 10MB.');
                return false;
            }
        }
        
        // Validate co-author emails (optional but if provided should be valid)
        if (inviteEmails && inviteEmails.value.trim()) {
            const emailValidation = validateCoAuthorEmails(inviteEmails.value);
            if (!emailValidation.valid) {
                e.preventDefault();
                alert('Invalid email addresses found:\n' + emailValidation.errors.join('\n'));
                inviteEmails.focus();
                return false;
            }
        }
    });
}

console.log('Submission Page Initialized - Department Exclusive Notifications & Co-author Email Invitations');