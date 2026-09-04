import './bootstrap';

// Alpine.js is loaded via Filament/Livewire
// Additional smooth scroll for anchor links
document.addEventListener('DOMContentLoaded', function () {
    // Handle smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});