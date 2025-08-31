<?php
/**
 * Global site header
 *
 * Expects $page variable to indicate current page (e.g. 'index', 'portfolio').
 */
$homeLink = ($page === 'index') ? '#home' : 'index.php';
$bioLink = ($page === 'index') ? '#bio' : 'index.php#bio';
$contactLink = ($page === 'index') ? '#contact' : 'index.php#contact';
$headerClass = ($page === 'index') ? 'fixed' : 'sticky';

// Load portfolio themes for dropdown (safe fallback if file missing)
$portfolioThemes = [];
if (defined('PORTFOLIO_FILE') && file_exists(PORTFOLIO_FILE)) {
    $pf = json_decode(file_get_contents(PORTFOLIO_FILE), true) ?: [];
    if (!empty($pf['themes']) && is_array($pf['themes'])) {
        $portfolioThemes = array_keys($pf['themes']);
    }
}

// Client gallery session context (set after login on proof.php)
$clientGallery = $_SESSION['client_gallery'] ?? null;
?>
<header id="header" class="<?php echo $headerClass; ?> top-0 left-0 w-full z-50">
    <nav class="container mx-auto px-6 flex justify-between items-center h-24">
        <a href="<?php echo $homeLink; ?>" class="text-2xl font-bold" style="font-family: var(--font-heading);">Andrew</a>
        <div class="hidden md:flex items-center space-x-10">
            <a href="<?php echo $bioLink; ?>" class="nav-link">Over Mij</a>
            <div class="relative header-dropdown">
                <a href="portfolio.php" class="nav-link<?php echo $page === 'portfolio' ? ' active' : ''; ?> flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
                    <span>Portfolio</span>
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                </a>
                <?php if (!empty($portfolioThemes)): ?>
                <div class="dropdown-panel">
                    <?php foreach ($portfolioThemes as $themeName): ?>
                        <a href="portfolio.php?theme=<?php echo urlencode($themeName); ?>" class="dropdown-item"><?php echo htmlspecialchars(ucfirst($themeName)); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($clientGallery): ?>
            <div id="client-menu-desktop" class="relative header-dropdown">
                <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="nav-link flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
                    <span><?php echo htmlspecialchars($clientGallery['title'] ?? 'Mijn galerij'); ?></span>
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                </a>
                <div class="dropdown-panel">
                    <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="dropdown-item">Mijn galerij</a>
                    <a href="#" id="client-send-selection" data-slug="<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="dropdown-item">Keuze doorsturen</a>
                    <?php if (($page ?? '') === 'proof'): ?>
                        <a href="#" id="client-help-link" class="dropdown-item">Help</a>
                    <?php else: ?>
                        <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>&help=1" class="dropdown-item">Help</a>
                    <?php endif; ?>
                    <a href="client_logout.php" class="dropdown-item">Log uit</a>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo $contactLink; ?>" class="nav-link">Contact</a>
            <?php if (!empty($_SESSION['loggedin'])): ?>
              <a href="admin.php" class="nav-link">Admin</a>
            <?php endif; ?>
        </div>
        <div class="md:hidden"><button id="mobile-menu-button" class="focus:outline-none"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg></button></div>
    </nav>
</header>
<div id="mobile-menu" class="hidden md:hidden fixed top-24 left-0 right-0 z-40">
    <div class="mobile-menu-panel mx-4">
        <nav class="p-4 flex flex-col gap-2">
            <a href="<?php echo $bioLink; ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Over Mij</a>
            <div class="w-full">
                <div class="flex items-center gap-2">
                    <a href="portfolio.php" class="mobile-nav-link flex-1 block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Portfolio</a>
                    <button id="mobile-portfolio-toggle" class="p-3 rounded-lg hover:bg-white/10" aria-controls="mobile-portfolio-menu" aria-expanded="false">
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <?php if (!empty($portfolioThemes)): ?>
                <div id="mobile-portfolio-menu" class="hidden mt-2 grid gap-2 px-2">
                    <?php foreach ($portfolioThemes as $themeName): ?>
                        <a href="portfolio.php?theme=<?php echo urlencode($themeName); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">
                            <?php echo htmlspecialchars(ucfirst($themeName)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($clientGallery): ?>
            <div id="mobile-client-block" class="w-full">
                <div class="flex items-center gap-2">
                    <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="mobile-nav-link flex-1 block text-lg px-4 py-3 rounded-lg hover:bg-white/10">
                        <?php echo htmlspecialchars($clientGallery['title'] ?? 'Mijn galerij'); ?>
                    </a>
                    <button id="mobile-client-toggle" class="p-3 rounded-lg hover:bg-white/10" aria-controls="mobile-client-menu" aria-expanded="false">
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <div id="mobile-client-menu" class="hidden mt-2 grid gap-2 px-2">
                    <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">Mijn galerij</a>
                    <a href="#" id="mobile-client-send-selection" data-slug="<?php echo htmlspecialchars($clientGallery['slug']); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">Keuze doorsturen</a>
                    <?php if (($page ?? '') === 'proof'): ?>
                        <a href="#" id="mobile-client-help" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">Help</a>
                    <?php else: ?>
                        <a href="proof.php?gallery=<?php echo htmlspecialchars($clientGallery['slug']); ?>&help=1" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">Help</a>
                    <?php endif; ?>
                    <a href="client_logout.php" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">Log uit</a>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo $contactLink; ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Contact</a>
            <?php if (!empty($_SESSION['loggedin'])): ?>
            <a href="admin.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<!-- Client confirm modal -->
<div id="client-confirm-modal" class="hidden fixed inset-0 z-[999] flex items-center justify-center" aria-hidden="true">
  <div class="absolute inset-0 bg-black/50"></div>
  <div class="relative bg-white rounded-xl shadow-2xl w-11/12 max-w-md p-6 text-center">
    <h3 class="text-xl font-semibold mb-2" style="font-family: var(--font-heading);">Keuze doorsturen</h3>
    <p class="text-slate-600 mb-6">Ben je zeker dat je je keuze definitief wil doorsturen?</p>
    <div class="flex justify-center gap-3">
      <button id="client-confirm-no" class="btn btn-secondary">Nee</button>
      <button id="client-confirm-yes" class="btn btn-primary">Ja</button>
    </div>
  </div>
  </div>
