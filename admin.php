<?php
/**
 * Admin panel
 *
 * Provides a modern, sidebar-based interface to manage website content.
 * The UI uses URL hashes (e.g. #team) to preserve context.
 */
session_start();
require_once 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Helper function to load JSON data
function loadContent($file) {
    if (file_exists($file)) { return json_decode(file_get_contents($file), true) ?: []; }
    return [];
}

// Load all necessary data sources
$content = loadContent(CONTENT_FILE);
$teamData = loadContent(TEAM_FILE);
$practiceData = loadContent(PRACTICE_FILE);
$linksData = loadContent(LINKS_FILE);

// Sanitize team member IDs (ensure every member has a stable id)
if (!empty($teamData['members']) && is_array($teamData['members'])) {
    $changed = false;
    foreach ($teamData['members'] as &$m) {
        if (empty($m['id'])) { $m['id'] = uniqid('tm_', true); $changed = true; }
    }
    unset($m);
    if ($changed) { saveJsonFile(TEAM_FILE, $teamData); }
}
$pass_status = $_GET['password_change'] ?? '';
$save_status = $_GET['save_status'] ?? '';

// Include SVG icons
$icons = include 'assets/admin-icons.php';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php $adminCssVersion = @filemtime('admin.css'); ?>
    <link rel="stylesheet" href="admin.css?v=<?php echo $adminCssVersion; ?>">
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.0/classic/ckeditor.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
</head>
<body class="admin-body">

    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div>
                <h1 class="text-2xl font-bold text-white px-4">Admin</h1>
                <nav class="mt-8">
                    <a href="#homepage" class="admin-nav-link" data-tab="homepage"><?php echo $icons['home']; ?><span>Homepage</span></a>
                    <a href="#team" class="admin-nav-link" data-tab="team"><?php echo $icons['team']; ?><span>Team</span></a>
                    <a href="#practice" class="admin-nav-link" data-tab="practice"><?php echo $icons['practice']; ?><span>Praktijkinfo</span></a>
                    <a href="#links" class="admin-nav-link" data-tab="links"><?php echo $icons['links']; ?><span>Links & Nummers</span></a>
                    <a href="#pinned" class="admin-nav-link" data-tab="pinned"><?php echo $icons['pinned']; ?><span>Gepinde Berichten</span></a>
                    <a href="#settings" class="admin-nav-link" data-tab="settings"><?php echo $icons['settings']; ?><span>Contact & Adres</span></a>
                    <a href="#security" class="admin-nav-link" data-tab="security"><?php echo $icons['security']; ?><span>Beveiliging</span></a>
                </nav>
            </div>
            <div>
                <a href="index.php" target="_blank" class="admin-nav-link"><?php echo $icons['view']; ?><span>Bekijk Website</span></a>
                <a href="logout.php" class="admin-nav-link"><?php echo $icons['logout']; ?><span>Uitloggen</span></a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main-content">
            <div id="homepage" class="admin-tab-panel">
                <h2 class="admin-page-title">Homepage</h2>
                <div class="card">
                    <form action="save.php" method="POST">
                        <div class="card-body space-y-6">
                            <input type="hidden" name="action" value="update_content">
                             <div>
                                <label class="form-label" for="meta_title">Meta Titel (SEO)</label>
                                <input class="form-input" type="text" name="meta_title" id="meta_title" value="<?php echo htmlspecialchars($content['meta_title'] ?? ''); ?>" placeholder="Titel die in Google verschijnt">
                            </div>
                             <div>
                                <label class="form-label" for="meta_description">Meta Omschrijving (SEO)</label>
                                <input class="form-input" name="meta_description" id="meta_description" value="<?php echo htmlspecialchars($content['meta_description'] ?? ''); ?>" placeholder="Korte omschrijving voor in Google">
                            </div>
                            <hr class="border-slate-200">
                            <div>
                                <label class="form-label">Hero Foto</label>
                                <div id="hero_image_container" class="relative group w-full aspect-[16/6] bg-slate-200 rounded-md cursor-pointer overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($content['hero']['image'] ?? 'https://placehold.co/1200x400/e2e8f0/64748b?text=Hero+Foto'); ?>" class="w-full h-full object-cover" alt="Huidige hero foto">
                                    <div class="dropzone absolute inset-0" data-target="hero"><span>Sleep een nieuwe foto hier of klik</span></div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label" for="hero_title">Hero Titel</label>
                                <input class="form-input" type="text" name="hero_title" id="hero_title" value="<?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label" for="hero_body">Hero Tekst (onder titel)</label>
                                <textarea class="form-textarea richtext" name="hero_body" id="hero_body" rows="4"><?php echo htmlspecialchars($content['hero']['body'] ?? ''); ?></textarea>
                            </div>
                             <hr class="border-slate-200">
                            <div>
                                <label class="form-label">Welkom Titel</label>
                                <input type="text" name="welcome_title" class="form-input" value="<?php echo htmlspecialchars($content['welcome']['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label">Welkom Tekst</label>
                                <textarea name="welcome_text" class="form-textarea richtext" rows="5"><?php echo htmlspecialchars($content['welcome']['text'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="form-label">Welkom Kaarten (optioneel)</label>
                                <?php $wcards = isset($content['welcome']['cards']) && is_array($content['welcome']['cards']) ? $content['welcome']['cards'] : []; ?>
                                <div id="welcome-cards" class="space-y-3">
                                    <?php foreach ($wcards as $c): ?>
                                    <div class="border border-slate-200 rounded-md p-3">
                                        <div class="flex items-center justify-end">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()"><?php echo $icons['delete']; ?> Verwijder</button>
                                        </div>
                                        <textarea name="welcome_card_html[]" class="form-textarea richtext" rows="4"><?php echo htmlspecialchars($c['html'] ?? ''); ?></textarea>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" onclick="window.addWelcomeCard()"><?php echo $icons['add']; ?> Kaart toevoegen</button>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Homepage Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="team" class="admin-tab-panel">
                <h2 class="admin-page-title">Team Beheren</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Bestaande teamleden -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Bestaande Teamleden</h3>
                            </div>
                            <div class="card-body space-y-4" id="team-members-list">
                                <?php $members = $teamData['members'] ?? []; $groups = $teamData['groups'] ?? []; ?>
                                <?php if (empty($members)): ?>
                                <p class="text-slate-500">Nog geen teamleden toegevoegd.</p>
                                <?php else: foreach ($members as $m): $mid = $m['id'] ?? uniqid('tm_'); ?>
                                <div class="team-member-card" data-id="<?php echo htmlspecialchars($mid); ?>">
                                    <div class="flex-shrink-0">
                                        <div class="relative group w-full aspect-[4/5] bg-slate-100 rounded-md overflow-hidden">
                                            <img src="<?php echo htmlspecialchars($m['image'] ? $m['image'] : 'https://placehold.co/400x500/e2e8f0/64748b?text=Foto'); ?>" alt="Foto van <?php echo htmlspecialchars($m['name'] ?? ''); ?>" class="w-full h-full object-cover">
                                            <div class="dropzone absolute inset-0" data-target="team" data-member-id="<?php echo htmlspecialchars($mid); ?>"><span>Sleep foto of klik</span></div>
                                        </div>
                                    </div>
                                    <div class="flex-grow">
                                        <form action="save.php" method="POST" class="space-y-2" id="update-form-<?php echo htmlspecialchars($mid); ?>">
                                            <input type="hidden" name="action" value="update_team_member">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                            <div class="flex justify-between items-center">
                                                 <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                                <label class="inline-flex items-center gap-2">
                                                    <input type="checkbox" name="visible" <?php echo !isset($m['visible']) || $m['visible'] ? 'checked' : ''; ?>>
                                                    <span class="text-sm font-medium">Zichtbaar</span>
                                                </label>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div><label class="form-label">Naam</label><input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>"></div>
                                                <div><label class="form-label">Functie</label><input type="text" name="role" class="form-input" value="<?php echo htmlspecialchars($m['role'] ?? ''); ?>"></div>
                                            </div>
                                            <div><label class="form-label">Afspraak URL</label><input type="text" name="appointment_url" class="form-input" value="<?php echo htmlspecialchars($m['appointment_url'] ?? ''); ?>"></div>
                                            <div>
                                                <label class="form-label">Functiegroep</label>
                                                <select name="group_id" class="form-input">
                                                    <option value="">Geen groep</option>
                                                    <?php foreach ($groups as $g): $gid = $g['id'] ?? ''; ?>
                                                        <option value="<?php echo htmlspecialchars($gid); ?>" <?php echo (($m['group_id'] ?? '') === $gid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name'] ?? ''); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </form>
                                        <div class="flex gap-2 pt-3 mt-auto">
                                            <button type="submit" form="update-form-<?php echo htmlspecialchars($mid); ?>" class="btn btn-secondary"><?php echo $icons['save']; ?> Opslaan</button>
                                            <form action="save.php" method="POST" class="delete-form">
                                                <input type="hidden" name="action" value="delete_team_member">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                                <button type="submit" class="btn btn-danger"><?php echo $icons['delete']; ?> Verwijder</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                         <!-- Nieuw teamlid toevoegen -->
                        <div class="card sticky top-6">
                            <div class="card-header"><h3 class="card-title">Nieuw Teamlid</h3></div>
                            <form action="save.php" method="POST">
                                <div class="card-body space-y-4">
                                    <input type="hidden" name="action" value="add_team_member">
                                    <div><label class="form-label">Naam</label><input type="text" name="name" class="form-input" required></div>
                                    <div><label class="form-label">Functie</label><input type="text" name="role" class="form-input" required></div>
                                    <div><label class="form-label">Afspraak URL (optioneel)</label><input type="url" name="appointment_url" class="form-input" placeholder="https://..."></div>
                                    <div>
                                        <label class="form-label">Functiegroep</label>
                                        <select name="group_id" class="form-input">
                                            <option value="">Geen groep</option>
                                            <?php foreach ($groups as $g): ?>
                                                <option value="<?php echo htmlspecialchars($g['id'] ?? ''); ?>"><?php echo htmlspecialchars($g['name'] ?? ''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="visible" checked><span>Zichtbaar op website</span></label>
                                </div>
                                <div class="card-footer"><button type="submit" class="btn btn-primary w-full"><?php echo $icons['add']; ?> Teamlid toevoegen</button></div>
                            </form>
                        </div>
                        <!-- Functiegroepen -->
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Functiegroepen</h3></div>
                            <div class="card-body space-y-4">
                                <div id="team-groups-list" class="space-y-3">
                                    <?php foreach ($groups as $g): $gid = $g['id'] ?? uniqid('grp_', true); ?>
                                    <div class="border border-slate-200 rounded-lg p-3" data-id="<?php echo htmlspecialchars($gid); ?>">
                                        <form action="save.php" method="POST" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                                <label class="inline-flex items-center gap-2"><input type="checkbox" name="visible" <?php echo !isset($g['visible']) || $g['visible'] ? 'checked' : ''; ?>> <span class="text-sm">Zichtbaar</span></label>
                                            </div>
                                            <input type="hidden" name="action" value="update_team_group"><input type="hidden" name="id" value="<?php echo htmlspecialchars($gid); ?>">
                                            <div><label class="form-label text-sm">Naam</label><input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($g['name'] ?? ''); ?>"></div>
                                            <div><label class="form-label text-sm">Beschrijving</label><input type="text" class="form-input" name="description" value="<?php echo htmlspecialchars($g['description'] ?? ''); ?>"></div>
                                            <div class="flex items-center justify-between pt-2">
                                                 <form action="save.php" method="POST" class="delete-form m-0"><input type="hidden" name="action" value="delete_team_group"><input type="hidden" name="id" value="<?php echo htmlspecialchars($gid); ?>"><button type="submit" class="btn btn-danger btn-sm"><?php echo $icons['delete']; ?></button></form>
                                                <button type="submit" class="btn btn-secondary btn-sm"><?php echo $icons['save']; ?> Opslaan</button>
                                            </div>
                                        </form>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <form action="save.php" method="POST" class="border-t border-slate-200 pt-4">
                                    <h4 class="font-medium mb-2">Nieuwe groep</h4>
                                    <input type="hidden" name="action" value="add_team_group">
                                    <div class="space-y-2">
                                        <input type="text" name="name" class="form-input" placeholder="Naam groep" required>
                                        <input type="text" name="description" class="form-input" placeholder="Beschrijving (optioneel)">
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="visible" checked><span>Zichtbaar</span></label>
                                    </div>
                                    <button type="submit" class="btn btn-secondary w-full mt-3"><?php echo $icons['add']; ?> Groep toevoegen</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="practice" class="admin-tab-panel">
                <h2 class="admin-page-title">Praktijkinfo</h2>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Pagina's en editors -->
                         <div class="card">
                            <div class="card-header"><h3 class="card-title">Pagina's</h3></div>
                            <div class="card-body">
                                <?php $pages = $practiceData['pages'] ?? []; ?>
                                <div id="practice-pages-list" class="space-y-3">
                                <?php if (empty($pages)): ?>
                                    <p class="text-slate-500">Nog geen pagina's. Voeg er een toe om te beginnen.</p>
                                <?php else: foreach ($pages as $slug => $pd): $cards = $pd['cards'] ?? []; ?>
                                    <div class="border border-slate-200 rounded-lg" data-slug="<?php echo htmlspecialchars($slug); ?>">
                                        <div class="p-3 flex justify-between items-center bg-slate-50 rounded-t-lg">
                                            <div class="flex items-center gap-3">
                                                <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                                <strong class="font-medium"><?php echo htmlspecialchars($pd['title'] ?? $slug); ?></strong>
                                            </div>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="this.closest('.border').querySelector('.editor-panel').classList.toggle('hidden')"><?php echo $icons['edit']; ?> Bewerken</button>
                                        </div>
                                        <div class="p-4 editor-panel hidden">
                                            <form action="save.php" method="POST" id="practice-form-<?php echo htmlspecialchars($slug); ?>">
                                                <input type="hidden" name="action" value="save_practice_page">
                                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                                <label class="form-label">Titel</label>
                                                <input type="text" class="form-input" name="title" value="<?php echo htmlspecialchars($pd['title'] ?? ''); ?>">
                                                <div class="flex items-center justify-between mt-4 mb-2">
                                                    <label class="form-label mb-0">Inhoudskaarten</label>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="window.addPracticeCard('cards-<?php echo htmlspecialchars($slug); ?>')"><?php echo $icons['add']; ?> Kaart</button>
                                                </div>
                                                <div class="space-y-3" id="cards-<?php echo htmlspecialchars($slug); ?>">
                                                    <?php foreach ($cards as $c): ?>
                                                    <div class="border border-slate-200 rounded-md p-3">
                                                        <div class="text-right"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div.border').remove()"><?php echo $icons['delete']; ?></button></div>
                                                        <textarea name="card_html[]" class="form-textarea richtext" rows="5"><?php echo htmlspecialchars($c['html'] ?? ''); ?></textarea>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </form>
                                            <div class="flex justify-between items-center mt-4 border-t border-slate-200 pt-4">
                                                <form action="save.php" method="POST" class="delete-form m-0">
                                                    <input type="hidden" name="action" value="delete_practice_page">
                                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                                    <button type="submit" class="btn btn-danger"><?php echo $icons['delete']; ?> Pagina verwijderen</button>
                                                </form>
                                                <button type="submit" form="practice-form-<?php echo htmlspecialchars($slug); ?>" class="btn btn-primary"><?php echo $icons['save']; ?> Pagina opslaan</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <!-- Nieuwe pagina & Hero -->
                        <div class="card sticky top-6">
                             <div class="card-header"><h3 class="card-title">Nieuwe Pagina</h3></div>
                             <form action="save.php" method="POST">
                                <div class="card-body">
                                    <input type="hidden" name="action" value="save_practice_page">
                                    <label class="form-label">Titel nieuwe pagina</label>
                                    <input type="text" class="form-input" name="title" required>
                                </div>
                                <div class="card-footer"><button type="submit" class="btn btn-primary w-full"><?php echo $icons['add']; ?> Pagina toevoegen</button></div>
                            </form>
                        </div>
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Hero Sectie</h3></div>
                            <form action="save.php" method="POST">
                                <div class="card-body">
                                    <input type="hidden" name="action" value="save_practice_hero">
                                    <?php $practiceHero = $practiceData['hero'] ?? []; ?>
                                    <label class="form-label">Hero Afbeelding</label>
                                    <div class="relative group w-full aspect-video bg-slate-100 rounded-md overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($practiceHero['image'] ?? 'https://placehold.co/800x450/e2e8f0/64748b?text=Hero+Afbeelding'); ?>" class="w-full h-full object-cover">
                                        <div class="dropzone absolute inset-0" data-target="practice_hero"><span>Sleep foto of klik</span></div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">Deze afbeelding wordt bovenaan alle 'Praktijkinfo' pagina's getoond.</p>
                                </div>
                                <div class="card-footer"><button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Opslaan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="links" class="admin-tab-panel">
                <h2 class="admin-page-title">Nuttige Links & Telefoonnummers</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    <!-- Nuttige Links -->
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Nuttige links</h3></div>
                        <?php $hero = $linksData['hero'] ?? []; ?>
                         <form action="save.php" method="POST">
                            <div class="card-body space-y-4">
                                <input type="hidden" name="action" value="save_links">
                                <div>
                                    <label class="form-label">Hero Afbeelding & Titel</label>
                                    <div class="relative group w-full aspect-video bg-slate-100 rounded-md overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($hero['image'] ? $hero['image'] : 'https://placehold.co/800x450/e2e8f0/64748b?text=Hero+Afbeelding'); ?>" class="w-full h-full object-cover">
                                        <div class="dropzone absolute inset-0" data-target="links_hero"><span>Sleep foto of klik</span></div>
                                    </div>
                                    <input type="text" class="form-input mt-2" name="hero_title" value="<?php echo htmlspecialchars($hero['title'] ?? 'Nuttige links'); ?>">
                                </div>
                                <hr class="border-slate-200">
                                <div id="links-list" class="space-y-3">
                                    <?php $items = $linksData['items'] ?? []; foreach ($items as $it): $iid = $it['id'] ?? uniqid('link_', true); ?>
                                    <div class="border border-slate-200 rounded p-3" data-id="<?php echo htmlspecialchars($iid); ?>">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()"><?php echo $icons['delete']; ?></button>
                                        </div>
                                        <input type="hidden" name="link_id[]" value="<?php echo htmlspecialchars($iid); ?>">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <input type="text" class="form-input" name="link_label[]" placeholder="Naam" value="<?php echo htmlspecialchars($it['label'] ?? ''); ?>">
                                            <input type="text" class="form-input" name="link_url[]" placeholder="URL (https://...)" value="<?php echo htmlspecialchars($it['url'] ?? ''); ?>">
                                            <input type="text" class="form-input" name="link_tel[]" placeholder="Telefoon" value="<?php echo htmlspecialchars($it['tel'] ?? ''); ?>">
                                            <input type="text" class="form-input" name="link_desc[]" placeholder="Omschrijving" value="<?php echo htmlspecialchars($it['description'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div><button type="button" class="btn btn-secondary" onclick="window.addLinkItem()"><?php echo $icons['add']; ?> Link toevoegen</button></div>
                            </div>
                            <div class="card-footer"><button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Links Opslaan</button></div>
                        </form>
                    </div>
                     <!-- Nuttige Telefoonnummers -->
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Nuttige Telefoonnummers</h3></div>
                        <?php $phonesHero = $content['phones_hero'] ?? []; $settings = $content['settings'] ?? []; ?>
                         <form action="save.php" method="POST">
                            <div class="card-body space-y-4">
                                <input type="hidden" name="action" value="save_phones">
                                <div>
                                    <label class="form-label">Hero Afbeelding & Titel</label>
                                    <div class="relative group w-full aspect-video bg-slate-100 rounded-md overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($phonesHero['image'] ?? 'https://placehold.co/800x450/e2e8f0/64748b?text=Hero+Afbeelding'); ?>" class="w-full h-full object-cover">
                                        <div class="dropzone absolute inset-0" data-target="phones_hero"><span>Sleep foto of klik</span></div>
                                    </div>
                                     <input type="text" class="form-input mt-2" name="phones_hero_title" value="<?php echo htmlspecialchars($phonesHero['title'] ?? 'Nuttige telefoonnummers'); ?>">
                                </div>
                                <hr class="border-slate-200 !my-6">
                                <div>
                                    <h3 class="font-semibold text-lg mb-2">Lijst met nummers</h3>
                                    <?php $phones = $settings['footer_phones'] ?? []; ?>
                                    <div id="phones-list" class="space-y-3">
                                        <?php foreach ($phones as $ph): ?>
                                        <div class="border border-slate-200 rounded p-3" data-id="phone_<?php echo uniqid(); ?>">
                                             <div class="flex items-center justify-between mb-2">
                                                 <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                                 <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()"><?php echo $icons['delete']; ?></button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                                <input type="text" class="form-input" name="phone_label[]" placeholder="Naam" value="<?php echo htmlspecialchars($ph['label'] ?? ''); ?>">
                                                <input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon" value="<?php echo htmlspecialchars($ph['tel'] ?? ''); ?>">
                                                <input type="text" class="form-input" name="phone_desc[]" placeholder="Omschrijving" value="<?php echo htmlspecialchars($ph['desc'] ?? ''); ?>">
                                                <input type="text" class="form-input" name="phone_url[]" placeholder="Link (optioneel)" value="<?php echo htmlspecialchars($ph['url'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary mt-3" onclick="window.addPhoneItem()"><?php echo $icons['add']; ?> Nummer toevoegen</button>
                                </div>
                            </div>
                            <div class="card-footer"><button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Telefoonnummers Opslaan</button></div>
                        </form>
                    </div>
                </div>
            </div>

             <div id="settings" class="admin-tab-panel">
                 <h2 class="admin-page-title">Contact & Adres</h2>
                 <div class="card">
                    <form action="save.php" method="POST">
                        <div class="card-body space-y-4">
                            <input type="hidden" name="action" value="save_settings">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2"><h3 class="font-semibold text-lg">Algemene Info Praktijk</h3></div>
                                <div><label class="form-label">Algemene afspraak URL</label><input type="text" class="form-input" name="appointment_url" value="<?php echo htmlspecialchars($settings['appointment_url'] ?? ''); ?>"></div>
                                <div><label class="form-label">Telefoonnummer</label><input type="text" class="form-input" name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>"></div>
                                <div><label class="form-label">Adresregel 1</label><input type="text" class="form-input" name="address_line_1" value="<?php echo htmlspecialchars($settings['address_line_1'] ?? ''); ?>"></div>
                                <div><label class="form-label">Adresregel 2</label><input type="text" class="form-input" name="address_line_2" value="<?php echo htmlspecialchars($settings['address_line_2'] ?? ''); ?>"></div>
                                <div class="md:col-span-2"><label class="form-label">Google Maps embed code</label><textarea name="map_embed" class="form-textarea font-mono text-sm" rows="4"><?php echo htmlspecialchars($settings['map_embed'] ?? ''); ?></textarea></div>
                            </div>
                        </div>
                        <div class="card-footer"><button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Instellingen opslaan</button></div>
                    </form>
                </div>
            </div>

            <div id="pinned" class="admin-tab-panel">
                <h2 class="admin-page-title">Gepinde berichten</h2>
                <div class="card">
                     <form action="save.php" method="POST">
                        <div class="card-body">
                            <input type="hidden" name="action" value="save_pinned">
                            <p class="text-sm text-slate-500 mb-4">Gepinde berichten verschijnen als mededelingen op de geselecteerde pagina's.</p>
                            <div id="pinned-list" class="space-y-4">
                                <?php $pinned = $content['pinned'] ?? []; foreach ($pinned as $pin): $pid = $pin['id'] ?? uniqid('pin_', true); $scope = $pin['scope'] ?? []; if (!is_array($scope)) { $scope = ($scope==='all') ? ['all'] : []; } ?>
                                <div class="border border-slate-200 rounded-lg p-4" data-id="<?php echo htmlspecialchars($pid); ?>">
                                    <input type="hidden" name="pinned_id[]" value="<?php echo htmlspecialchars($pid); ?>">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren"><?php echo $icons['drag']; ?></span>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()"><?php echo $icons['delete']; ?> Verwijderen</button>
                                    </div>
                                    <label class="form-label">Titel</label>
                                    <input type="text" class="form-input" name="pinned_title[]" value="<?php echo htmlspecialchars($pin['title'] ?? ''); ?>">
                                    <label class="form-label mt-2">Tekst</label>
                                    <textarea class="form-textarea richtext" name="pinned_text[]" rows="4"><?php echo htmlspecialchars($pin['text'] ?? ''); ?></textarea>
                                    <div class="mt-4">
                                        <label class="form-label">Zichtbaar op:</label>
                                        <div class="flex flex-wrap gap-4 text-sm">
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_all[<?php echo htmlspecialchars($pid); ?>]" onchange="window.togglePinnedAll(this)" <?php echo in_array('all',$scope)?'checked':''; ?>><span>Alle pagina's</span></label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_home[<?php echo htmlspecialchars($pid); ?>]" <?php echo in_array('home',$scope)?'checked':''; ?>><span>Homepage</span></label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_team[<?php echo htmlspecialchars($pid); ?>]" <?php echo in_array('team',$scope)?'checked':''; ?>><span>Team</span></label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_practice[<?php echo htmlspecialchars($pid); ?>]" <?php echo in_array('practice',$scope)?'checked':''; ?>><span>Praktijkinfo</span></label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_links[<?php echo htmlspecialchars($pid); ?>]" <?php echo in_array('links',$scope)?'checked':''; ?>><span>Links</span></label>
                                            <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_phones[<?php echo htmlspecialchars($pid); ?>]" <?php echo in_array('phones',$scope)?'checked':''; ?>><span>Telefoon</span></label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary mt-4" onclick="window.addPinnedItem()"><?php echo $icons['add']; ?> Bericht toevoegen</button>
                        </div>
                         <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Gepinde Berichten Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="security" class="admin-tab-panel">
                <h2 class="admin-page-title">Beveiliging</h2>
                 <div class="card max-w-lg">
                    <div class="card-header"><h3 class="card-title">Wachtwoord Wijzigen</h3></div>
                    <form action="save.php" method="POST">
                        <div class="card-body">
                            <div class="space-y-4">
                                <input type="hidden" name="action" value="change_password">
                                <div><label class="form-label" for="old_password">Huidig Wachtwoord</label><input class="form-input" type="password" name="old_password" id="old_password" required></div>
                                <div><label class="form-label" for="new_password">Nieuw Wachtwoord</label><input class="form-input" type="password" name="new_password" id="new_password" required></div>
                                <div><label class="form-label" for="confirm_password">Bevestig Nieuw Wachtwoord</label><input class="form-input" type="password" name="confirm_password" id="confirm_password" required></div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><?php echo $icons['save']; ?> Wachtwoord Wijzigen</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Global UI Elements -->
    <div id="toast-container"></div>
    <div id="confirm-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <h3 class="text-lg font-bold mb-2">Bevestigen</h3>
            <p id="confirm-text" class="text-slate-600 mb-6">Weet je het zeker?</p>
            <div class="flex justify-end gap-3">
                <button id="confirm-no" class="btn btn-secondary">Annuleren</button>
                <button id="confirm-yes" class="btn btn-danger">Ja, doorgaan</button>
            </div>
        </div>
    </div>
    <div id="upload-progress-container" class="hidden">
        <div class="upload-progress-header">
            <span id="upload-progress-summary">Uploads</span>
            <button type="button" id="upload-clear-btn" class="text-xs text-slate-500 hover:underline">Sluiten</button>
        </div>
        <div id="upload-progress-list"></div>
    </div>
    
    <!-- JS Templates -->
    <template id="toast-template">
        <div class="toast-popup">
            <div class="toast-icon"></div>
            <p class="toast-message"></p>
        </div>
    </template>
    
    <script>
    // Pass PHP-generated data to JS
    window.APP_ICONS = <?php echo json_encode($icons); ?>;
    </script>
    <?php $adminJsVersion = @filemtime('admin.js'); ?>
    <script src="admin.js?v=<?php echo $adminJsVersion; ?>"></script>
    
    <?php if ($save_status === 'success'): ?>
    <script>window.showToast('Wijzigingen succesvol opgeslagen.', 'success');</script>
    <?php elseif ($save_status === 'error'): ?>
    <script>window.showToast('Fout bij opslaan. Controleer serverpermissies.', 'error');</script>
    <?php endif; ?>
    <?php if ($pass_status === 'success'): ?><script>window.showToast('Wachtwoord succesvol gewijzigd.', 'success');</script>
    <?php elseif ($pass_status === 'error_wrong'): ?><script>window.showToast('Huidige wachtwoord is onjuist.', 'error');</script>
    <?php elseif ($pass_status === 'error_mismatch'): ?><script>window.showToast('Nieuwe wachtwoorden komen niet overeen.', 'error');</script>
    <?php endif; ?>
</body>
</html>

