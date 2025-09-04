<?php
// Block direct access to /admin.php; use the dedicated entry file instead
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'admin.php') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}
/**
 * Admin panel
 *
 * Provides a tabbed interface to manage homepage content, portfolio themes
 * and client galleries. The UI uses URL hashes (e.g. #tab-portfolio&theme=x)
 * so context is preserved across redirects and light reloads.
 */
session_start();
require_once 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

function loadContent($file) {
    if (file_exists($file)) { return json_decode(file_get_contents($file), true); }
    return [];
}

$content = loadContent(CONTENT_FILE);
$portfolioData = loadContent(PORTFOLIO_FILE);
// Normalize any absolute file paths in portfolio data for display
if (!empty($portfolioData['themes'])) {
    foreach ($portfolioData['themes'] as $tn => &$td) {
        if (isset($td['images']) && is_array($td['images'])) {
            foreach ($td['images'] as &$img) {
                foreach (['path','webp'] as $k) {
                    if (!empty($img[$k])) { $img[$k] = toPublicPath($img[$k]); }
                }
            }
            unset($img);
        }
    }
    unset($td);
}
$themes = $portfolioData['themes'] ?? [];

// Load new data sources for Groepspraktijk Elewijt
$teamFilePath = defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/data/team/team.json');
$practiceFilePath = defined('PRACTICE_FILE') ? PRACTICE_FILE : (defined('DATA_DIR') ? DATA_DIR . '/practice/practice.json' : __DIR__ . '/data/practice/practice.json');
$linksFilePath = defined('LINKS_FILE') ? LINKS_FILE : (defined('DATA_DIR') ? DATA_DIR . '/links/links.json' : __DIR__ . '/data/links/links.json');
$teamData = file_exists($teamFilePath) ? (json_decode(file_get_contents($teamFilePath), true) ?: []) : [];
$practiceData = file_exists($practiceFilePath) ? (json_decode(file_get_contents($practiceFilePath), true) ?: []) : [];
$linksData = file_exists($linksFilePath) ? (json_decode(file_get_contents($linksFilePath), true) ?: []) : [];
// Normalize team member IDs (ensure every member has a stable id)
if (!empty($teamData['members']) && is_array($teamData['members'])) {
    $changed = false;
    foreach ($teamData['members'] as &$m) {
        if (empty($m['id'])) { $m['id'] = uniqid('tm_', true); $changed = true; }
    }
    unset($m);
    if ($changed) { saveJsonFile($teamFilePath, $teamData); }
}
$pass_status = $_GET['password_change'] ?? '';
$mailbox_status = $_GET['mailbox_update'] ?? '';
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
<body class="bg-slate-100">

    <div class="admin-container">
        <header class="admin-header">
            <h1 class="text-2xl font-bold text-slate-800">Admin</h1>
            <div class="flex items-center gap-2">
                <a href="index.php" class="btn btn-secondary">
                    <span>Homepage</span>
                </a>
            <a href="logout.php" class="btn btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2A.75.75 0 0010.75 3.5h-5.5A.75.75 0 004.5 4.25v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.75a.75.75 0 000 1.5h9.5a.75.75 0 00.75-.75z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M15.53 6.47a.75.75 0 00-1.06 0l-3 3a.75.75 0 000 1.06l3 3a.75.75 0 101.06-1.06L13.06 10l2.47-2.47a.75.75 0 000-1.06z" clip-rule="evenodd" /></svg>
                <span>Uitloggen</span>
            </a>
            </div>
        </header>

        <nav class="admin-tabs">
            <button class="admin-tab-button active" data-tab="tab-homepage"><span>Homepage</span></button>
            <button class="admin-tab-button" data-tab="tab-team"><span>Team</span></button>
            <button class="admin-tab-button" data-tab="tab-practice"><span>Praktijkinfo</span></button>
            <button class="admin-tab-button" data-tab="tab-links"><span>Nuttige Links</span></button>
            <button class="admin-tab-button" data-tab="tab-pinned"><span>Gepinde berichten</span></button>
            <button class="admin-tab-button" data-tab="tab-settings"><span>Algemene Instellingen</span></button>
            <button class="admin-tab-button" data-tab="tab-security"><span>Beveiliging</span></button>
        </nav>

        <main class="admin-content">
            <div id="tab-homepage" class="admin-tab-panel active">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Homepage</h2></div>
                    <form action="save.php" method="POST" enctype="multipart/form-data">
                        <div class="card-body space-y-6">
                            <input type="hidden" name="action" value="update_content">
                            <div>
                                <label class="form-label" for="hero_title">Hero Titel (tekst op homepagina foto)</label>
                                <input class="form-input" type="text" name="hero_title" id="hero_title" value="<?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label" for="hero_image_input">Hero Foto</label>
                                <div id="hero_image_container" class="relative group w-full aspect-[16/6] bg-slate-200 rounded-md cursor-pointer overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($content['hero']['image'] ?? 'https://placehold.co/1200x400'); ?>" class="w-full h-full object-cover" alt="Huidige hero foto">
                                    <div class="dropzone absolute inset-0" data-target="hero"><span>Sleep een nieuwe foto hier of klik</span></div>
                                </div>
                                <input class="hidden" type="file" name="hero_image" id="hero_image_input" accept="image/*">
                            </div>
                            <div>
                                <label class="form-label" for="hero_body">Hero Tekst (onder titel)</label>
                                <textarea class="form-textarea richtext" name="hero_body" id="hero_body" rows="4"><?php echo htmlspecialchars($content['hero']['body'] ?? ''); ?></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label">Welkom Titel</label>
                                    <input type="text" name="welcome_title" class="form-input" value="<?php echo htmlspecialchars($content['welcome']['title'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label" for="meta_title">Meta Titel (SEO)</label>
                                    <input class="form-input" type="text" name="meta_title" id="meta_title" value="<?php echo htmlspecialchars($content['meta_title'] ?? ''); ?>">
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Welkom Tekst</label>
                                <textarea name="welcome_text" class="form-textarea richtext" rows="5"><?php echo htmlspecialchars($content['welcome']['text'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="form-label">Welkom Kaarten</label>
                                <?php $wcards = isset($content['welcome']['cards']) && is_array($content['welcome']['cards']) ? $content['welcome']['cards'] : []; ?>
                                <div id="welcome-cards" class="space-y-3">
                                    <?php foreach ($wcards as $c): ?>
                                    <div class="border border-slate-200 rounded-md p-3">
                                        <label class="form-label">Inhoud</label>
                                        <textarea name="welcome_card_html[]" class="form-textarea richtext" rows="4"><?php echo htmlspecialchars($c['html'] ?? ''); ?></textarea>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" onclick="window.addWelcomeCard()">Kaart toevoegen</button>
                            </div>
                            <div>
                                <label class="form-label" for="meta_description">Meta Omschrijving (SEO)</label>
                                <input class="form-input" name="meta_description" id="meta_description" value="<?php echo htmlspecialchars($content['meta_description'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="card-footer homepage-footer">
                            <button type="submit" class="btn btn-primary"><span>Homepage Opslaan</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-team" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Team</h2>
                        <form action="save.php" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="action" value="add_team_member">
                            <input type="text" name="name" class="form-input" placeholder="Naam" required>
                            <input type="text" name="role" class="form-input" placeholder="Functie" required>
                            <input type="text" name="appointment_url" class="form-input" placeholder="Afspraak URL (optioneel)">
                            <button type="submit" class="btn btn-primary">Toevoegen</button>
                        </form>
                    </div>
                    <div class="card-body space-y-6">
                        <?php $members = isset($teamData['members']) && is_array($teamData['members']) ? $teamData['members'] : []; ?>
                        <?php if (empty($members)): ?>
                        <p class="text-slate-500">Nog geen teamleden toegevoegd.</p>
                        <?php else: foreach ($members as $m): $mid = $m['id'] ?? uniqid('tm_'); ?>
                        <div class="border border-slate-200 rounded-lg p-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                                <div>
                                    <div class="relative group w-full aspect-[4/3] bg-slate-100 rounded-md overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($m['image'] ?? ''); ?>" alt="" class="w-full h-full object-cover">
                                        <div class="dropzone absolute inset-0" data-target="team" data-member-id="<?php echo htmlspecialchars($mid); ?>"><span>Sleep foto of klik</span></div>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <form action="save.php" method="POST" class="space-y-2">
                                        <input type="hidden" name="action" value="update_team_member">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                        <label class="form-label">Naam</label>
                                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>">
                                        <label class="form-label">Functie</label>
                                        <input type="text" name="role" class="form-input" value="<?php echo htmlspecialchars($m['role'] ?? ''); ?>">
                                        <label class="form-label">Afspraak URL</label>
                                        <input type="text" name="appointment_url" class="form-input" value="<?php echo htmlspecialchars($m['appointment_url'] ?? ''); ?>">
                                        <div class="flex gap-2">
                                            <button type="submit" class="btn btn-secondary">Opslaan</button>
                                            <button type="button" class="btn btn-danger" onclick="(function(f){ if(!confirm('Verwijder dit teamlid?')) return; f.submit(); })(this.nextElementSibling)">Verwijder</button>
                                            <form action="save.php" method="POST" class="hidden">
                                                <input type="hidden" name="action" value="delete_team_member">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                            </form>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-practice" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Praktijkinfo</h2>
                        <form action="save.php" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="action" value="save_practice_page">
                            <input type="text" class="form-input" name="title" placeholder="Titel" required>
                            <button type="submit" class="btn btn-primary">Pagina toevoegen</button>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php $pages = isset($practiceData['pages']) && is_array($practiceData['pages']) ? $practiceData['pages'] : []; ?>
                        <?php if (empty($pages)): ?>
                        <p class="text-slate-500">Nog geen praktijkinfo-pagina's.</p>
                        <?php else: ?>
                        <div class="admin-table-container">
                            <table class="admin-table" id="practice-table">
                                <thead><tr><th class="w-8"></th><th>Titel</th><th>Kaarten</th><th>Acties</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pages as $slug => $pd): $cards = isset($pd['cards']) && is_array($pd['cards']) ? $pd['cards'] : []; ?>
                                    <tr data-slug="<?php echo htmlspecialchars($slug); ?>">
                                        <td><span class="drag-handle cursor-move" title="Sleep om te verplaatsen">&#9776;</span></td>
                                        <td><?php echo htmlspecialchars($pd['title'] ?? $slug); ?></td>
                                        <td><?php echo count($cards); ?></td>
                                        <td>
                                            <form action="save.php" method="POST" onsubmit="return confirm('Pagina verwijderen?');">
                                                <input type="hidden" name="action" value="delete_practice_page">
                                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                                <button type="submit" class="btn btn-danger">Verwijder</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php foreach ($pages as $slug => $pd): $cards = isset($pd['cards']) && is_array($pd['cards']) ? $pd['cards'] : []; ?>
                        <div class="card practice-card-editor hidden mt-6" data-slug="<?php echo htmlspecialchars($slug); ?>">
                            <div class="card-header"><h3 class="card-title text-lg">Bewerk '<?php echo htmlspecialchars($pd['title'] ?? $slug); ?>'</h3></div>
                            <div class="card-body">
                                <form action="save.php" method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="save_practice_page">
                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                    <label class="form-label">Titel</label>
                                    <input type="text" class="form-input" name="title" value="<?php echo htmlspecialchars($pd['title'] ?? ''); ?>">
                                        <div class="space-y-3" id="cards-<?php echo htmlspecialchars($slug); ?>">
                                            <?php foreach ($cards as $c): ?>
                                            <div class="border border-slate-200 rounded-md p-3">
                                                <div class="flex items-center justify-between">
                                                    <label class="form-label">Inhoud</label>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div.border').remove()">Verwijder</button>
                                                </div>
                                                <textarea name="card_html[]" class="form-textarea richtext" rows="5"><?php echo htmlspecialchars($c['html'] ?? ''); ?></textarea>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-secondary" onclick="window.addPracticeCard('cards-<?php echo htmlspecialchars($slug); ?>')">Kaart toevoegen</button>
                                        </div>
                                    <div>
                                        <button type="submit" class="btn btn-primary">Pagina opslaan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-links" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Nuttige links</h2></div>
                    <?php $hero = $linksData['hero'] ?? ['title' => '', 'image' => '', 'webp' => '']; $items = $linksData['items'] ?? []; ?>
                    <div class="card-body space-y-4">
                        <form action="save.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="save_links">
                            <div>
                                <label class="form-label">Hero Titel</label>
                                    <input type="text" class="form-input" name="hero_title" value="<?php echo htmlspecialchars($hero['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label">Hero Afbeelding</label>
                                <div class="relative group w-full aspect-[16/6] bg-slate-100 rounded-md overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($hero['image'] ?? ''); ?>" alt="" class="w-full h-full object-cover">
                                    <div class="dropzone absolute inset-0" data-target="links_hero"><span>Sleep foto of klik</span></div>
                                </div>
                            </div>
                            <div id="links-list" class="space-y-2">
                                <?php foreach ($items as $it): $iid = $it['id'] ?? uniqid('link_', true); ?>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2" data-id="<?php echo htmlspecialchars($iid); ?>">
                                    <input type="hidden" name="link_id[]" value="<?php echo htmlspecialchars($iid); ?>">
                                    <div class="flex items-center gap-2">
                                        <span class="drag-handle cursor-move" title="Sleep om te verplaatsen">&#9776;</span>
                                        <input type="text" class="form-input flex-1" name="link_label[]" placeholder="Omschrijving" value="<?php echo htmlspecialchars($it['label'] ?? ''); ?>">
                                    </div>
                                    <input type="text" class="form-input" name="link_url[]" placeholder="https://... of www..." value="<?php echo htmlspecialchars($it['url'] ?? ''); ?>">
                                    <div class="flex gap-2 items-center">
                                        <input type="text" class="form-input flex-1" name="link_tel[]" placeholder="Telefoon (optioneel)" value="<?php echo htmlspecialchars($it['tel'] ?? ''); ?>">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.grid').remove()">Verwijder</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-secondary" onclick="window.addLinkItem()">Link toevoegen</button>
                                <button type="submit" class="btn btn-primary">Opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="tab-settings" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Algemene instellingen</h2></div>
                    <?php $settings = $content['settings'] ?? []; ?>
                    <div class="card-body space-y-4">
                        <form action="save.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="save_settings">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label">Algemene afspraak URL</label>
                                    <input type="text" class="form-input" name="appointment_url" value="<?php echo htmlspecialchars($settings['appointment_url'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Telefoonnummer</label>
                                    <input type="text" class="form-input" name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Adresregel 1</label>
                                    <input type="text" class="form-input" name="address_line_1" value="<?php echo htmlspecialchars($settings['address_line_1'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Adresregel 2</label>
                                    <input type="text" class="form-input" name="address_line_2" value="<?php echo htmlspecialchars($settings['address_line_2'] ?? ''); ?>">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label">Kaart embed (HTML)</label>
                                    <textarea name="map_embed" class="form-textarea" rows="4"><?php echo htmlspecialchars($settings['map_embed'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-2">Nuttige telefoonnummers</h3>
                                <?php $phones = isset($settings['footer_phones']) && is_array($settings['footer_phones']) ? $settings['footer_phones'] : []; ?>
                                <div id="phones-list" class="space-y-2">
                                    <?php foreach ($phones as $ph): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        <input type="text" class="form-input" name="phone_label[]" placeholder="Omschrijving" value="<?php echo htmlspecialchars($ph['label'] ?? ''); ?>">
                                        <input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon" value="<?php echo htmlspecialchars($ph['tel'] ?? ''); ?>">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" onclick="window.addPhoneItem()">Nummer toevoegen</button>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">Instellingen opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="tab-pinned" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Gepinde berichten</h2></div>
                    <?php $pinned = isset($content['pinned']) && is_array($content['pinned']) ? $content['pinned'] : []; ?>
                    <div class="card-body">
                        <form action="save.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="save_pinned">
                            <div id="pinned-list" class="space-y-4">
                                <?php foreach ($pinned as $pin): $pid = $pin['id'] ?? uniqid('pin_', true); ?>
                                <div class="border border-slate-200 rounded-lg p-4" data-id="<?php echo htmlspecialchars($pid); ?>">
                                    <input type="hidden" name="pinned_id[]" value="<?php echo htmlspecialchars($pid); ?>">
                                    <label class="form-label">Titel</label>
                                    <input type="text" class="form-input" name="pinned_title[]" value="<?php echo htmlspecialchars($pin['title'] ?? ''); ?>">
                                    <label class="form-label mt-2">Tekst</label>
                                    <textarea class="form-textarea richtext" name="pinned_text[]" rows="4"><?php echo htmlspecialchars($pin['text'] ?? ''); ?></textarea>
                                    <?php $scope = $pin['scope'] ?? []; if (!is_array($scope)) { $scope = ($scope==='all') ? ['all'] : []; } ?>
                                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-2 items-center">
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_home[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('home',$scope)?'checked':''; ?>> Home</label>
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_team[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('team',$scope)?'checked':''; ?>> Team</label>
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_practice[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('practice',$scope)?'checked':''; ?>> Praktijkinfo</label>
                                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_links[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('links',$scope)?'checked':''; ?>> Nuttige links</label>
                                        <div class="flex items-center gap-2">
                                            <input type="hidden" name="pinned_scope_all[]" value="<?php echo in_array('all',$scope)?htmlspecialchars($pid):''; ?>">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="(function(btn){var box=btn.closest('.border');var id=box.querySelector('input[name=\'pinned_id[]\']').value;var home=box.querySelector('input[name=\'pinned_scope_home[]\'][value=\''+id+'\']');var team=box.querySelector('input[name=\'pinned_scope_team[]\'][value=\''+id+'\']');var pr=box.querySelector('input[name=\'pinned_scope_practice[]\'][value=\''+id+'\']');var li=box.querySelector('input[name=\'pinned_scope_links[]\'][value=\''+id+'\']');var all=box.querySelector('input[name=\'pinned_scope_all[]\']');var on=all.value && all.value.length>0; if(!on){ home.checked=team.checked=pr.checked=li.checked=true; all.value=id; btn.textContent='Selectie wissen'; } else { home.checked=team.checked=pr.checked=li.checked=false; all.value=''; btn.textContent="Alle pagina's"; }})(this)"><?php echo in_array('all',$scope)?'Selectie wissen':"Alle pagina's"; ?></button>
                                            <span class="drag-handle cursor-move" title="Sleep om te verplaatsen">&#9776;</span>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">Verwijder</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="btn btn-secondary" onclick="window.addPinnedItem()">Bericht toevoegen</button>
                                <button type="submit" class="btn btn-primary">Opslaan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
            // Initialize CKEditor on any .richtext textarea and keep a reference
            window.initRichtext = function(el){
              if (!window.ClassicEditor || !el) return;
              if (el._ck_inited) return;
              try {
                ClassicEditor.create(el, { toolbar: ['heading','bold','italic','link','bulletedList','numberedList','undo','redo'] })
                  .then(editor => { el._ck = editor; el._ck_inited = true; })
                  .catch(() => {});
              } catch(e) {}
            };
            document.addEventListener('DOMContentLoaded', function(){
              document.querySelectorAll('textarea.richtext').forEach(window.initRichtext);
            });
            // Simple helpers to add dynamic fields without extra dependencies
            window.addWelcomeCard = function(){
              var c = document.getElementById('welcome-cards'); if(!c) return;
              var d = document.createElement('div');
              d.className = 'border border-slate-200 rounded-md p-3';
              d.innerHTML = '<label class="form-label">Inhoud</label><textarea name="welcome_card_html[]" class="form-textarea richtext" rows="4"></textarea>';
              c.appendChild(d);
              var tx = d.querySelector('textarea.richtext'); if (tx) window.initRichtext(tx);
            };
            // Toggle all scopes for a pinned item
            window.togglePinnedAll = function(btn){
              var box = btn.closest('.border'); if(!box) return;
              var id = box.querySelector('input[name="pinned_id[]"]').value;
              var home = box.querySelector('input[name="pinned_scope_home[]"][value="'+id+'"]');
              var team = box.querySelector('input[name="pinned_scope_team[]"][value="'+id+'"]');
              var pr   = box.querySelector('input[name="pinned_scope_practice[]"][value="'+id+'"]');
              var li   = box.querySelector('input[name="pinned_scope_links[]"][value="'+id+'"]');
              var all  = box.querySelector('input[name="pinned_scope_all[]"]');
              var turnOn = !(home?.checked && team?.checked && pr?.checked && li?.checked && all?.value);
              if (home) home.checked = turnOn;
              if (team) team.checked = turnOn;
              if (pr)   pr.checked   = turnOn;
              if (li)   li.checked   = turnOn;
              if (all)  all.value    = turnOn ? id : '';
            };
            window.addPracticeCard = function(containerId){
              var c = document.getElementById(containerId); if(!c) return;
              var wrap = document.createElement('div');
              wrap.className = 'border border-slate-200 rounded-md p-3';
              wrap.innerHTML = '<label class="form-label">Inhoud</label><textarea name="card_html[]" class="form-textarea richtext" rows="5"></textarea>';
              c.appendChild(wrap);
              var tx = wrap.querySelector('textarea.richtext'); if (tx) window.initRichtext(tx);
            };
            window.addLinkItem = function(){
              var list = document.getElementById('links-list'); if(!list) return;
              var row = document.createElement('div');
              row.className = 'grid grid-cols-1 md:grid-cols-3 gap-2';
              row.innerHTML = '<input type="text" class="form-input" name="link_label[]" placeholder="Omschrijving">'+
                              '<input type="url" class="form-input" name="link_url[]" placeholder="https://...">'+
                              '<input type="text" class="form-input" name="link_tel[]" placeholder="Telefoon (optioneel)">';
              list.appendChild(row);
            };
            window.addPhoneItem = function(){
              var list = document.getElementById('phones-list'); if(!list) return;
              var row = document.createElement('div');
              row.className = 'grid grid-cols-1 md:grid-cols-2 gap-2';
              row.innerHTML = '<input type="text" class="form-input" name="phone_label[]" placeholder="Omschrijving">'+
                              '<input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon">';
              list.appendChild(row);
            };
            window.addPinnedItem = function(){
              var list = document.getElementById('pinned-list'); if(!list) return;
              var id = 'pin_' + Math.random().toString(36).slice(2);
              var box = document.createElement('div');
              box.className = 'border border-slate-200 rounded-lg p-4';
              box.innerHTML = '<input type="hidden" name="pinned_id[]" value="'+id+'">'+
                              '<label class="form-label">Titel</label><input type="text" class="form-input" name="pinned_title[]" value="">'+
                              '<label class="form-label mt-2">Tekst</label><textarea class="form-textarea richtext" name="pinned_text[]" rows="4"></textarea>'+
                              '<div class="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-2">'+
                              '<label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_home[]" value="'+id+'"> Home</label>'+
                              '<label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_team[]" value="'+id+'"> Team</label>'+
                              '<label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_practice[]" value="'+id+'"> Praktijkinfo</label>'+
                              '<label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_links[]" value="'+id+'"> Nuttige links</label>'+
                              '<label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_all[]" value="'+id+'"> Alle</label>'+
                              '</div>';
              list.appendChild(box);
              var tx = box.querySelector('textarea.richtext'); if (tx) window.initRichtext(tx);
            };
            </script>

            <div id="tab-portfolio" class="admin-tab-panel">
                <?php $portfolioHidden = isset($content['pages']['portfolio']['visible']) ? !$content['pages']['portfolio']['visible'] : false; ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="save.php" method="POST" class="flex items-center gap-4">
                            <input type="hidden" name="action" value="update_content">
                            <label class="inline-flex items-center gap-3">
                                <input type="checkbox" name="hide_portfolio" value="1" <?php echo $portfolioHidden ? 'checked' : ''; ?>>
                                <span>Verberg Portfolio pagina en navigatie</span>
                            </label>
                            <button type="submit" class="btn btn-secondary">Opslaan</button>
                        </form>
                    </div>
                </div>
                 <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Portfolio Beheren</h2>
                        <form action="save.php" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="action" value="add_theme">
                            <input class="form-input" type="text" name="theme_name" placeholder="Nieuwe portfolio naam..." required>
                            <button type="submit" class="btn btn-primary">Toevoegen</button>
                        </form>
                    </div>
                    <div class="card-body">
                         <?php if (empty($themes)): ?>
                            <p class="text-slate-500 text-center py-8">Nog geen portfolio's. Voeg er een toe om te beginnen.</p>
                        <?php else: ?>
                            <div class="admin-table-container">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th class="w-8"></th>
                                            <th>Naam</th>
                                            <th class="text-center">Foto's</th>
                                        </tr>
                                    </thead>
                                    <tbody id="portfolio-sortable-list">
                                    <?php foreach ($themes as $themeName => $themeData): ?>
                                        <tr class="clickable-row" data-theme="<?php echo htmlspecialchars($themeName); ?>">
                                            <td class="text-center drag-handle"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-slate-400"><path fill-rule="evenodd" d="M10 5a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 0110 5zm0 3.5a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5a.75.75 0 01.75-.75zm0 3.5a.75.75 0 01.75.75v.5a.75.75 0 01-1.5 0v-.5A.75.75 0 0110 12z" clip-rule="evenodd" /></svg></td>
                                            <td><?php echo htmlspecialchars(ucfirst($themeName)); ?></td>
                                            <td class="text-center"><?php echo count($themeData['images'] ?? []); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Algemene begeleidende tekst voor Portfolio (getoond wanneer geen filter is gekozen) -->
                <?php $hasGlobalIntro = !empty($portfolioData['intro']['title']) || !empty($portfolioData['intro']['text']); ?>
                <details class="card mt-6" <?php echo $hasGlobalIntro ? 'open' : ''; ?>>
                    <summary class="card-header cursor-pointer"><h3 class="card-title text-lg">Algemene begeleidende tekst</h3></summary>
                    <div class="card-body">
                        <form action="save.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_portfolio_intro">
                            <div>
                                <label class="form-label" for="portfolio_intro_title">Titel (optioneel)</label>
                                <input type="text" id="portfolio_intro_title" name="intro_title" class="form-input" value="<?php echo htmlspecialchars($portfolioData['intro']['title'] ?? ''); ?>" placeholder="Bijv. Welkom in mijn portfolio">
                            </div>
                            <div>
                                <label class="form-label" for="portfolio_intro_text">Begeleidende tekst (optioneel)</label>
                                <textarea id="portfolio_intro_text" name="intro_text" rows="4" class="form-textarea" placeholder="Algemene uitleg die boven de foto's verschijnt als geen specifiek portfolio gekozen is."><?php echo htmlspecialchars($portfolioData['intro']['text'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-secondary">Opslaan</button>
                            </div>
                        </form>
                    </div>
                </details>

                <div id="theme-cards-container" class="mt-6 space-y-6">
                    <?php foreach ($themes as $themeName => $themeData): ?>
                        <div class="card theme-card hidden" data-theme="<?php echo htmlspecialchars($themeName); ?>">
                            <div class="card-header">
                                <h3 class="card-title text-lg">Beheer '<?php echo htmlspecialchars(ucfirst($themeName)); ?>'</h3>
                                <form action="save.php" method="POST" class="delete-form">
                                    <input type="hidden" name="action" value="delete_theme">
                                    <input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($themeName); ?>">
                                    <button type="submit" class="btn btn-danger">Portfolio Verwijderen</button>
                                </form>
                            </div>
                             <div class="card-body border-b border-slate-200">
                                <form action="save.php" method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="rename_theme">
                                    <input type="hidden" name="old_theme_name" value="<?php echo htmlspecialchars($themeName); ?>">
                                    <input type="text" name="new_theme_name" value="<?php echo htmlspecialchars(ucfirst($themeName)); ?>" class="form-input flex-grow" />
                                    <button type="submit" class="btn btn-secondary">Hernoem</button>
                                </form>
                            </div>
                        <div class="card-body">
                            <div class="mb-6">
                                <form action="save.php" method="POST" class="space-y-2">
                                    <input type="hidden" name="action" value="update_theme_intro">
                                    <input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($themeName); ?>">
                                    <label class="form-label" for="intro_title_<?php echo htmlspecialchars($themeName); ?>">Titel (optioneel)</label>
                                    <input type="text" class="form-input" id="intro_title_<?php echo htmlspecialchars($themeName); ?>" name="intro_title" placeholder="Titel voor dit portfolio" value="<?php echo htmlspecialchars($themeData['intro_title'] ?? ''); ?>">
                                    <label class="form-label" for="intro_text_<?php echo htmlspecialchars($themeName); ?>">Begeleidende tekst (optioneel)</label>
                                    <textarea class="form-textarea" rows="4" id="intro_text_<?php echo htmlspecialchars($themeName); ?>" name="intro_text" placeholder="Korte beschrijving of begeleidende tekst voor dit portfolio..."><?php echo htmlspecialchars($themeData['intro_text'] ?? ''); ?></textarea>
                                    <div>
                                        <button type="submit" class="btn btn-secondary">Tekst opslaan</button>
                                    </div>
                                </form>
                            </div>
                            <label class="form-label">Foto's toevoegen</label>
                            <div class="dropzone" data-target="portfolio" data-theme="<?php echo htmlspecialchars($themeName); ?>"><span>Sleep hier foto's of klik</span></div>
                            <div class="photo-list" data-theme="<?php echo htmlspecialchars($themeName); ?>">
                                    <?php foreach (($themeData['images'] ?? []) as $index => $image): ?>
                                        <div class="photo-list-item" data-id="<?php echo $index; ?>" data-title="<?php echo htmlspecialchars($image['title'] ?? ''); ?>" data-description="<?php echo htmlspecialchars($image['description'] ?? ''); ?>" data-alt="<?php echo htmlspecialchars($image['alt'] ?? ''); ?>" data-featured="<?php echo isset($image['featured']) && $image['featured'] ? 'true' : 'false'; ?>">
                                            <?php if (isset($image['featured']) && $image['featured']): ?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="featured-star"><path fill-rule="evenodd" d="M10.868 2.884c.321-.662 1.215-.662 1.536 0l1.681 3.462 3.818.554c.729.106 1.022.992.494 1.506l-2.764 2.693.654 3.802c.124.723-.635 1.27-1.282.944l-3.415-1.795-3.415 1.795c-.647.326-1.406-.221-1.282-.944l.654-3.802-2.764-2.693c-.528-.514-.235-1.399.494-1.506l3.818-.554 1.681-3.462z" clip-rule="evenodd" /></svg><?php endif; ?>
                                            <img src="<?php echo htmlspecialchars($image['path']); ?>" class="admin-thumb" alt="">
                                            <div class="photo-list-overlay">
                                                <button class="btn-icon" onclick="editPhoto(this, '<?php echo htmlspecialchars($themeName); ?>', <?php echo $index; ?>)" title="Bewerk"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" /></svg></button>
                                                <form action="save.php" method="POST" class="delete-photo-form"><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="theme_name" value="<?php echo htmlspecialchars($themeName); ?>"><input type="hidden" name="photo_index" value="<?php echo $index; ?>"><button type="submit" class="btn-icon btn-icon-danger" title="Verwijder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.58.22-2.365.468a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.573l.842-10.518.149.022a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193v-.443A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg></button></form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-pricing" class="admin-tab-panel">
                <?php $pricingData = []; if (file_exists(PRICING_FILE)) { $pricingData = json_decode(file_get_contents(PRICING_FILE), true) ?: []; } $pricingItems = $pricingData['items'] ?? []; $pricingHidden = isset($content['pages']['pricing']['visible']) ? !$content['pages']['pricing']['visible'] : false; ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Tarieven Beheren</h2>
                    </div>
                    <div class="card-body space-y-6">
                        <div>
                            <form action="save.php" method="POST" class="flex items-center gap-4">
                                <input type="hidden" name="action" value="update_content">
                                <label class="inline-flex items-center gap-3">
                                    <input type="checkbox" name="hide_pricing" value="1" <?php echo $pricingHidden ? 'checked' : ''; ?>>
                                    <span>Verberg Tarieven pagina en navigatie</span>
                                </label>
                                <button type="submit" class="btn btn-secondary">Opslaan</button>
                            </form>
                        </div>
                        <form action="save.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" name="action" value="add_pricing_item">
                            <div class="md:col-span-2">
                                <label class="form-label" for="pricing_title">Titel</label>
                                <input class="form-input" type="text" id="pricing_title" name="title" placeholder="Bijv. Fotoshoot Basic" required>
                            </div>
                            <div>
                                <label class="form-label" for="pricing_price">Prijs</label>
                                <input class="form-input" type="text" id="pricing_price" name="price" placeholder="€ 199">
                            </div>
                            <div>
                                <label class="form-label" for="pricing_image">Foto (optioneel)</label>
                                <input class="form-input" type="file" id="pricing_image" name="image" accept="image/*">
                            </div>
                            <div class="md:col-span-4">
                                <label class="form-label" for="pricing_desc">Beschrijving</label>
                                <textarea class="form-textarea" id="pricing_desc" name="description" rows="3" placeholder="Korte omschrijving van het pakket..."></textarea>
                            </div>
                            <div class="md:col-span-4">
                                <button type="submit" class="btn btn-primary">Tarief toevoegen</button>
                            </div>
                        </form>

                        <div>
                            <label class="form-label">Snel foto toevoegen (maakt een nieuw tarief met enkel foto)</label>
                            <div class="dropzone" data-target="pricing"><span>Sleep hier foto('s) of klik</span></div>
                        </div>

                        <?php if (empty($pricingItems)): ?>
                            <p class="text-slate-500 text-center py-6">Nog geen tarieven toegevoegd.</p>
                        <?php else: ?>
                            <div class="admin-table-container">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th class="w-20">Foto</th>
                                            <th>Titel</th>
                                            <th>Prijs</th>
                                            <th>Omschrijving</th>
                                            <th class="w-40">Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pricingItems as $it): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($it['image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($it['image']); ?>" class="admin-thumb" alt="">
                                                <?php else: ?>
                                                    <span class="text-slate-500">Geen</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form action="save.php" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="update_pricing_item">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($it['id']); ?>">
                                                    <input class="form-input mb-2" type="text" name="title" value="<?php echo htmlspecialchars($it['title'] ?? ''); ?>">
                                                    <input class="form-input" type="file" name="image" accept="image/*">
                                            </td>
                                            <td>
                                                    <input class="form-input" type="text" name="price" value="<?php echo htmlspecialchars($it['price'] ?? ''); ?>">
                                            </td>
                                            <td>
                                                    <textarea class="form-textarea" name="description" rows="2"><?php echo htmlspecialchars($it['description'] ?? ''); ?></textarea>
                                            </td>
                                            <td class="align-top">
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="btn btn-secondary">Opslaan</button>
                                                    </div>
                                                </form>
                                                <form action="save.php" method="POST" class="mt-2">
                                                    <input type="hidden" name="action" value="delete_pricing_item">
                                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($it['id']); ?>">
                                                    <button type="submit" class="btn btn-danger">Verwijder</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-galleries" class="admin-tab-panel">
                <?php $galleries = []; if (is_dir(GALLERIES_DIR)) { foreach (glob(GALLERIES_DIR . '/*/gallery.json') as $gfile) { if ($data = json_decode(file_get_contents($gfile), true)) $galleries[] = $data; } } ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Klantengalerijen</h2>
                        <form action="save.php" method="POST" class="flex items-center gap-2">
                            <input type="hidden" name="action" value="create_gallery">
                            <input type="text" class="form-input" name="gallery_title" placeholder="Titel nieuwe galerij" required>
                            <input type="text" class="form-input" name="gallery_password" placeholder="Wachtwoord" required>
                            <button type="submit" class="btn btn-primary">Aanmaken</button>
                        </form>
                    </div>
                    <div class="card-body">
                         <?php if (empty($galleries)): ?><p class="text-slate-500 text-center py-8">Nog geen klantengalerijen aangemaakt.</p>
                        <?php else: ?>
                            <div class="admin-table-container">
                                <table class="admin-table">
                                    <thead><tr><th>Titel</th><th class="text-center">Status</th><th class="text-center">Selectie</th><th class="text-center">Foto's</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($galleries as $gallery): 
                                        $photos = $gallery['photos'] ?? [];
                                        $selectedCount = count(array_filter($photos, fn($p) => !empty($p['favorite'])));
                                        $isActive = $gallery['active'] ?? true;
                                        $isConfirmed = !empty($gallery['confirmed']);
                                    ?>
                                        <tr class="clickable-row" data-slug="<?php echo htmlspecialchars($gallery['slug']); ?>">
                                            <td><?php echo htmlspecialchars($gallery['title']); ?></td>
                                            <td class="text-center">
                                                <?php if ($isConfirmed): ?>
                                                    <span class="status-badge status-confirmed">Bevestigd</span>
                                                <?php else: ?>
                                                    <span class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>"><?php echo $isActive ? 'Actief' : 'Inactief'; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $selectedCount . ' / ' . (($gallery['max_select'] ?? 0) > 0 ? $gallery['max_select'] : '&#8734;'); ?></td>
                                            <td class="text-center"><?php echo count($photos); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="gallery-cards-container" class="mt-6 space-y-6">
                <?php foreach ($galleries as $gallery): ?>
                    <div class="card gallery-card hidden" data-slug="<?php echo htmlspecialchars($gallery['slug']); ?>">
                        <div class="card-header">
                            <h3 class="card-title text-lg">Beheer '<?php echo htmlspecialchars($gallery['title']); ?>'</h3>
                            <div class="flex items-center gap-2">
                                <a href="proof.php?gallery=<?php echo urlencode($gallery['slug']); ?>" target="_blank" class="btn btn-secondary text-sm">Open Link</a>
                                <button type="button" class="btn btn-secondary text-sm copy-link-btn" data-link="/proof.php?gallery=<?php echo urlencode($gallery['slug']); ?>">Kopieer link</button>
                            </div>
                        </div>
                        <div class="card-body space-y-6 border-b border-slate-200">
                            <div>
                                <label for="gallery_title_<?php echo htmlspecialchars($gallery['slug']); ?>" class="form-label">Galerij Titel</label>
                                <form action="save.php" method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="rename_gallery">
                                    <input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>">
                                    <input type="text" id="gallery_title_<?php echo htmlspecialchars($gallery['slug']); ?>" name="new_title" value="<?php echo htmlspecialchars($gallery['title']); ?>" class="form-input flex-grow" required>
                                    <button type="submit" class="btn btn-secondary">Opslaan</button>
                                </form>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="max_select_<?php echo htmlspecialchars($gallery['slug']); ?>" class="form-label">Selectielimiet</label>
                                    <form action="save.php" method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="action" value="update_gallery_max">
                                        <input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>">
                                        <input type="number" id="max_select_<?php echo htmlspecialchars($gallery['slug']); ?>" name="max_select" min="0" value="<?php echo $gallery['max_select'] ?? 0; ?>" class="form-input w-28" placeholder="0 = onbeperkt">
                                        <button type="submit" class="btn btn-secondary flex-grow">Instellen</button>
                                    </form>
                                </div>
                                <div>
                                    <label for="new_password_<?php echo htmlspecialchars($gallery['slug']); ?>" class="form-label">Nieuw Wachtwoord</label>
                                    <form action="save.php" method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="action" value="reset_gallery_password">
                                        <input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>">
                                        <input type="text" id="new_password_<?php echo htmlspecialchars($gallery['slug']); ?>" name="new_password" class="form-input flex-grow" placeholder="Leeglaten om niet te wijzigen">
                                        <button type="submit" class="btn btn-secondary">Reset</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <label class="form-label">Foto's toevoegen (sleep om de volgorde te wijzigen)</label>
                            <div class="dropzone" data-target="gallery" data-slug="<?php echo htmlspecialchars($gallery['slug']); ?>"><span>Sleep hier foto's of klik</span></div>
                             <?php if (empty($gallery['photos'])): ?><p class="text-slate-500 text-center pt-8 pb-4">Deze galerij bevat nog geen foto's.</p>
                             <?php else: ?>
                                <div class="gallery-photo-list" data-gallery="<?php echo htmlspecialchars($gallery['slug']); ?>">
                                    <?php foreach ($gallery['photos'] as $pi => $p): ?>
                                        <div class="gallery-photo-list-item" data-id="<?php echo $pi; ?>">
                                            <img src="<?php echo htmlspecialchars($p['path']); ?>" class="admin-thumb">
                                            
                                            <div class="absolute top-1.5 right-1.5 z-10 flex flex-col gap-1.5">
                                                <?php if (!empty($p['favorite'])): ?><span title="Geselecteerd" class="h-6 w-6 rounded-full flex items-center justify-center bg-green-500 text-white shadow-md"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.052-.143z" clip-rule="evenodd" /></svg></span><?php endif; ?>
                                                <?php if (!empty($p['comment'])): ?><span title="Opmerking: <?php echo htmlspecialchars($p['comment']); ?>" class="h-6 w-6 rounded-full flex items-center justify-center bg-blue-500 text-white shadow-md"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M5 3.75A1.75 1.75 0 016.75 2h6.5A1.75 1.75 0 0115 3.75v7.5A1.75 1.75 0 0113.25 13h-6.5A1.75 1.75 0 015 11.25v-7.5zm1.75-.25a.25.25 0 00-.25.25v7.5c0 .138.112.25.25.25h6.5a.25.25 0 00.25-.25v-7.5a.25.25 0 00-.25-.25h-6.5z" clip-rule="evenodd" /><path d="M6 15.25a.75.75 0 01.75-.75h6.5a.75.75 0 010 1.5h-6.5a.75.75 0 01-.75-.75z" /></svg></span><?php endif; ?>
                                            </div>

                                            <div class="photo-list-overlay items-center justify-center">
                                                <form action="save.php" method="POST" class="delete-photo-form"><input type="hidden" name="action" value="delete_gallery_photo"><input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>"><input type="hidden" name="photo_index" value="<?php echo $pi; ?>"><button type="submit" class="btn-icon btn-icon-danger" title="Verwijder"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.58.22-2.365.468a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.573l.842-10.518.149.022a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193v-.443A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg></button></form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer justify-between">
                            <?php $selectedCountCard = count(array_filter(($gallery['photos'] ?? []), fn($p) => !empty($p['favorite']))); $confirmedCard = !empty($gallery['confirmed']); ?>
                             <form action="save.php" method="GET"><input type="hidden" name="action" value="download_gallery_selection"><input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>"><button type="submit" class="btn <?php echo $confirmedCard ? 'btn-primary' : 'btn-secondary'; ?> text-sm" <?php echo $selectedCountCard === 0 ? ' disabled' : ''; ?>>Download Selectie</button></form>
                            <div class="flex items-center gap-2">
                                <form action="save.php" method="POST"><input type="hidden" name="action" value="toggle_gallery_active"><input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>"><button type="submit" class="btn <?php echo ($gallery['active'] ?? true) ? 'btn-warning' : 'btn-success'; ?> text-sm"><?php echo ($gallery['active'] ?? true) ? 'Deactiveren' : 'Activeren'; ?></button></form>
                                <form action="save.php" method="POST" class="delete-form"><input type="hidden" name="action" value="delete_gallery"><input type="hidden" name="gallery_slug" value="<?php echo htmlspecialchars($gallery['slug']); ?>"><button type="submit" class="btn btn-danger text-sm">Verwijder Galerij</button></form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            
            <div id="tab-security" class="admin-tab-panel">
                 <div class="card">
                    <div class="card-header"><h2 class="card-title">Beveiliging</h2></div>
                    <div class="card-body">
                        <?php if ($pass_status === 'success'): ?><div class="alert alert-success">Wachtwoord succesvol gewijzigd.</div><?php elseif ($pass_status === 'error_wrong'): ?><div class="alert alert-danger">Huidige wachtwoord is onjuist.</div><?php elseif ($pass_status === 'error_mismatch'): ?><div class="alert alert-danger">Nieuwe wachtwoorden komen niet overeen.</div><?php endif; ?>
                        <form action="save.php" method="POST" class="space-y-4 max-w-md">
                            <input type="hidden" name="action" value="change_password">
                            <div><label class="form-label" for="old_password">Huidig Wachtwoord</label><input class="form-input" type="password" name="old_password" id="old_password" required></div>
                            <div><label class="form-label" for="new_password">Nieuw Wachtwoord</label><input class="form-input" type="password" name="new_password" id="new_password" required></div>
                            <div><label class="form-label" for="confirm_password">Bevestig Nieuw Wachtwoord</label><input class="form-input" type="password" name="confirm_password" id="confirm_password" required></div>
                            <div class="pt-2"><button type="submit" class="btn btn-primary w-full">Wachtwoord Wijzigen</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="tab-mailbox" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Mailbox instellingen</h2></div>
                    <div class="card-body">
                        <?php if ($mailbox_status === 'success'): ?><div class="alert alert-success">Mailbox-instellingen opgeslagen.</div><?php elseif ($mailbox_status === 'error_file'): ?><div class="alert alert-danger">Kon config niet wegschrijven.</div><?php endif; ?>
                        <form action="save.php" method="POST" class="space-y-4 max-w-md">
                            <input type="hidden" name="action" value="update_mailbox">
                            <div>
                                <label class="form-label" for="mail_address">Mailadres</label>
                                <input class="form-input" type="email" name="mail_address" id="mail_address" value="<?php echo htmlspecialchars(defined('SMTP_USERNAME') ? SMTP_USERNAME : (defined('MAIL_FROM') ? MAIL_FROM : '')); ?>" required>
                            </div>
                            <div>
                                <label class="form-label" for="mail_password">Mailbox wachtwoord</label>
                                <input class="form-input" type="password" name="mail_password" id="mail_password" placeholder="••••••••">
                                <p class="text-xs text-slate-500 mt-1">Laat leeg om ongewijzigd te laten.</p>
                            </div>
                            <div class="pt-2"><button type="submit" class="btn btn-primary w-full">Opslaan</button></div>
                        </form>
                        <p class="text-sm text-slate-500 mt-4">Dit adres en wachtwoord wordt gebruikt voor contactformulier mails en voor resetlinks bij "Wachtwoord vergeten".</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="editModal" class="modal-overlay hidden"><div class="modal-content"><div class="modal-header"><h2 class="modal-title">Foto Bewerken</h2><button onclick="closeModal()" class="modal-close-btn">&times;</button></div><form id="editForm" class="modal-body"><input type="hidden" name="action" value="update_photo_details"><input type="hidden" name="theme_name" id="edit_theme_name"><input type="hidden" name="photo_index" id="edit_photo_index"><div><label class="form-label" for="edit_title">Titel</label><input class="form-input" type="text" name="title" id="edit_title"></div><div><label class="form-label" for="edit_description">Omschrijving</label><textarea class="form-input" name="description" id="edit_description" rows="3"></textarea></div><div><label class="form-label" for="edit_alt">Alternatieve Tekst (SEO)</label><input class="form-input" type="text" name="alt" id="edit_alt"></div><div><label class="flex items-center gap-2"><input type="checkbox" name="featured" id="edit_featured" class="form-checkbox">Toon op homepage (uitgelicht)</label></div><div class="modal-footer"><button type="button" onclick="closeModal()" class="btn btn-secondary">Annuleren</button><button type="submit" class="btn btn-primary">Opslaan</button></div></form></div></div>
    <div id="toast-popup" class="toast-popup"></div>
    <div id="confirm-modal" class="modal-overlay hidden"><div class="modal-content text-center max-w-sm"><div class="modal-body"><p id="confirm-text" class="text-lg mb-6"></p><div class="flex justify-center gap-4"><button id="confirm-no" class="btn btn-secondary">Annuleren</button><button id="confirm-yes" class="btn btn-danger">Ja, doorgaan</button></div></div></div></div>
    <div id="upload-progress-container" class="hidden">
        <div class="upload-progress-header">
            <span id="upload-progress-summary">Uploads</span>
            <div class="flex items-center gap-2">
                <button type="button" id="upload-minimize-btn" title="Minimaliseren">&minus;</button>
                <button type="button" id="upload-clear-btn" class="text-xs underline">Annuleren</button>
            </div>
        </div>
        <div id="upload-progress-list"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
    <?php $adminJsVersion = @filemtime('admin.js'); ?>
    <script src="admin.js?v=<?php echo $adminJsVersion; ?>"></script>
</body>
</html>
