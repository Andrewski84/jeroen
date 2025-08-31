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
    <link rel="stylesheet" href="style.css">
    <style>
        /* De lightbox is nu donkerder en heeft een blur-effect */
        #lightbox {
            background-color: rgba(25, 25, 25, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: opacity 0.3s ease;
        }
        .lightbox-img { 
            max-height: 75vh;
            max-width: 90vw; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .lightbox-nav { color: white; font-size: 3rem; }
        @media (max-width: 768px) { #lightbox-prev, #lightbox-next { display: none; } }
    </style>
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
            // Show images for a specific theme
            $imagesToShow = $themes[$currentThemeName]['images'];
        } else {
            // Show all images for the "All" view
            foreach ($themes as $themeData) {
                $imagesToShow = array_merge($imagesToShow, $themeData['images']);
            }
        }

        // Normalize paths to web-relative in case absolute paths were stored
        $imagesToShow = array_map(function($img) {
            foreach (['path', 'webp'] as $k) {
                if (!empty($img[$k])) {
                    $img[$k] = toPublicPath($img[$k]);
                }
            }
            return $img;
        }, $imagesToShow);

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
        <div class="text-center pt-12">
            <h1 class="text-4xl md:text-6xl font-serif mb-6">Mijn Portfolio</h1>
            <p class="max-w-2xl mx-auto text-lg">Een collectie van mijn favoriete werk.</p>
            <p class="max-w-2xl mx-auto text-lg">Selecteer een categorie om de galerij te filteren.</p>
        </div>
        
        <?php if (!empty($themes)): ?>
        <div class="flex justify-center flex-wrap items-center gap-4 my-12">
              <?php if ($currentThemeName): ?>
                <a class="filter-btn active" aria-current="true"><?php echo htmlspecialchars(ucfirst($currentThemeName)); ?></a>
                <a href="portfolio.php" class="filter-btn">Reset</a>
              <?php else: ?>
                <a href="portfolio.php" class="filter-btn active">Alles</a>
                <?php foreach ($themes as $themeName => $themeData): ?>
                    <a href="portfolio.php?theme=<?php echo urlencode($themeName); ?>" class="filter-btn">
                        <?php echo htmlspecialchars(ucfirst($themeName)); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($imagesToShow)): ?>
            <p class="text-center text-lg">Er zijn nog geen foto's in dit thema.</p>
        <?php else: ?>
            <div class="portfolio-grid-modern">
                <?php foreach ($imagesToShow as $index => $image): ?>
                    <div class="gallery-item cursor-pointer group" onclick="openLightbox(<?php echo $index; ?>)">
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

<script src="main.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    
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
