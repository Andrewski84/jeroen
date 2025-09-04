document.addEventListener('DOMContentLoaded', function () {
    // Homepage: initialize featured Swiper if present
    try {
        if (typeof Swiper !== 'undefined' && document.querySelector('.homepage-swiper')) {
            const el = document.querySelector('.homepage-swiper');
            const count = el.querySelectorAll('.swiper-slide').length;
            const shouldLoop = count > 3;
            const opts = {
                effect: 'coverflow',
                grabCursor: true,
                centeredSlides: true,
                centeredSlidesBounds: true,
                slidesPerView: 'auto',
                preventClicks: true,
                preventClicksPropagation: true,
                threshold: 5,
                coverflowEffect: {
                    rotate: 50,
                    stretch: 0,
                    depth: 100,
                    modifier: 1,
                    slideShadows: true,
                },
                loop: shouldLoop,
                initialSlide: Math.max(0, Math.floor(count / 2)),
                loopedSlides: Math.min(5, Math.max(3, count)),
                pagination: shouldLoop ? { el: '.homepage-swiper-pagination', clickable: true } : undefined,
                on: {
                    click: function (swiper, event) {
                        if (!swiper.allowClick) return;
                        const a = event.target.closest('a');
                        if (!a) return;
                        const idxAttr = a.getAttribute('data-idx');
                        const indexLightbox = document.getElementById('index-lightbox');
                        if (indexLightbox && idxAttr !== null && typeof window.openIndexLightbox === 'function') {
                            event.preventDefault();
                            const i = parseInt(idxAttr, 10) || 0;
                            window.openIndexLightbox(i);
                        } else {
                            window.location.href = a.href;
                        }
                    }
                }
            };
            const swiper = new Swiper('.homepage-swiper', opts);
            const pag = document.querySelector('.homepage-swiper-pagination');
            if (pag) pag.style.display = shouldLoop ? '' : 'none';
            // ensure update after render for proper centering
            setTimeout(() => { try { swiper.update(); } catch(e){} }, 50);
        }
    } catch (e) {}
    /**
     * Header scroll effect: voegt een 'scrolled' class toe aan de header
     * wanneer de gebruiker naar beneden scrollt.
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
     * We observeren een container. Wanneer de container in beeld komt,
     * voegen we een class toe die de animatie voor de kinderen activeert.
     * De kinderen hebben een CSS transition-delay, wat zorgt voor een
     * vloeiend cascade-effect.
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
            threshold: 0.2 // Activeer als 20% van de container zichtbaar is
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
        document.getElementById('mobile-portfolio-menu')?.classList.add('hidden');
        document.getElementById('mobile-client-menu')?.classList.add('hidden');
        document.getElementById('mobile-team-menu')?.classList.add('hidden');
        document.getElementById('mobile-practice-menu')?.classList.add('hidden');
        });
        // Close the menu after clicking a link
        document.querySelectorAll('.mobile-nav-link').forEach(l => l.addEventListener('click', () => mobileMenu.classList.add('hidden')));
    }

    // Mobile: toggle portfolio dropdown in the compact panel
    const mobilePortfolioToggle = document.getElementById('mobile-portfolio-toggle');
    const mobilePortfolioMenu = document.getElementById('mobile-portfolio-menu');
    if (mobilePortfolioToggle && mobilePortfolioMenu) {
        mobilePortfolioToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            mobilePortfolioMenu.classList.toggle('hidden');
            mobilePortfolioToggle.setAttribute('aria-expanded', mobilePortfolioMenu.classList.contains('hidden') ? 'false' : 'true');
        });
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

    // Mobile: toggle client gallery dropdown if present
    const mobileClientToggle = document.getElementById('mobile-client-toggle');
    const mobileClientMenu = document.getElementById('mobile-client-menu');
    if (mobileClientToggle && mobileClientMenu) {
        mobileClientToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            mobileClientMenu.classList.toggle('hidden');
            mobileClientToggle.setAttribute('aria-expanded', mobileClientMenu.classList.contains('hidden') ? 'false' : 'true');
        });
    }

    // Client: confirm and send selection (dropdown)
    function showClientConfirm(slug) {
        const modal = document.getElementById('client-confirm-modal');
        if (!modal) return false;
        modal.classList.remove('hidden');
        const yes = document.getElementById('client-confirm-yes');
        const no = document.getElementById('client-confirm-no');
        const cleanup = () => {
            yes.removeEventListener('click', onYes);
            no.removeEventListener('click', onNo);
        };
        const onNo = () => { modal.classList.add('hidden'); cleanup(); };
        const onYes = () => {
            fetch('proof_save.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=finalize&gallery=${encodeURIComponent(slug)}` })
                .finally(() => fetch('client_logout.php', { method: 'GET', cache: 'no-store' }))
                .finally(() => {
                    document.getElementById('client-menu-desktop')?.remove();
                    document.getElementById('mobile-client-block')?.remove();
                    // Simple toast
                    let toast = document.getElementById('toast-popup');
                    if (!toast) {
                        toast = document.createElement('div');
                        toast.id = 'toast-popup';
                        toast.className = 'fixed bottom-5 right-5 bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg opacity-0 transform translate-y-4 z-[1000]';
                        document.body.appendChild(toast);
                    }
                    toast.textContent = 'Keuze doorgestuurd!';
                    toast.style.backgroundColor = '#28a745';
                    toast.classList.remove('opacity-0', 'translate-y-4');
                    setTimeout(() => { toast.classList.add('opacity-0', 'translate-y-4'); }, 1500);
                    // Redirect to homepage after short delay
                    setTimeout(() => { window.location.href = 'index.php'; }, 1600);
                    onNo();
                });
        };
        yes.addEventListener('click', onYes);
        no.addEventListener('click', onNo);
        return true;
    }

    const clientSendBtn = document.getElementById('client-send-selection');
    if (clientSendBtn) {
        clientSendBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const slug = clientSendBtn.dataset.slug;
            if (slug) showClientConfirm(slug);
        });
    }
    const clientSendBtnMobile = document.getElementById('mobile-client-send-selection');
    if (clientSendBtnMobile) {
        clientSendBtnMobile.addEventListener('click', (e) => {
            e.preventDefault();
            const slug = clientSendBtnMobile.dataset.slug;
            if (slug) showClientConfirm(slug);
        });
    }

    // Desktop: keep aria-expanded in sync for accessibility
    const headerDropdown = document.querySelector('.header-dropdown');
    if (headerDropdown) {
        const trigger = headerDropdown.querySelector('.nav-link[aria-haspopup="true"]') || headerDropdown.querySelector('.nav-link-button');
        const panel = headerDropdown.querySelector('.dropdown-panel');
        if (trigger && panel) {
            headerDropdown.addEventListener('mouseenter', () => trigger.setAttribute('aria-expanded', 'true'));
            headerDropdown.addEventListener('mouseleave', () => trigger.setAttribute('aria-expanded', 'false'));
            trigger.addEventListener('focus', () => trigger.setAttribute('aria-expanded', 'true'));
            trigger.addEventListener('blur', () => trigger.setAttribute('aria-expanded', 'false'));
        }
    }
});

