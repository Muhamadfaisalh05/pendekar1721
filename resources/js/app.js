import './bootstrap';

// Alpine.js is loaded via Filament/Livewire
// Additional smooth scroll and sticky header effects
document.addEventListener('DOMContentLoaded', function () {
    const header = document.querySelector('.site-header');

    const updateHeaderState = () => {
        if (!header) {
            return;
        }

        if (window.scrollY > 12) {
            header.classList.add('is-scrolled');
        } else {
            header.classList.remove('is-scrolled');
        }
    };

    updateHeaderState();
    window.addEventListener('scroll', updateHeaderState, { passive: true });

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (!targetId || targetId === '#') {
                return;
            }

            const target = document.querySelector(targetId);
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