<?php
/**
 * Client gallery (proofing) page
 * Allows clients to view and select favorite photos in a password protected gallery.
 */
session_start();
require_once 'config.php';
require_once 'helpers.php';

// Helper to load JSON files
function loadContent($file) {
    if (file_exists($file)) { return json_decode(file_get_contents($file), true) ?: []; }
    return [];
}

/**
 * Genereert een volledige HTML-pagina voor foutmeldingen.
 * @param string $title De titel van de pagina.
 * @param string $message Het bericht dat aan de gebruiker wordt getoond.
 */
function renderErrorPage($title, $message) {
    // Start van de HTML-structuur
    echo '<!DOCTYPE html><html lang="nl" class="h-full"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . ' - Andrew</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="stylesheet" href="style.css?v=' . @filemtime('style.css') . '">';
    echo '</head><body class="flex flex-col min-h-screen">';

    // Header
    echo '<header class="sticky top-0 w-full z-50 shadow-md" style="background: rgba(var(--surface-rgb), 0.9);">';
    echo '<nav class="container mx-auto px-6 flex justify-between items-center h-20">';
    echo '<a href="index.php" class="text-2xl font-serif font-bold">Andrew</a>';
    echo '</nav></header>';

    // Hoofdinhoud met de foutmelding
    echo '<main class="flex-grow flex items-center justify-center container mx-auto px-6 py-12">';
    echo '<div class="text-center p-10 rounded-lg shadow-xl max-w-xl" style="background: var(--surface); border: 1px solid var(--border);">';
    echo '<h1 class="text-4xl font-serif font-bold mb-4">' . htmlspecialchars($title) . '</h1>';
    echo '<p class="text-lg mb-8">' . htmlspecialchars($message) . '</p>';
    echo '<a href="index.php" class="btn btn-primary">Terug naar homepage</a>';
    echo '</div></main>';

    // Footer
    echo '<footer class="py-6" style="background: var(--surface);"><div class="container mx-auto px-6 text-center"><p>&copy; ' . date('Y') . ' Andrew Smeets Fotografie. Alle rechten voorbehouden.</p></div></footer>';
    echo '</body></html>';
    exit;
}

// Haal de galerij-slug uit de URL.
$slug = $_GET['gallery'] ?? '';
$galleryFile = GALLERIES_DIR . '/' . $slug . '/gallery.json';

// Controleer of de galerij bestaat.
if (empty($slug) || !file_exists($galleryFile)) {
    http_response_code(404);
    renderErrorPage('Galerij niet gevonden', 'De opgevraagde galerij bestaat niet of is verwijderd.');
}

$galleryData = loadContent($galleryFile);

// Controleer of de galerij actief is.
if (isset($galleryData['active']) && $galleryData['active'] === false) {
    renderErrorPage('Galerij niet actief', 'Deze galerij is momenteel niet beschikbaar. Neem contact op voor meer informatie.');
}

// Beheer de login-status.
$loggedIn = $_SESSION['gallery_' . $slug . '_logged_in'] ?? false;
$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (password_verify($_POST['password'], $galleryData['password_hash'])) {
        $_SESSION['gallery_' . $slug . '_logged_in'] = true;
        // Persist client gallery context for header dropdown
        $_SESSION['client_gallery'] = [ 'slug' => $slug, 'title' => $galleryData['title'] ?? 'Mijn Galerij' ];
        $loggedIn = true;
    } else {
        $loginError = 'Ongeldig wachtwoord.';
    }
}

// If already logged in by session, ensure the client context is present
if ($loggedIn && empty($_SESSION['client_gallery'])) {
    $_SESSION['client_gallery'] = [ 'slug' => $slug, 'title' => $galleryData['title'] ?? 'Mijn Galerij' ];
}

function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($galleryData['title']); ?> - Andrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo @filemtime('style.css'); ?>">
    <style>
        /* Proof lightbox tuned to match site style */
        #lightbox { background-color: rgba(25,25,25,0.9); backdrop-filter: blur(8px); transition: opacity 0.25s ease-in-out; }
        .polaroid { background: var(--surface); padding: 1rem; box-shadow: var(--shadow); max-width: 92vw; max-height: 86vh; border-radius: var(--radius); }
        .lightbox-nav { color: white; font-size: 2.5rem; }
        @media (max-width: 768px) { #lightbox-prev, #lightbox-next { display: none; } }
        #toast-popup { transition: opacity 0.3s ease, transform 0.3s ease; z-index: 1000; }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <?php
        // Load site content for footer (instagram etc.)
        $siteContent = file_exists(CONTENT_FILE) ? json_decode(file_get_contents(CONTENT_FILE), true) : [];
        $instagramUrl = $siteContent['contact']['instagram_url'] ?? '';
        $page = 'proof';
        include TEMPLATES_DIR . '/header.php';
    ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 py-12">
        <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-center"><?php echo e($galleryData['title']); ?></h1>

        <?php if (!$loggedIn): ?>
            <div class="max-w-sm mx-auto bg-white p-8 rounded-lg shadow-md mt-8">
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="password">Wachtwoord</label>
                        <input type="password" name="password" id="password" class="w-full px-4 py-2 border border-gray-300 rounded-md" required>
                    </div>
                    <?php if ($loginError): ?><p class="text-red-500 text-sm"><?php echo e($loginError); ?></p><?php endif; ?>
                    <button type="submit" class="w-full btn btn-primary">Bekijk foto's</button>
                </form>
            </div>
        <?php else: ?>
            <?php if (empty($galleryData['photos'])): ?>
                <p class="text-center text-gray-600">Er zijn nog geen foto's in deze galerij.</p>
            <?php else: ?>
                <?php
                    $maxSelect = $galleryData['max_select'] ?? 0;
                    $selectedCount = count(array_filter($galleryData['photos'], fn($p) => !empty($p['favorite'])));
                ?>
                <div class="mb-8 text-center text-gray-700 max-w-2xl mx-auto">
                    <?php if ($maxSelect > 0): ?>
                        <p id="selectionInfo" class="mt-2 font-semibold text-lg"><?php echo $selectedCount; ?> / <?php echo $maxSelect; ?> geselecteerd</p>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <?php foreach ($galleryData['photos'] as $index => $photo): ?>
                        <div class="relative group cursor-pointer" data-index="<?php echo $index; ?>" onclick="openLightbox(<?php echo $index; ?>)">
                            <picture>
                                <?php if (isset($photo['webp'])): ?><source srcset="<?php echo e($photo['webp']); ?>" type="image/webp"><?php endif; ?>
                                <img src="<?php echo e($photo['path']); ?>" alt="Galerij foto <?php echo $index + 1; ?>" class="w-full aspect-square object-cover rounded-lg shadow-sm">
                            </picture>
                            <div class="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg pointer-events-none"></div>
                            <button type="button" class="select-toggle absolute top-2 right-2 h-8 w-8 z-10 rounded-full flex items-center justify-center bg-white/70 text-green-600 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition" onclick="event.stopPropagation(); toggleFavorite(<?php echo $index; ?>);" aria-label="Selecteer foto">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </button>
                            <?php $c = trim($photo['comment'] ?? ''); if ($c !== ''): ?>
                                <span class="comment-badge absolute bottom-2 left-2 h-6 w-6 rounded-full flex items-center justify-center bg-blue-500 text-white shadow-md" title="<?php echo e($c); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M5 3.75A1.75 1.75 0 016.75 2h6.5A1.75 1.75 0 0115 3.75v7.5A1.75 1.75 0 0113.25 13h-6.5A1.75 1.75 0 015 11.25v-7.5zm1.75-.25a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h6.5a.25.25 0 00.25-.25v-7.5a.25.25 0 00-.25-.25h-6.5z" clip-rule="evenodd" /><path d="M6 15.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5a.75.75 0 01-.75-.75z" /></svg>
                                </span>
                            <?php else: ?>
                                <span class="comment-badge absolute bottom-2 left-2 h-6 w-6 rounded-full hidden items-center justify-center bg-blue-500 text-white shadow-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M5 3.75A1.75 1.75 0 016.75 2h6.5A1.75 1.75 0 0115 3.75v7.5A1.75 1.75 0 0113.25 13h-6.5A1.75 1.75 0 015 11.25v-7.5zm1.75-.25a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h6.5a.25.25 0 00.25-.25v-7.5a.25.25 0 00-.25-.25h-6.5z" clip-rule="evenodd" /><path d="M6 15.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5a.75.75 0 01-.75-.75z" /></svg>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include TEMPLATES_DIR . '/footer.php'; ?>

    <!-- Help Modal -->
    <div id="help-modal" class="fixed inset-0 z-[999] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-11/12 max-w-lg p-6">
            <h3 class="text-xl font-semibold mb-4" style="font-family: var(--font-heading);">Zo werkt het</h3>
            <ul class="list-disc pl-6 space-y-2 text-slate-700">
                <li>Klik op een foto om ze te selecteren of te bekijken</li>
                <li>Navigeer met de pijltoetsen of swipe op je mobiel toestel</li>
                <li>Druk op S om snel te selecteren of deselecteren</li>
                <li>Voeg eventueel een opmerking toe bij je gekozen foto's (bijvoorbeeld een bewerkingsvoorstel, gewenste crop, zwart/wit, …)</li>
                <li>Klaar? Tik bovenaan op je galerij en kies "Keuze doorsturen"</li>
            </ul>
            <div class="text-right mt-6">
                <button id="help-close" class="btn btn-secondary">Sluiten</button>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <button id="lightbox-close" class="lightbox-nav absolute top-4 right-6 z-50">&times;</button>
        <button id="lightbox-prev" class="lightbox-nav absolute left-4 top-1/2 -translate-y-1/2 z-50">&lt;</button>
        <button id="lightbox-next" class="lightbox-nav absolute right-4 top-1/2 -translate-y-1/2 z-50">&gt;</button>
        <div class="polaroid relative flex flex-col items-center">
            <img id="lightbox-img" src="" alt="" class="w-auto h-auto max-h-[60vh] object-contain">
            <div class="mt-4 w-full max-w-2xl">
                <div class="flex items-center justify-center">
                    <label class="inline-flex items-center gap-3 select-none">
                        <input id="lightbox-select-toggle" type="checkbox" class="peer hidden">
                        <span class="h-9 w-9 rounded-md flex items-center justify-center bg-white/80 text-green-600 shadow transition peer-checked:bg-green-500 peer-checked:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </span>
                    </label>
                </div>
                <div id="lightbox-comment-wrap" class="mt-3 hidden">
                    <textarea id="lightbox-comment" class="w-full p-2 rounded-md text-sm resize-none" placeholder="Opmerking toevoegen..." rows="2"></textarea>
                </div>
                <p id="lightbox-helper" class="mt-2 text-sm text-white/60 hidden">Tip: gebruik "S" om te selecteren/deselecteren, pijlen om te wisselen</p>
            </div>
        </div>
    </div>

    <!-- Toast Pop-up -->
    <div id="toast-popup" class="fixed bottom-5 right-5 bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg opacity-0 transform translate-y-4"></div>

    <!-- Deselect Confirm Modal -->
    <div id="deselect-modal" class="fixed inset-0 z-[999] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/50"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-11/12 max-w-md p-6 text-center">
            <p class="text-lg mb-6">Als je de selectie ongedaan maakt, wordt je comment ook verwijderd. Ben je zeker?</p>
            <div class="flex justify-center gap-3">
                <button id="deselect-no" class="btn btn-secondary">Nee</button>
                <button id="deselect-yes" class="btn btn-primary">Ja</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!<?php echo $loggedIn ? 'true' : 'false'; ?>) return;

        const galleryPhotos = <?php echo json_encode($galleryData['photos']); ?>;
        const maxSelect = <?php echo intval($galleryData['max_select'] ?? 0); ?>;
        let selectedCount = <?php echo $selectedCount; ?>;
        const selectionInfo = document.getElementById('selectionInfo');

        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('toast-popup');
            if (!toast) return;
            toast.textContent = message;
            toast.style.backgroundColor = isSuccess ? '#28a745' : '#dc3545';
            toast.classList.remove('opacity-0', 'translate-y-4');
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-4');
            }, 3000);
        }

        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const lightboxSelectToggle = document.getElementById('lightbox-select-toggle');
        const lightboxComment = document.getElementById('lightbox-comment');
        const lightboxCommentWrap = document.getElementById('lightbox-comment-wrap');
        let currentImageIndex = 0;

        function updateSelectionCount() {
            selectedCount = galleryPhotos.filter(p => p.favorite).length;
            if (selectionInfo && maxSelect > 0) {
                selectionInfo.textContent = `${selectedCount} / ${maxSelect} geselecteerd`;
            }
            updateDisabledStates();
        }

        function updateDisabledStates() {
            const limitReached = (maxSelect > 0 && selectedCount >= maxSelect);
            // Disable all non-selected grid toggles when limit is reached
            document.querySelectorAll('[data-index]').forEach((el, i) => {
                const btn = el.querySelector('.select-toggle');
                if (!btn) return;
                const isFav = !!galleryPhotos[i].favorite;
                const shouldDisable = limitReached && !isFav;
                btn.dataset.disabled = shouldDisable ? '1' : '0';
                btn.classList.toggle('cursor-not-allowed', shouldDisable);
                btn.classList.toggle('opacity-50', shouldDisable);
            });
            // Lightbox checkbox disabled state if viewing a non-selected photo while limit reached
            if (lightboxSelectToggle) {
                const cur = galleryPhotos[currentImageIndex] || {};
                lightboxSelectToggle.disabled = limitReached && !cur.favorite;
            }
        }

        function toggleFavorite(index) {
            const photo = galleryPhotos[index];
            if (maxSelect > 0 && !photo.favorite && selectedCount >= maxSelect) {
                showToast(`Maximaal ${maxSelect} foto's toegestaan.`, false);
                return;
            }
            photo.favorite = !photo.favorite;
            updateSelectionCount();
            updatePhotoUI(index);
            updateLightboxUI();
            fetch('proof_save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=update_photo&gallery=<?php echo e($slug); ?>&index=${index}&favorite=${photo.favorite ? '1' : '0'}`
            });
        }

        function updatePhotoUI(index) {
            const container = document.querySelector(`[data-index="${index}"]`);
            if (!container) return;
            const toggleBtn = container.querySelector('.select-toggle');
            if (galleryPhotos[index].favorite) {
                toggleBtn.classList.add('bg-green-500', 'text-white');
                toggleBtn.classList.remove('bg-white/70', 'text-green-600');
                // Always visible on desktop when selected
                toggleBtn.classList.add('md:opacity-100');
                toggleBtn.classList.remove('md:opacity-0');
            } else {
                toggleBtn.classList.add('bg-white/70', 'text-green-600');
                toggleBtn.classList.remove('bg-green-500', 'text-white');
                // On desktop, hide until hover when not selected
                toggleBtn.classList.remove('md:opacity-100');
                toggleBtn.classList.add('md:opacity-0');
            }
            // Update comment badge visibility/title
            const badge = container.querySelector('.comment-badge');
            const comment = (galleryPhotos[index].comment || '').trim();
            if (badge) {
                if (comment !== '') {
                    badge.title = comment;
                    // Ensure correct icon is present immediately
                    badge.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M5 3.75A1.75 1.75 0 016.75 2h6.5A1.75 1.75 0 0115 3.75v7.5A1.75 1.75 0 0113.25 13h-6.5A1.75 1.75 0 015 11.25v-7.5zm1.75-.25a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h6.5a.25.25 0 00.25-.25v-7.5a.25.25 0 00-.25-.25h-6.5z" clip-rule="evenodd" /><path d="M6 15.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5a.75.75 0 01-.75-.75z" /></svg>';
                    badge.classList.remove('hidden');
                } else {
                    badge.title = '';
                    badge.innerHTML = '';
                    badge.classList.add('hidden');
                }
            }
        }

        function updateLightboxUI() {
            const photo = galleryPhotos[currentImageIndex];
            lightboxImg.src = photo.path;
            lightboxComment.value = photo.comment || '';
            lightboxSelectToggle.checked = !!photo.favorite;
            const showComment = !!photo.favorite;
            lightboxComment.disabled = !showComment;
            if (lightboxCommentWrap) lightboxCommentWrap.classList.toggle('hidden', !showComment);
        }

        galleryPhotos.forEach((_, i) => updatePhotoUI(i));
        updateSelectionCount();

        window.openLightbox = function(index) {
            currentImageIndex = index;
            updateLightboxUI();
            lightbox.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function showNextImage() { currentImageIndex = (currentImageIndex + 1) % galleryPhotos.length; updateLightboxUI(); }
        function showPrevImage() { currentImageIndex = (currentImageIndex - 1 + galleryPhotos.length) % galleryPhotos.length; updateLightboxUI(); }

        document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
        document.getElementById('lightbox-next').addEventListener('click', showNextImage);
        document.getElementById('lightbox-prev').addEventListener('click', showPrevImage);
        lightboxSelectToggle.addEventListener('change', () => toggleFavorite(currentImageIndex));

        // Ensure checkbox in grid always works (delegate clicks)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.select-toggle');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            if (btn.dataset.disabled === '1') { showToast(`Maximaal ${maxSelect} foto's toegestaan.`, false); return; }
            const parent = btn.closest('[data-index]');
            if (!parent) return;
            const idx = parseInt(parent.getAttribute('data-index'), 10);
            if (!Number.isNaN(idx)) toggleFavorite(idx);
        });

        document.addEventListener('keydown', (e) => {
            if (lightbox.classList.contains('hidden') || document.activeElement === lightboxComment) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight') showNextImage();
            if (e.key === 'ArrowLeft') showPrevImage();
            if (window.innerWidth >= 768 && (e.key === 's' || e.key === 'S')) {
                lightboxSelectToggle.checked = !lightboxSelectToggle.checked;
                toggleFavorite(currentImageIndex);
            }
        });

        let touchStartX = 0;
        lightbox.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; });
        lightbox.addEventListener('touchend', e => {
            let touchEndX = e.changedTouches[0].clientX;
            if (touchStartX - touchEndX > 50) showNextImage();
            if (touchEndX - touchStartX > 50) showPrevImage();
        });

        let debounceTimer;
        lightboxComment.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (this.disabled) return;
                const newComment = this.value;
                galleryPhotos[currentImageIndex].comment = newComment;
                // Reflect comment in grid badge as you type
                updatePhotoUI(currentImageIndex);
                fetch('proof_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_photo&gallery=<?php echo e($slug); ?>&index=${currentImageIndex}&comment=${encodeURIComponent(newComment)}`
                });
            }, 500);
        });

        // Selection finalize is handled from header dropdown

        // Help modal controls
        const helpModal = document.getElementById('help-modal');
        function openHelp() { helpModal.classList.remove('hidden'); helpModal.classList.add('flex'); document.body.style.overflow='hidden'; }
        function closeHelp() { helpModal.classList.add('hidden'); helpModal.classList.remove('flex'); document.body.style.overflow='auto'; }
        document.getElementById('help-close')?.addEventListener('click', closeHelp);
        document.getElementById('client-help-link')?.addEventListener('click', function(e){ e.preventDefault(); openHelp(); });
        document.getElementById('mobile-client-help')?.addEventListener('click', function(e){ e.preventDefault(); openHelp(); });
        helpModal.addEventListener('click', function(e){ if (e.target === helpModal) closeHelp(); });

        // Open help if requested via query (?help=1)
        <?php if (isset($_GET['help']) && $_GET['help']): ?>
            openHelp();
        <?php endif; ?>

        // Deselect confirm modal utilities
        function askDeselectConfirm() {
            return new Promise(resolve => {
                const modal = document.getElementById('deselect-modal');
                const yesBtn = document.getElementById('deselect-yes');
                const noBtn = document.getElementById('deselect-no');
                const cleanup = () => {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                    document.body.style.overflow='auto';
                    yesBtn.removeEventListener('click', onYes);
                    noBtn.removeEventListener('click', onNo);
                };
                const onYes = () => { cleanup(); resolve(true); };
                const onNo = () => { cleanup(); resolve(false); };
                yesBtn.addEventListener('click', onYes);
                noBtn.addEventListener('click', onNo);
                modal.classList.remove('hidden'); modal.classList.add('flex'); document.body.style.overflow='hidden';
            });
        }

        // Core toggle with limit + confirm logic
        async function toggleFavorite(index) {
            const photo = galleryPhotos[index];
            const limitReached = maxSelect > 0 && selectedCount >= maxSelect;
            if (!photo.favorite && limitReached) {
                showToast(`Maximaal ${maxSelect} foto's toegestaan.`, false);
                // revert lightbox checkbox if needed
                if (index === currentImageIndex) { lightboxSelectToggle.checked = false; }
                return;
            }
            // Confirm when deselecting a photo that has a comment
            if (photo.favorite && (photo.comment || '').trim() !== '') {
                const ok = await askDeselectConfirm();
                if (!ok) {
                    // Revert UI if user cancels
                    if (index === currentImageIndex) { lightboxSelectToggle.checked = true; }
                    return;
                }
                // Clear comment when deselecting after confirmation
                photo.comment = '';
            }

            photo.favorite = !photo.favorite;
            updateSelectionCount();
            updatePhotoUI(index);
            updateLightboxUI();

            // Persist favorite and possibly cleared comment in one call
            const params = new URLSearchParams();
            params.append('action', 'update_photo');
            params.append('gallery', '<?php echo e($slug); ?>');
            params.append('index', index);
            params.append('favorite', photo.favorite ? '1' : '0');
            if ((photo.comment || '') === '') { params.append('comment', ''); }
            fetch('proof_save.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() });
        }
    });
    </script>
<script src="main.js"></script>
</body>
</html>
