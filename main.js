document.addEventListener('DOMContentLoaded', function () {
    /**
     * Header scroll effect: adds a 'scrolled' class to the header
     * when the user scrolls down.
     */
    const header = document.getElementById('header');
    if (header) {
        const handleScroll = () => {
            header.classList.toggle('scrolled', window.scrollY > 50);
        };
        handleScroll(); // Check on page load
        window.addEventListener('scroll', handleScroll);
    }

    /**
     * Staggered reveal on scroll effect:
     * We observe a container. When it comes into view, we add a class
     * that triggers the animation for its children via CSS.
     */
    const staggerContainers = document.querySelectorAll('.stagger-container');
    if (staggerContainers.length > 0) {
        const staggerObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.2 // Trigger when 20% of the container is visible
        });

        staggerContainers.forEach(container => {
            staggerObserver.observe(container);
        });
    }

    // Global: hamburger toggle for mobile menu (works on all pages)
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            // Close any submenus when toggling main menu
            document.getElementById('mobile-team-menu')?.classList.add('hidden');
            document.getElementById('mobile-practice-menu')?.classList.add('hidden');
        });
        // Close the menu after clicking a link
        document.querySelectorAll('.mobile-nav-link').forEach(l => l.addEventListener('click', () => mobileMenu.classList.add('hidden')));
    }

    // Mobile: toggle team dropdown
    const mobileTeamToggle = document.getElementById('mobile-team-toggle');
    const mobileTeamMenu = document.getElementById('mobile-team-menu');
    if (mobileTeamToggle && mobileTeamMenu) {
        mobileTeamToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            mobileTeamMenu.classList.toggle('hidden');
            mobileTeamToggle.setAttribute('aria-expanded', mobileTeamMenu.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    // Mobile: toggle practice dropdown
    const mobilePracticeToggle = document.getElementById('mobile-practice-toggle');
    const mobilePracticeMenu = document.getElementById('mobile-practice-menu');
    if (mobilePracticeToggle && mobilePracticeMenu) {
        mobilePracticeToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            mobilePracticeMenu.classList.toggle('hidden');
            mobilePracticeToggle.setAttribute('aria-expanded', mobilePracticeMenu.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    // Desktop: keep aria-expanded in sync for accessibility
    const headerDropdowns = document.querySelectorAll('.header-dropdown');
    headerDropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.nav-link[aria-haspopup="true"]');
        const panel = dropdown.querySelector('.dropdown-panel');
        if (trigger && panel) {
            dropdown.addEventListener('mouseenter', () => trigger.setAttribute('aria-expanded', 'true'));
            dropdown.addEventListener('mouseleave', () => trigger.setAttribute('aria-expanded', 'false'));
            trigger.addEventListener('focus', () => trigger.setAttribute('aria-expanded', 'true'));
            // Use focusout to handle blur on any child element
            dropdown.addEventListener('focusout', (e) => {
                if (!dropdown.contains(e.relatedTarget)) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });
});
