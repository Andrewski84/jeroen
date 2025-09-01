<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio - Andrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="stylesheet" href="style.css">

</head>
<body id="portfolio-page" class="antialiased flex flex-col min-h-screen">
    <?php
        function loadContent($file) { if (file_exists($file)) { return json_decode(file_get_contents($file), true); } return []; }
        $siteContent = loadContent(CONTENT_FILE);
        $portfolioData = loadContent(PORTFOLIO_FILE);
        $themes = $portfolioData['themes'] ?? [];
        $currentThemeName = $_GET['theme'] ?? null;
        $instagramUrl = $siteContent['contact']['instagram_url'] ?? '';
        $imagesToShow = [];

        if ($currentThemeName && isset($themes[$currentThemeName])) {
            // Toon afbeeldingen voor een specifiek thema
            $imagesToShow = array_map(function($img) use ($currentThemeName) {
                $img['theme'] = $currentThemeName;
                return $img;
            }, ($themes[$currentThemeName]['images'] ?? []));
        } else {
            // Toon alle afbeeldingen van alle thema's
            foreach ($themes as $themeName => $themeData) {
                $annotated = array_map(function($img) use ($themeName) {
                    $img['theme'] = $themeName;
                    return $img;
                }, ($themeData['images'] ?? []));
                $imagesToShow = array_merge($imagesToShow, $annotated);
            }
        }

        // Filter voor uitgelichte afbeeldingen voor de carrousel, gebaseerd op de huidige selectie
        $carouselImages = array_filter($imagesToShow, function($img) {
            return !empty($img['featured']);
        });

        // Normaliseer paden naar web-relatieve paden
        $imagesToShow = array_map(function($img) {
            foreach (['path', 'webp'] as $k) { if (!empty($img[$k])) { $img[$k] = toPublicPath($img[$k]); } }
            return $img;
        }, $imagesToShow);

        $carouselImages = array_map(function($img) {
            foreach (['path', 'webp'] as $k) { if (!empty($img[$k])) { $img[$k] = toPublicPath($img[$k]); } }
            return $img;
        }, $carouselImages);

        $highlightPath = $_GET['highlight'] ?? null;
        $highlightIndex = null;
        if ($highlightPath) {
            $highlightPath = toPublicPath($highlightPath);
            foreach ($imagesToShow as $idx => $img) {
                if (($img['path'] ?? '') === $highlightPath) {
                    $highlightIndex = $idx;
                    break;
                }
            }
        }
    ?>
    
    <?php
        $page = 'portfolio';
        include TEMPLATES_DIR . '/header.php';
    ?>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-16 flex-grow">
        <div class="stagger-container text-center pt-12">
            <h1 class="text-4xl md:text-6xl font-serif mb-4 reveal">Mijn Portfolio</h1>
            <p class="max-w-2xl mx-auto text-lg reveal">Een collectie van mijn favoriete werk.</p>
        </div>

        <!-- Carousel sectie, wordt alleen getoond als er uitgelichte foto's zijn -->
        <?php if (!empty($carouselImages)): ?>
        <section class="stagger-container">
            <div class="portfolio-swiper swiper-container reveal">
                <div class="swiper-wrapper">
                    <?php foreach ($carouselImages as $image): ?>
                        <div class="swiper-slide">
                            <a href="portfolio.php?theme=<?php echo urlencode($image['theme']); ?>&highlight=<?php echo urlencode($image['path']); ?>">
                                <img src="<?php echo htmlspecialchars($image['path']); ?>" alt="<?php echo htmlspecialchars($image['alt'] ?? ''); ?>" loading="lazy">
                                <div class="slide-overlay">
                                    <div class="text-xs uppercase tracking-wide opacity-80"><?php echo htmlspecialchars(ucfirst($image['theme'])); ?></div>
                                    <div class="font-serif text-lg"><?php echo htmlspecialchars($image['title'] ?? ''); ?></div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (!empty($themes)): ?>
        <div id="portfolio-filters" class="stagger-container my-10">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 reveal">
                    <a href="portfolio.php" class="filter-btn <?php echo !$currentThemeName ? 'active' : ''; ?>">Alles</a>
                    <?php foreach ($themes as $themeName => $themeData): ?>
                        <a href="portfolio.php?theme=<?php echo urlencode($themeName); ?>" class="filter-btn <?php echo $currentThemeName === $themeName ? 'active' : ''; ?>"><?php echo htmlspecialchars(ucfirst($themeName)); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($imagesToShow)): ?>
            <p class="text-center text-lg">Er zijn nog geen foto's in dit thema.</p>
        <?php else: ?>
            <div id="portfolio-grid" class="portfolio-grid-modern stagger-container">
                <?php foreach ($imagesToShow as $index => $image): ?>
                    <div class="gallery-item cursor-pointer group reveal" data-theme="<?php echo htmlspecialchars($image['theme']); ?>" data-title="<?php echo htmlspecialchars($image['title'] ?? ''); ?>" data-description="<?php echo htmlspecialchars($image['description'] ?? ''); ?>" onclick="openLightbox(<?php echo $index; ?>)">
                        <img src="<?php echo htmlspecialchars($image['path']); ?>" alt="<?php echo htmlspecialchars($image['alt'] ?? ''); ?>" class="w-full h-auto object-cover" loading="lazy">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Lightbox -->
    <div id="lightbox" class="fixed inset-0 z-[100] flex items-center justify-center hidden">
        <button id="lightbox-close" class="lightbox-nav absolute top-4 right-6">&times;</button>
        <button id="lightbox-prev" class="lightbox-nav absolute left-4 top-1/2 -translate-y-1/2">&lt;</button>
        <button id="lightbox-next" class="lightbox-nav absolute right-4 top-1/2 -translate-y-1/2">&gt;</button>
        <div class="flex flex-col items-center gap-4">
            <img id="lightbox-img" src="" alt="" class="lightbox-img rounded-md">
            <div id="lightbox-caption" class="text-center text-white max-w-lg">
                <h3 id="lightbox-title" class="text-2xl font-serif mb-2"></h3>
                <p id="lightbox-description" class="text-sm opacity-80"></p>
            </div>
        </div>
    </div>
    
    <?php include TEMPLATES_DIR . '/footer.php'; ?>

<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script src="main.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
      try {
        const portfolioSwiperEl = document.querySelector('.portfolio-swiper');
        if (portfolioSwiperEl) {
            new Swiper(portfolioSwiperEl, {
              effect: 'slide',
              slidesPerView: 'auto',
              spaceBetween: 24,
              loop: true,
              grabCursor: true, // Maakt slepen mogelijk met een 'handje' cursor
              allowTouchMove: true, // Staat slepen/swipen toe
              autoplay: {
                delay: 4000,
                disableOnInteraction: true, // Pauzeert autoplay na interactie
              },
              pagination: { el: '.swiper-pagination', clickable: true },
              breakpoints: { 768: { spaceBetween: 28 }, 1280: { spaceBetween: 32 } },
            });
        }
      } catch (e) {}

      const portfolioImages = <?php echo json_encode($imagesToShow); ?>;
      const highlightIndex = <?php echo $highlightIndex !== null ? $highlightIndex : 'null'; ?>;
    if (portfolioImages.length > 0) {
        let idx = 0;
        const lb = { 
            el: document.getElementById('lightbox'), 
            img: document.getElementById('lightbox-img'),
            title: document.getElementById('lightbox-title'),
            description: document.getElementById('lightbox-description')
        };
        const update = () => { 
            lb.img.src = portfolioImages[idx].path; 
            lb.img.alt = portfolioImages[idx].alt || '';
            lb.title.textContent = portfolioImages[idx].title || '';
            lb.description.textContent = portfolioImages[idx].description || '';
        };
        const next = () => { idx = (idx + 1) % portfolioImages.length; update(); };
        const prev = () => { idx = (idx - 1 + portfolioImages.length) % portfolioImages.length; update(); };
        window.openLightbox = (index) => { idx = index; update(); lb.el.classList.remove('hidden'); };
        const closeLightbox = () => lb.el.classList.add('hidden');
        
        document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
        document.getElementById('lightbox-next').addEventListener('click', next);
        document.getElementById('lightbox-prev').addEventListener('click', prev);
          lb.el.addEventListener('click', (e) => { if (e.target === lb.el) closeLightbox(); });
        document.addEventListener('keydown', (e) => { 
            if(lb.el.classList.contains('hidden')) return; 
            if (e.key === 'Escape') closeLightbox(); 
            if (e.key === 'ArrowRight') next(); 
            if (e.key === 'ArrowLeft') prev(); 
        });

        let touchStartX = 0;
        lb.el.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; });
        lb.el.addEventListener('touchend', e => {
            const touchEndX = e.changedTouches[0].clientX;
            if (touchStartX - touchEndX > 50) next();
            if (touchEndX - touchStartX > 50) prev();
        });
          if (highlightIndex !== null) {
              openLightbox(highlightIndex);
          }
      }
  });
  </script>
</body>
</html>

