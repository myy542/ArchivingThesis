// Dark mode toggle
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

// Avatar dropdown
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');

if (avatarBtn) {
  avatarBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
    if (notificationDropdown) notificationDropdown.classList.remove('show');
  });
}

// Notification dropdown
if (notificationBell) {
  notificationBell.addEventListener('click', function(e) {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');
    if (dropdownMenu) dropdownMenu.classList.remove('show');
  });
}

// Close dropdowns when clicking outside
window.addEventListener('click', function() {
  if (notificationDropdown) notificationDropdown.classList.remove('show');
  if (dropdownMenu) dropdownMenu.classList.remove('show');
});

if (notificationDropdown) {
  notificationDropdown.addEventListener('click', function(e) {
    e.stopPropagation();
  });
}

if (dropdownMenu) {
  dropdownMenu.addEventListener('click', function(e) {
    e.stopPropagation();
  });
}

// Mark all as read
document.getElementById('markAllRead')?.addEventListener('click', function(e) {
  e.preventDefault();
  fetch('mark_all_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.querySelectorAll('.notification-item').forEach(item => {
        item.classList.remove('unread');
      });
      const badge = document.querySelector('.notification-badge');
      if (badge) badge.remove();
    }
  })
  .catch(error => console.error('Error:', error));
});

// Mobile menu
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');

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

if (mobileBtn) {
  mobileBtn.addEventListener('click', toggleSidebar);
}

if (hamburgerBtn) {
  hamburgerBtn.addEventListener('click', toggleSidebar);
}

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

// Close sidebar on nav link click (mobile only)
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