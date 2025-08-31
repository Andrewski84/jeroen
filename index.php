<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <?php
        // Load global content
        $siteContent = [];
        if (file_exists(CONTENT_FILE)) {
            $siteContent = json_decode(file_get_contents(CONTENT_FILE), true) ?: [];
        }
        $metaTitle = $siteContent['meta_title'] ?? 'Andrew Smeets Fotografie';
        $metaDescription = $siteContent['meta_description'] ?? '';
        $instagramUrl = $siteContent['contact']['instagram_url'] ?? '';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($metaTitle); ?></title>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css">
</head>
<body class="antialiased">
<div class="w-full overflow-x-hidden">
    <?php
        function loadContent($file) {
            if (file_exists($file)) { return json_decode(file_get_contents($file), true); }
            return [];
        }
        $content = $siteContent;
        $portfolioData = loadContent(PORTFOLIO_FILE);
        $themes = array_keys($portfolioData['themes'] ?? []);

        $featuredImages = [];
        if (!empty($portfolioData['themes'])) {
            foreach ($portfolioData['themes'] as $themeName => $theme) {
                foreach ($theme['images'] as $image) {
                    if (!empty($image['featured'])) {
                        // Normalize any accidentally stored absolute paths
                        foreach (['path','webp'] as $k) {
                            if (!empty($image[$k])) {
                                $image[$k] = toPublicPath($image[$k]);
                            }
                        }
                        $image['theme'] = $themeName;
                        $featuredImages[] = $image;
                    }
                }
            }
        }

        $page = 'index';
        include TEMPLATES_DIR . '/header.php';
    ?>

    <section id="home" class="h-screen min-h-[600px] bg-cover bg-center bg-fixed flex items-center justify-center stagger-container" style="background-image: url('<?php echo htmlspecialchars($content['hero']['image'] ?? ''); ?>');">
        <div class="w-full h-full flex items-center justify-center px-4">
            <h1 class="font-serif reveal max-w-4xl"><?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?></h1>
        </div>
    </section>

    <section id="bio">
        <div class="container mx-auto px-6 stagger-container">
            <div class="flex flex-col md:flex-row items-center gap-12 md:gap-20">
                <div class="md:w-5/12 reveal">
                    <img src="<?php echo htmlspecialchars($content['bio']['image'] ?? ''); ?>" alt="Foto van Andrew" class="w-full">
                </div>
                <div class="md:w-7/12 text-center md:text-left reveal">
                    <h2 class="text-4xl md:text-5xl mb-4"><?php echo htmlspecialchars($content['bio']['title'] ?? ''); ?></h2>
                    <p class="text-lg leading-relaxed"><?php echo nl2br(htmlspecialchars($content['bio']['text'] ?? '')); ?></p>
                    <a href="portfolio.php" class="btn btn-primary mt-6">Ontdek mijn werk</a>
                </div>
            </div>
        </div>
    </section>

    <section id="featured-work" style="background-color: var(--surface);">
        <div class="container mx-auto px-6 text-center stagger-container">
            <h2 class="text-4xl md:text-5xl mb-12 reveal">Uitgelicht Werk</h2>
            <?php if (!empty($featuredImages)): ?>
            <div class="swiper-container reveal">
                <div class="swiper-wrapper">
                    <?php foreach ($featuredImages as $image): ?>
                    <div class="swiper-slide" style="background-image: url('<?php echo htmlspecialchars($image['path']); ?>')">
                        <a href="portfolio.php?theme=<?php echo urlencode($image['theme']); ?>&highlight=<?php echo urlencode($image['path']); ?>" class="absolute inset-0">
                            <div class="slide-content">
                                <h3 class="slide-title"><?php echo htmlspecialchars($image['title'] ?? 'Bekijk project'); ?></h3>
                                <p class="text-sm"><?php echo htmlspecialchars($image['description'] ?? ''); ?></p>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
        // Honeypot / time-trap start marker
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION['form_start'] = time();
    ?>
    <section id="contact" class="stagger-container">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center reveal">
                <h2 class="text-4xl md:text-5xl mb-4">Laten we iets moois creëren</h2>
                <p class="text-lg mb-10">Heb je een vraag of wil je een shoot boeken? Stuur me een bericht.</p>
            </div>
            <form id="contact_form" action="save.php" method="POST" class="max-w-xl mx-auto space-y-6 reveal">
                <input type="hidden" name="action" value="contact_form">
                <div><input id="contact_name" name="name" type="text" placeholder="Jouw Naam*" required></div>
                <div><input id="contact_email" name="email" type="email" placeholder="Jouw e-mail*" required></div>
                <div><textarea id="contact_message" name="message" placeholder="Jouw Bericht*" rows="6" required></textarea></div>
                <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;"><label for="address2">Adresregel 2</label><input id="address2" name="address2" type="text" value="" autocomplete="off" tabindex="-1"></div>
                <div class="text-center"><button type="submit" class="btn btn-primary">Verstuur Bericht</button></div>
            </form>
        </div>
    </section>

    <?php include TEMPLATES_DIR . '/footer.php'; ?>
</div>
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script src="main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Swiper
    var swiper = new Swiper('.swiper-container', {
        effect: 'coverflow',
        grabCursor: true,
        centeredSlides: true,
        slidesPerView: 'auto',
        // Prevent accidental navigation while dragging
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
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        loop: true,
        on: {
            // Ensure links still work on actual click/tap
            click: function (swiper, event) {
                if (swiper.allowClick) {
                    const a = event.target.closest('a');
                    if (a) { window.location.href = a.href; }
                }
            }
        }
    });
    // Show toaster for contact form result (uses same style as others)
    <?php if(isset($_GET['sent'])): ?>
    (function(){
        var ok = <?php echo ($_GET['sent']=='1' ? 'true' : 'false'); ?>;
        var msg = ok ? 'Bedankt! Je bericht is verzonden.' : 'Er ging iets mis bij het verzenden. Probeer later opnieuw.';
        var toast = document.getElementById('toast-popup');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-popup';
            toast.className = 'fixed bottom-5 right-5 bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg opacity-0 transform translate-y-4 z-[1000]';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.backgroundColor = ok ? '#22c55e' : '#ef4444';
        toast.classList.remove('opacity-0','translate-y-4');
        setTimeout(function(){ toast.classList.add('opacity-0','translate-y-4'); }, 2500);
    })();
    <?php endif; ?>
    // Preserve scroll position around contact form submission
    (function(){
        var form = document.getElementById('contact_form');
        if (form) {
            form.addEventListener('submit', function(){
                try { sessionStorage.setItem('contactScrollY', String(window.scrollY || window.pageYOffset || 0)); } catch(e) {}
            });
        }
        try {
            var url = new URL(window.location.href);
            if (url.searchParams.has('sent')) {
                var y = sessionStorage.getItem('contactScrollY');
                if (y !== null) {
                    window.scrollTo(0, parseInt(y, 10) || 0);
                    sessionStorage.removeItem('contactScrollY');
                }
            }
        } catch (e) {}
    })();
});
</script>
</body>
</html>
