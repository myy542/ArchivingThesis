// This file handles AJAX requests for librarian_archive.php
function archiveThesis(thesisId, retentionPeriod, archiveNotes, callback) {
    const formData = new FormData();
    formData.append('thesis_id', thesisId);
    formData.append('retention_period', retentionPeriod);
    formData.append('archive_notes', archiveNotes);
    
    fetch('librarian_archive.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (callback) callback(data);
        showArchiveToast(data);
    })
    .catch(error => {
        console.error('Error:', error);
        if (callback) callback({ success: false, message: 'Network error. Please try again.' });
        showArchiveToast({ success: false, message: 'Network error. Please try again.' });
    });
}

function showArchiveToast(data) {
    const toast = document.createElement('div');
    toast.className = `archive-toast ${data.success ? 'success' : 'error'}`;
    toast.innerHTML = `<i class="fas ${data.success ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${data.message}`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

console.log('librarian_archive.js loaded');