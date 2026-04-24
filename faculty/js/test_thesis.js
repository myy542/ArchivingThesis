// Debug script for thesis status testing
console.log('Debug Page Initialized');
console.log('Thesis ID:', window.debugData?.thesisId);
console.log('Is Archived:', window.debugData?.isArchived);
console.log('Title:', window.debugData?.title);

// Add visual indicator for current status
document.addEventListener('DOMContentLoaded', function() {
    const statusElement = document.querySelector('.status-value');
    if (statusElement && window.debugData) {
        if (window.debugData.isArchived === 0) {
            statusElement.style.color = 'green';
            statusElement.innerHTML = 'Pending/Active ✅';
        } else if (window.debugData.isArchived === 1) {
            statusElement.style.color = 'red';
            statusElement.innerHTML = 'Archived ❌';
        }
    }
});

console.log('Test Thesis Page Ready');