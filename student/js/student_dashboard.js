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

// Dropdown toggles
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

if (notificationBell) {
  notificationBell.addEventListener('click', function(e) {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');
    if (dropdownMenu) dropdownMenu.classList.remove('show');
  });
}

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
  
  fetch('notification_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ action: 'mark_all_read' })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      document.querySelectorAll('.notification-item').forEach(item => {
        item.classList.remove('unread');
      });
      
      const badge = document.querySelector('.notification-badge');
      if (badge) {
        badge.remove();
      }
    }
  })
  .catch(error => console.error('Error:', error));
});

// Mark as read function
window.markAsRead = function(element) {
  var notificationId = element.getAttribute('data-notification-id');
  var thesisId = element.getAttribute('data-thesis-id');
  
  if (!notificationId) {
    return;
  }
  
  element.style.opacity = '0.5';
  element.style.pointerEvents = 'none';
  
  fetch('notification_handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ 
      action: 'mark_read',
      notification_id: notificationId 
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      element.classList.remove('unread');
      
      const badge = document.querySelector('.notification-badge');
      if (badge) {
        let currentCount = parseInt(badge.textContent);
        if (currentCount > 1) {
          badge.textContent = currentCount - 1;
        } else {
          badge.remove();
        }
      }
      
      if (thesisId && thesisId > 0 && thesisId != '0') {
        window.location.href = 'projects.php';
      }
    } else {
      element.style.opacity = '1';
      element.style.pointerEvents = 'auto';
    }
  })
  .catch(error => {
    element.style.opacity = '1';
    element.style.pointerEvents = 'auto';
  });
}

// Mobile menu toggle
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');

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

// CHARTS - Project Status Distribution
new Chart(document.getElementById('projectStatusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Pending', 'Approved', 'Rejected', 'Archived'],
    datasets: [{
      data: [chartData.pending, chartData.approved, chartData.rejected, chartData.archived],
      backgroundColor: ['#ef9a9a', '#81c784', '#b71c1c', '#999999'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 15,
          usePointStyle: true,
          pointStyle: 'circle',
          color: '#333333'
        }
      }
    },
    cutout: '70%'
  }
});

// Timeline Chart
new Chart(document.getElementById('timelineChart'), {
  type: 'line',
  data: {
    labels: ['Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'],
    datasets: [{
      label: 'Submissions',
      data: [2, 3, 1, 4, 3, 5, 2],
      borderColor: '#d32f2f',
      backgroundColor: 'rgba(211, 47, 47, 0.1)',
      tension: 0.4,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(211, 47, 47, 0.1)'
        },
        ticks: {
          stepSize: 1,
          color: '#666666'
        }
      },
      x: {
        ticks: {
          color: '#666666'
        }
      }
    }
  }
});