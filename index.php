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
<body id="index-page" class="antialiased">
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
            <div class="flex flex-col md:flex-row-reverse items-center gap-12 md:gap-20">
                <div class="md:w-4/12 reveal">
                    <img src="<?php echo htmlspecialchars($content['bio']['image'] ?? ''); ?>" alt="Foto van Andrew" class="w-full">
                </div>
                <div class="md:w-8/12 text-center md:text-left reveal">
                    <h2 class="text-4xl md:text-5xl mb-4"><?php echo htmlspecialchars($content['bio']['title'] ?? ''); ?></h2>
                    <p class="text-lg leading-relaxed"><?php echo nl2br(htmlspecialchars($content['bio']['text'] ?? '')); ?></p>
                    <?php $portfolioVisible = !isset($siteContent['pages']['portfolio']['visible']) || $siteContent['pages']['portfolio']['visible']; ?>
                    <?php if ($portfolioVisible): ?>
                    <a href="portfolio.php" class="btn btn-primary mt-6">Ontdek mijn werk</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section id="featured-work" style="background-color: var(--surface);">
        <div class="container mx-auto px-6 text-center stagger-container">
            <h2 class="text-4xl md:text-5xl mb-12 reveal">Uitgelicht Werk</h2>
            <?php if (!empty($featuredImages)): ?>
            <div class="homepage-swiper swiper-container reveal">
                <div class="swiper-wrapper">
                    <?php foreach ($featuredImages as $i => $image): ?>
                    <div class="swiper-slide" style="background-image: url('<?php echo htmlspecialchars($image['path']); ?>')">
                        <a href="portfolio.php?theme=<?php echo urlencode($image['theme']); ?>&highlight=<?php echo urlencode($image['path']); ?>" class="absolute inset-0 slide-link" data-idx="<?php echo (int)$i; ?>">
                            <div class="slide-content">
                                <h3 class="slide-title"><?php echo htmlspecialchars($image['title'] ?? 'Bekijk project'); ?></h3>
                                <p class="text-sm"><?php echo htmlspecialchars($image['description'] ?? ''); ?></p>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="homepage-swiper-pagination"></div>
            
            <?php endif; ?>
        </div>
    </section>

    <!-- Index lightbox -->
    <div id="index-lightbox" class="fixed inset-0 z-[200] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/70"></div>
        <button id="index-lightbox-close" class="absolute top-4 right-6 text-white text-4xl">&times;</button>
        <button id="index-lightbox-prev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl">&lt;</button>
        <button id="index-lightbox-next" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl">&gt;</button>
        <div class="relative max-w-5xl w-11/12 flex flex-col items-center index-lightbox-inner">
            <img id="index-lightbox-img" src="" alt="" class="w-auto h-auto max-h-[70vh] object-contain rounded-md shadow-2xl">
            <div id="index-lightbox-caption" class="mt-4 text-center text-white/90">
                <h3 id="index-lightbox-title" class="text-xl font-serif"></h3>
                <p id="index-lightbox-description" class="text-sm opacity-80"></p>
            </div>
        </div>
    </div>

    <?php
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        $_SESSION['form_start'] = time();
    ?>
    <section id="contact" class="stagger-container">
        <div class="container mx-auto px-6">
            <div class="max-w-3xl mx-auto text-center reveal">
                <h2 class="text-4xl md:text-5xl mb-4">Laten we samenwerken</h2>
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
window.indexFeatured = <?php echo json_encode($featuredImages); ?>;
document.addEventListener('DOMContentLoaded', function () {
    // Show toaster for contact form result
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

    // Index lightbox handlers
    (function(){
        const lf = window.indexFeatured || [];
        const wrap = document.getElementById('index-lightbox');
        if (!wrap || !lf.length) return;
        let idx = 0;
        const img = document.getElementById('index-lightbox-img');
        const title = document.getElementById('index-lightbox-title');
        const desc = document.getElementById('index-lightbox-description');
        const upd = () => {
            img.src = lf[idx].path || '';
            img.alt = lf[idx].alt || '';
            title.textContent = lf[idx].title || '';
            desc.textContent = lf[idx].description || '';
        };
        window.openIndexLightbox = function(i){ idx = Math.max(0, Math.min(i, lf.length-1)); upd(); wrap.classList.remove('hidden'); wrap.classList.add('flex'); };
        const close = () => { wrap.classList.add('hidden'); wrap.classList.remove('flex'); };
        document.getElementById('index-lightbox-close')?.addEventListener('click', close);
        document.getElementById('index-lightbox-next')?.addEventListener('click', () => { idx = (idx+1)%lf.length; upd(); });
        document.getElementById('index-lightbox-prev')?.addEventListener('click', () => { idx = (idx-1+lf.length)%lf.length; upd(); });
        wrap.addEventListener('click', (e) => { if (e.target === wrap) close(); });
        document.addEventListener('keydown', (e) => { if (wrap.classList.contains('hidden')) return; if (e.key==='Escape') close(); if (e.key==='ArrowRight') { idx=(idx+1)%lf.length; upd(); } if (e.key==='ArrowLeft') { idx=(idx-1+lf.length)%lf.length; upd(); } });
    })();
});
</script>
</body>
</html>
