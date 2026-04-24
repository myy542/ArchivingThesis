// Form validation for request step
const requestForm = document.getElementById('requestForm');
if (requestForm) {
    requestForm.addEventListener('submit', function(e) {
        const email = document.getElementById('email');
        if (!email.value.trim()) {
            e.preventDefault();
            alert('Please enter your email address.');
            email.focus();
            return false;
        }
        const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
        if (!emailRegex.test(email.value)) {
            e.preventDefault();
            alert('Please enter a valid email address.');
            email.focus();
            return false;
        }
    });
}

// Form validation for verify step
const verifyForm = document.getElementById('verifyForm');
if (verifyForm) {
    verifyForm.addEventListener('submit', function(e) {
        const otp = document.getElementById('otp');
        if (!otp.value.trim()) {
            e.preventDefault();
            alert('Please enter the OTP code.');
            otp.focus();
            return false;
        }
        if (otp.value.length < 6) {
            e.preventDefault();
            alert('Please enter a valid 6-digit OTP code.');
            otp.focus();
            return false;
        }
    });
}

// Form validation for reset step
const resetForm = document.getElementById('resetForm');
if (resetForm) {
    resetForm.addEventListener('submit', function(e) {
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        
        if (!newPass.value) {
            e.preventDefault();
            alert('Please enter new password.');
            newPass.focus();
            return false;
        }
        if (newPass.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            newPass.focus();
            return false;
        }
        if (newPass.value !== confirmPass.value) {
            e.preventDefault();
            alert('Passwords do not match.');
            confirmPass.focus();
            return false;
        }
    });
}

// Restrict OTP input to numbers only
const otpInput = document.getElementById('otp');
if (otpInput) {
    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

// Auto-hide alerts after 5 seconds
const alerts = document.querySelectorAll('.alert');
alerts.forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            if (alert.parentNode) alert.remove();
        }, 300);
    }, 5000);
});

console.log('Forgot password page initialized');