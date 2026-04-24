// Print certificate function
function printCertificate() {
    window.print();
}

// Attach print button event
const printBtn = document.querySelector('.btn-print');
if (printBtn) {
    printBtn.addEventListener('click', printCertificate);
}

console.log('Certificate Page Initialized');