<?php
/**
 * Global site header
 *
 * Expects $page variable to indicate current page (e.g. 'index', 'team', 'practice', 'links').
 */
$homeLink = 'index.php';
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

// Load practice info pages for dropdown
$practicePages = [];
if (defined('PRACTICE_FILE') && file_exists(PRACTICE_FILE)) {
    $pd = json_decode(file_get_contents(PRACTICE_FILE), true) ?: [];
    $practicePages = $pd['pages'] ?? [];
}
?>
<header id="header" class="<?php echo $headerClass; ?> top-0 left-0 w-full z-50">
    <div class="container mx-auto px-6 flex justify-between items-center h-24">
        <a href="<?php echo $homeLink; ?>" class="text-2xl font-bold" style="font-family: var(--font-heading);"><?php echo htmlspecialchars($siteTitle); ?></a>

        <div class="hidden md:flex items-center space-x-10 absolute left-1/2 -translate-x-1/2">
            <a href="team.php" class="nav-link<?php echo $page === 'team' ? ' active' : ''; ?>">Team</a>
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
            <a href="phones.php" class="nav-link<?php echo $page === 'phones' ? ' active' : ''; ?>">Nuttige telefoonnummers</a>
            <a href="links.php" class="nav-link<?php echo $page === 'links' ? ' active' : ''; ?>">Nuttige links</a>
        </div>

        <div class="hidden md:flex items-center gap-4">
            <?php if (!empty($appointmentUrl)): ?>
            <a href="<?php echo htmlspecialchars(safeUrl($appointmentUrl)); ?>" target="_blank" class="btn btn-primary">Maak afspraak</a>
            <?php endif; ?>
            <?php if (!empty($phoneNumber)): ?>
            <a href="tel:<?php echo htmlspecialchars(trim($phoneNumber)); ?>" class="text-lg font-medium">Tel: <?php echo htmlspecialchars(trim($phoneNumber)); ?></a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['loggedin'])): ?>
              <a href="<?php echo (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php'); ?>" class="nav-link">Admin</a>
            <?php endif; ?>
        </div>

        <div class="md:hidden">
            <button id="mobile-menu-button" class="focus:outline-none p-2 -mr-2"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg></button>
        </div>
    </div>
</header>
<div id="mobile-menu" class="hidden md:hidden fixed top-24 left-0 right-0 z-40">
    <div class="mobile-menu-panel mx-4">
        <nav class="p-4 flex flex-col gap-2">
            <a href="team.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Team</a>
            <div class="w-full">
                <a href="practice.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Praktijkinfo</a>
                <?php if (!empty($practicePages)): ?>
                <div id="mobile-practice-menu" class="grid gap-2 px-2 pt-2">
                    <?php foreach ($practicePages as $slug => $pd): ?>
                        <a href="practice.php?slug=<?php echo urlencode($slug); ?>" class="mobile-nav-link block text-base py-2 border border-[var(--border)] rounded-full text-center" style="background: var(--surface);">
                            <?php echo htmlspecialchars($pd['title'] ?? $slug); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="phones.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Nuttige telefoonnummers</a>
            <a href="links.php" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Nuttige links</a>
            <?php if (!empty($appointmentUrl)): ?>
            <a href="<?php echo htmlspecialchars($appointmentUrl); ?>" target="_blank" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Maak afspraak</a>
            <?php endif; ?>
            <?php if (!empty($phoneNumber)): ?>
            <a href="tel:<?php echo htmlspecialchars(trim($phoneNumber)); ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Tel: <?php echo htmlspecialchars(trim($phoneNumber)); ?></a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['loggedin'])): ?>
            <a href="<?php echo (defined('ADMIN_PANEL_FILE') ? ADMIN_PANEL_FILE : 'beheer-gpe-a4x7.php'); ?>" class="mobile-nav-link block text-lg px-4 py-3 rounded-lg hover:bg-slate-50">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</div>
