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

if (avatarBtn) {
  avatarBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdownMenu.classList.toggle('show');
  });
}

window.addEventListener('click', function() {
  if (dropdownMenu) dropdownMenu.classList.remove('show');
});

if (dropdownMenu) {
  dropdownMenu.addEventListener('click', function(e) {
    e.stopPropagation();
  });
}

// Mobile menu toggle
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

function toggleSidebar() {
  sidebar.classList.toggle('show');
  overlay.classList.toggle('show');
  
  const icon = hamburgerBtn?.querySelector('i');
  if (icon) {
    if (sidebar.classList.contains('show')) {
      icon.classList.remove('fa-bars');
      icon.classList.add('fa-times');
    } else {
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    }
  }
}

if (hamburgerBtn) {
  hamburgerBtn.addEventListener('click', toggleSidebar);
}

if (overlay) {
  overlay.addEventListener('click', function() {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    const icon = hamburgerBtn?.querySelector('i');
    if (icon) {
      icon.classList.remove('fa-times');
      icon.classList.add('fa-bars');
    }
  });
}

// Close sidebar on nav link click (mobile)
const navLinks = document.querySelectorAll('.nav-link');
navLinks.forEach(link => {
  link.addEventListener('click', function() {
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
      const icon = hamburgerBtn?.querySelector('i');
      if (icon) {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      }
    }
  });
});

// Form submission handling
const form = document.getElementById('submissionForm');
const submitBtn = document.getElementById('submitBtn');

if (form) {
  form.addEventListener('submit', function() {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
  });
}

// File input validation
const fileInput = document.getElementById('manuscript');
if (fileInput) {
  fileInput.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
      const fileName = file.name;
      const fileExt = fileName.split('.').pop().toLowerCase();
      
      if (fileExt !== 'pdf') {
        alert('Please select a PDF file.');
        this.value = '';
      }
      
      const maxSize = 10 * 1024 * 1024; // 10MB
      if (file.size > maxSize) {
        alert('File size must not exceed 10MB.');
        this.value = '';
      }
    }
  });
}

// Confirm clear form
document.querySelector('button[type="reset"]')?.addEventListener('click', function(e) {
  if (!confirm('Are you sure you want to clear the form?')) {
    e.preventDefault();
  }
});