document.addEventListener('DOMContentLoaded', function() {
  // Check if there's a thesis_id in the URL
  const urlParams = new URLSearchParams(window.location.search);
  const thesisId = urlParams.get('thesis_id');
  
  // If thesis_id exists, scroll to that project
  if (thesisId) {
    const projectCard = document.getElementById('project-' + thesisId);
    
    if (projectCard) {
      // Scroll to the project smoothly
      projectCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      
      // Add highlight class
      projectCard.classList.add('highlight');
      
      // Remove highlight after 2 seconds
      setTimeout(() => {
        projectCard.classList.remove('highlight');
      }, 2000);
    }
  }
});

// Mobile menu toggle
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');

function toggleSidebar() {
  sidebar.classList.toggle('show');
  overlay.classList.toggle('show');
  
  const mobileIcon = mobileBtn?.querySelector('i');
  const hamburgerIcon = hamburgerBtn?.querySelector('i');
  
  if (sidebar.classList.contains('show')) {
    if (mobileIcon) {
      mobileIcon.classList.remove('fa-bars');
      mobileIcon.classList.add('fa-times');
    }
    if (hamburgerIcon) {
      hamburgerIcon.classList.remove('fa-bars');
      hamburgerIcon.classList.add('fa-times');
    }
  } else {
    if (mobileIcon) {
      mobileIcon.classList.remove('fa-times');
      mobileIcon.classList.add('fa-bars');
    }
    if (hamburgerIcon) {
      hamburgerIcon.classList.remove('fa-times');
      hamburgerIcon.classList.add('fa-bars');
    }
  }
}

if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);

if (overlay) {
  overlay.addEventListener('click', function() {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    
    const mobileIcon = mobileBtn?.querySelector('i');
    const hamburgerIcon = hamburgerBtn?.querySelector('i');
    
    if (mobileIcon) {
      mobileIcon.classList.remove('fa-times');
      mobileIcon.classList.add('fa-bars');
    }
    if (hamburgerIcon) {
      hamburgerIcon.classList.remove('fa-times');
      hamburgerIcon.classList.add('fa-bars');
    }
  });
}

const navLinks = document.querySelectorAll('.nav-link');
navLinks.forEach(link => {
  link.addEventListener('click', function() {
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
      
      const mobileIcon = mobileBtn?.querySelector('i');
      const hamburgerIcon = hamburgerBtn?.querySelector('i');
      
      if (mobileIcon) {
        mobileIcon.classList.remove('fa-times');
        mobileIcon.classList.add('fa-bars');
      }
      if (hamburgerIcon) {
        hamburgerIcon.classList.remove('fa-times');
        hamburgerIcon.classList.add('fa-bars');
      }
    }
  });
});

// Confirm archive action
document.querySelectorAll('a[href*="archive_thesis.php"]').forEach(link => {
  link.addEventListener('click', function(e) {
    if (!confirm('Archive this thesis?')) {
      e.preventDefault();
    }
  });
});