<?php
/**
 * Global site header
 *
 * Expects $page variable to indicate current page (e.g. 'index', 'team', 'practice', 'links').
 */
$homeLink = ($page === 'index') ? '#home' : 'index.php';
$bioLink = ($page === 'index') ? '#bio' : 'index.php#bio';
$contactLink = ($page === 'index') ? '#contact' : 'index.php#contact';
$headerClass = ($page === 'index') ? 'fixed' : 'sticky';
$siteTitle = 'Groepspraktijk Elewijt';

// Load site config and new data sources
$siteCfg = [];
if (defined('CONTENT_FILE') && file_exists(CONTENT_FILE)) {
    $siteCfg = json_decode(file_get_contents(CONTENT_FILE), true) ?: [];
}
$settings = $siteCfg['settings'] ?? [];
$appointmentUrl = $settings['appointment_url'] ?? '';
$phoneNumber = $settings['phone'] ?? '';

// Load team for dropdown
$teamMembers = [];
$teamFile = (defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/../data/team/team.json'));
if (file_exists($teamFile)) {
    $td = json_decode(file_get_contents($teamFile), true) ?: [];
    $teamMembers = $td['members'] ?? [];
}

// Load practice info pages for dropdown
$practicePages = [];
$practiceFile = (defined('PRACTICE_FILE') ? PRACTICE_FILE : (defined('DATA_DIR') ? DATA_DIR . '/practice/practice.json' : __DIR__ . '/../data/practice/practice.json'));
if (file_exists($practiceFile)) {
    $pd = json_decode(file_get_contents($practiceFile), true) ?: [];
    $practicePages = $pd['pages'] ?? [];
}

// Client gallery session context (set after login on proof.php)
$clientGallery = $_SESSION['client_gallery'] ?? null;
?>
<header id="header" class="<?php echo $headerClass; ?> top-0 left-0 w-full z-50">
    <nav class="container mx-auto px-6 flex justify-between items-center h-24">
        <a href="<?php echo $homeLink; ?>" class="text-2xl font-bold" style="font-family: var(--font-heading);"><?php echo htmlspecialchars($siteTitle); ?></a>

        <div class="hidden md:flex items-center space-x-10 absolute left-1/2 -translate-x-1/2">
            <div class="relative header-dropdown">
                <a href="team.php" class="nav-link<?php echo $page === 'team' ? ' active' : ''; ?> flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
                    <span>Team</span>
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                </a>
                <?php if (!empty($teamMembers)): ?>
                <div class="dropdown-panel">
                    <?php foreach ($teamMembers as $m): $anchor = 'm-' . (!empty($m['id']) ? preg_replace('/[^a-zA-Z0-9_-]/','', $m['id']) : substr(md5(($m['name'] ?? '') . ($m['role'] ?? '')),0,8)); ?>
                        <a href="team.php#<?php echo htmlspecialchars($anchor); ?>" class="dropdown-item"><?php echo htmlspecialchars($m['name'] ?? ''); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="relative header-dropdown">
                <a href="practice.php" class="nav-link<?php echo $page === 'practice' ? ' active' : ''; ?> flex items-center gap-2" aria-haspopup="true" aria-expanded="false">
                    <span>Praktijkinfo</span>
                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                </a>
                <?php if (!empty($practicePages)): ?>
                <div class="dropdown-panel">
                    <?php foreach ($practicePages as $slug => $pd): ?>
                        <a href="practice.php?slug=<?php echo urlencode($slug); ?>" class="dropdown-item"><?php echo htmlspecialchars($pd['title'] ?? $slug); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="links.php" class="nav-link<?php echo $page === 'links' ? ' active' : ''; ?>">Nuttige Links</a>
        </div>

        <div class="hidden md:flex items-center gap-4">
            <?php if (!empty($appointmentUrl)): ?>
            <a href="<?php echo htmlspecialchars(safeUrl($appointmentUrl)); ?>" target="_blank" class="btn btn-primary">Maak afspraak</a>
            <?php endif; ?>
            <?php if (!empty($phoneNumber)): ?>
            <a href="tel:<?php echo htmlspecialchars($phoneNumber); ?>" class="text-lg font-medium">Tel: <?php echo htmlspecialchars($phoneNumber); ?></a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['loggedin'])): ?>
              <a href="<?php echo (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php'); ?>" class="nav-link">Admin</a>
            <?php endif; ?>
        </div>

        <div class="md:hidden">
            <button id="mobile-menu-button" class="focus:outline-none"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg></button>
        </div>
    </nav>
</header>
<div id="mobile-menu" class="hidden md:hidden fixed top-24 left-0 right-0 z-40">
    <div class="mobile-menu-panel mx-4">
        <nav class="p-4 flex flex-col gap-2">
            <div class="w-full">
                <div class="flex items-center gap-2">
                    <a href="team.php" class="mobile-nav-link flex-1 block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Team</a>
                    <button id="mobile-team-toggle" class="p-3 rounded-lg hover:bg-white/10" aria-controls="mobile-team-menu" aria-expanded="false">
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <?php if (!empty($teamMembers)): ?>
                <div id="mobile-team-menu" class="hidden mt-2 grid gap-2 px-2">
                    <?php foreach ($teamMembers as $m): $anchor = 'm-' . (!empty($m['id']) ? preg_replace('/[^a-zA-Z0-9_-]/','', $m['id']) : substr(md5(($m['name'] ?? '') . ($m['role'] ?? '')),0,8)); ?>
                        <a href="team.php#<?php echo htmlspecialchars($anchor); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">
                            <?php echo htmlspecialchars($m['name'] ?? ''); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="w-full">
                <div class="flex items-center gap-2">
                    <a href="practice.php" class="mobile-nav-link flex-1 block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Praktijkinfo</a>
                    <button id="mobile-practice-toggle" class="p-3 rounded-lg hover:bg-white/10" aria-controls="mobile-practice-menu" aria-expanded="false">
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <?php if (!empty($practicePages)): ?>
                <div id="mobile-practice-menu" class="hidden mt-2 grid gap-2 px-2">
                    <?php foreach ($practicePages as $slug => $pd): ?>
                        <a href="practice.php?slug=<?php echo urlencode($slug); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">
                            <?php echo htmlspecialchars($pd['title'] ?? $slug); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="links.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Nuttige Links</a>
            <?php if (!empty($appointmentUrl)): ?>
            <a href="<?php echo htmlspecialchars($appointmentUrl); ?>" target="_blank" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Maak afspraak</a>
            <?php endif; ?>
            <?php if (!empty($phoneNumber)): ?>
            <a href="tel:<?php echo htmlspecialchars($phoneNumber); ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Tel: <?php echo htmlspecialchars($phoneNumber); ?></a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['loggedin'])): ?>
            <a href="<?php echo (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php'); ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-white/10">Admin</a>
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

