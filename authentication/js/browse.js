// DOM Elements
const themeToggle = document.getElementById('themeToggle');
const modal = document.getElementById('loginModal');
const modalCloseBtn = document.getElementById('modalCloseBtn');
const viewButtons = document.querySelectorAll('.btn-view[data-action="view"]');
const downloadButtons = document.querySelectorAll('.btn-download[data-action="download"]');

// Modal functions
function showLoginModal() {
    if (modal) {
        modal.classList.add('show');
    }
}

function hideLoginModal() {
    if (modal) {
        modal.classList.remove('show');
    }
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (modal && event.target === modal) {
        hideLoginModal();
    }
});

// Close modal with close button
if (modalCloseBtn) {
    modalCloseBtn.addEventListener('click', hideLoginModal);
}

// Show login modal when non-logged in users click view/download buttons
if (!window.isLoggedIn) {
    if (viewButtons.length > 0) {
        viewButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                showLoginModal();
            });
        });
    }
    
    if (downloadButtons.length > 0) {
        downloadButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                showLoginModal();
            });
        });
    }
}

// Dark mode toggle
if (themeToggle) {
    // Check localStorage for saved preference
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
    }
    
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const icon = themeToggle.querySelector('i');
        
        if (document.body.classList.contains('dark-mode')) {
            if (icon) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
            localStorage.setItem('darkMode', 'true');
        } else {
            if (icon) {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
            localStorage.setItem('darkMode', 'false');
        }
    });
}

// Form validation for search
const searchForm = document.getElementById('searchForm');
if (searchForm) {
    searchForm.addEventListener('submit', function(e) {
        const searchInput = document.querySelector('.search-input');
        // Allow empty search (it will show all results)
        console.log('Searching for:', searchInput ? searchInput.value : '');
    });
}

// Add loading effect on pagination links
const paginationLinks = document.querySelectorAll('.pagination a.page-btn');
paginationLinks.forEach(link => {
    link.addEventListener('click', function() {
        // Optional: Add loading indicator
        console.log('Loading page:', this.href);
    });
});

// Initialize any tooltips or additional features
document.addEventListener('DOMContentLoaded', function() {
    console.log('Browse page initialized');
    console.log('User logged in:', window.isLoggedIn);
    console.log('Search params:', window.searchParams);
});