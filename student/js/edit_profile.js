// Dark mode toggle
const toggle = document.getElementById('darkmode');
if (toggle) {
    toggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', toggle.checked);
    });

    const savedMode = localStorage.getItem('darkMode');
    if (savedMode === 'true') {
        toggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Avatar dropdown
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');

if (avatarBtn) {
    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });
}

window.addEventListener('click', function() {
    if (dropdownMenu && dropdownMenu.classList.contains('show')) {
        dropdownMenu.classList.remove('show');
    }
});

if (dropdownMenu) {
    dropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// Mobile menu toggle
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

if (mobileBtn) {
    mobileBtn.addEventListener('click', function() {
        toggleSidebar();
    });
}

// Three-line menu for desktop
const hamburgerBtn = document.getElementById('hamburgerBtn');
if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function() {
        toggleSidebar();
    });
}

function toggleSidebar() {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
    
    // Update icons
    const mobileIcon = mobileBtn?.querySelector('i');
    if (mobileIcon) {
        if (sidebar.classList.contains('show')) {
            mobileIcon.classList.remove('fa-bars');
            mobileIcon.classList.add('fa-times');
        } else {
            mobileIcon.classList.remove('fa-times');
            mobileIcon.classList.add('fa-bars');
        }
    }
    
    const hamburgerIcon = hamburgerBtn?.querySelector('i');
    if (hamburgerIcon) {
        if (sidebar.classList.contains('show')) {
            hamburgerIcon.classList.remove('fa-bars');
            hamburgerIcon.classList.add('fa-times');
        } else {
            hamburgerIcon.classList.remove('fa-times');
            hamburgerIcon.classList.add('fa-bars');
        }
    }
}

// Close sidebar when clicking on overlay
if (overlay) {
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        
        // Reset icons
        const mobileIcon = mobileBtn?.querySelector('i');
        if (mobileIcon) {
            mobileIcon.classList.remove('fa-times');
            mobileIcon.classList.add('fa-bars');
        }
        
        const hamburgerIcon = hamburgerBtn?.querySelector('i');
        if (hamburgerIcon) {
            hamburgerIcon.classList.remove('fa-times');
            hamburgerIcon.classList.add('fa-bars');
        }
    });
}

// Close sidebar when clicking a link (for mobile)
const navLinks = document.querySelectorAll('.nav-link');
navLinks.forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            
            const mobileIcon = mobileBtn?.querySelector('i');
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }
            
            const hamburgerIcon = hamburgerBtn?.querySelector('i');
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
            }
        }
    });
});

// File input display filename
document.getElementById('profile_picture')?.addEventListener('change', function(e) {
    const fileName = e.target.files.length > 0 ? e.target.files[0].name : 'No file chosen';
    document.querySelector('.file-name').textContent = fileName;
});