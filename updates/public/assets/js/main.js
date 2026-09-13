// public/assets/js/main.js

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Add fade-in animation to cards
    var cards = document.querySelectorAll('.card-modern');
    cards.forEach(function(card, index) {
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('fade-in');
    });
});

// Form validation
function validateForm(formId) {
    var form = document.getElementById(formId);
    if (!form) return true;
    
    var inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    var isValid = true;
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
        
        // Password confirmation
        if (input.name === 'confirm_password') {
            var password = form.querySelector('input[name="password"]');
            if (password && input.value !== password.value) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Password strength meter
function checkPasswordStrength(password) {
    var strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    var strengthText = '';
    var strengthColor = '';
    
    if (strength <= 2) {
        strengthText = 'Weak';
        strengthColor = 'danger';
    } else if (strength <= 4) {
        strengthText = 'Medium';
        strengthColor = 'warning';
    } else {
        strengthText = 'Strong';
        strengthColor = 'success';
    }
    
    return { text: strengthText, color: strengthColor, score: strength };
}

// Live password strength indicator
document.addEventListener('DOMContentLoaded', function() {
    var passwordInput = document.querySelector('input[name="password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            var strength = checkPasswordStrength(this.value);
            var indicator = document.getElementById('passwordStrength');
            
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'passwordStrength';
                indicator.className = 'mt-1 small';
                this.parentNode.appendChild(indicator);
            }
            
            indicator.innerHTML = 'Password strength: <span class="text-' + strength.color + '">' + strength.text + '</span>';
        });
    }
});

// Mobile menu handling
var mobileMenuToggle = document.getElementById('mobileMenuToggle');
if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', function() {
        var sidebar = document.querySelector('.admin-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('active');
        }
    });
}

// Smooth scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});