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
 * Provides a tabbed interface to manage website content.
 * The UI uses URL hashes (e.g. #tab-team)
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

// Load data sources for Groepspraktijk Elewijt
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
$save_status = $_GET['save_status'] ?? '';
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
                <a href="index.php" target="_blank" class="btn btn-secondary">
                    <span>Bekijk Website</span>
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2A.75.75 0 0010.75 3.5h-5.5A.75.75 0 004.5 4.25v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.75a.75.75 0 000 1.5h9.5a.75.75 0 00.75-.75z" clip-rule="evenodd" /><path fill-rule="evenodd" d="M15.53 6.47a.75.75 0 00-1.06 0l-3 3a.75.75 0 000 1.06l3 3a.75.75 0 101.06-1.06L13.06 10l2.47-2.47a.75.75 0 000-1.06z" clip-rule="evenodd" /></svg>
                    <span>Uitloggen</span>
                </a>
            </div>
        </header>

        <?php if ($save_status === 'success'): ?>
            <div class="alert alert-success mb-4">
                <strong>Succes!</strong> De wijzigingen zijn opgeslagen.
            </div>
        <?php elseif ($save_status === 'error'): ?>
            <div class="alert alert-danger mb-4">
                <strong>Fout bij opslaan!</strong> De wijzigingen konden niet worden opgeslagen. Dit komt meestal door een permissieprobleem op de server. Controleer of de `data` map en de bestanden daarin (zoals `team.json`) beschrijfbaar zijn voor de webserver.
            </div>
        <?php endif; ?>

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
                    <form action="save.php" method="POST">
                        <div class="card-body space-y-6">
                            <input type="hidden" name="action" value="update_content">
                            <div>
                                <label class="form-label" for="hero_title">Hero Titel (tekst op homepagina foto)</label>
                                <input class="form-input" type="text" name="hero_title" id="hero_title" value="<?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label">Hero Foto</label>
                                <div id="hero_image_container" class="relative group w-full aspect-[16/6] bg-slate-200 rounded-md cursor-pointer overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($content['hero']['image'] ?? 'https://placehold.co/1200x400'); ?>" class="w-full h-full object-cover" alt="Huidige hero foto">
                                    <div class="dropzone absolute inset-0" data-target="hero"><span>Sleep een nieuwe foto hier of klik</span></div>
                                </div>
                                <input class="hidden" type="file" id="hero_image_input" accept="image/*">
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
                                        <div class="flex items-center justify-between">
                                            <label class="form-label">Inhoud</label>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">Verwijder</button>
                                        </div>
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
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><span>Homepage Opslaan</span></button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-team" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Nieuw Teamlid Toevoegen</h2>
                    </div>
                    <?php $groups = isset($teamData['groups']) && is_array($teamData['groups']) ? $teamData['groups'] : []; ?>
                    <form action="save.php" method="POST">
                        <div class="card-body grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <input type="hidden" name="action" value="add_team_member">
                            <div>
                                <label class="form-label">Naam</label>
                                <input type="text" name="name" class="form-input" placeholder="Naam" required>
                            </div>
                            <div>
                                <label class="form-label">Functie</label>
                                <input type="text" name="role" class="form-input" placeholder="Functie" required>
                            </div>
                            <div>
                                <label class="form-label">Functiegroep</label>
                                <select name="group_id" class="form-input">
                                    <option value="">Geen (ongegroepeerd)</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g['id'] ?? ''); ?>"><?php echo htmlspecialchars($g['name'] ?? ''); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Afspraak URL (optioneel)</label>
                                <input type="text" name="appointment_url" class="form-input" placeholder="https://...">
                            </div>
                            <div class="md:col-span-4">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" name="visible" checked>
                                    <span>Zichtbaar</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer">
                             <button type="submit" class="btn btn-primary">Toevoegen</button>
                        </div>
                    </form>
                </div>
                <div class="card mt-6">
                    <div class="card-header">
                        <h2 class="card-title">Functiegroepen</h2>
                    </div>
                    <div class="card-body space-y-6">
                        <form action="save.php" method="POST" class="border border-slate-200 rounded-lg p-4">
                            <input type="hidden" name="action" value="add_team_group">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label class="form-label">Naam</label>
                                    <input type="text" name="name" class="form-input" placeholder="Bijv. Huisartsen" required>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="form-label">Beschrijving (optioneel)</label>
                                    <input type="text" name="description" class="form-input" placeholder="Korte omschrijving">
                                </div>
                                <div class="md:col-span-3 flex items-center justify-between">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="visible" checked>
                                        <span>Zichtbaar</span>
                                    </label>
                                    <button type="submit" class="btn btn-secondary">Groep toevoegen</button>
                                </div>
                            </div>
                        </form>

                        <div>
                            <h3 class="text-lg font-semibold mb-2">Bestaande Functiegroepen</h3>
                            <div id="team-groups" class="space-y-3">
                                <?php foreach ($groups as $g): $gid = $g['id'] ?? uniqid('grp_', true); ?>
                                <div class="border border-slate-200 rounded-lg p-4" data-id="<?php echo htmlspecialchars($gid); ?>">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="drag-handle cursor-move text-slate-400" title="Sleep">&#9776;</span>
                                        <form action="save.php" method="POST" class="delete-form">
                                            <input type="hidden" name="action" value="delete_team_group">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($gid); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Verwijder</button>
                                        </form>
                                    </div>
                                    <form action="save.php" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                        <input type="hidden" name="action" value="update_team_group">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($gid); ?>">
                                        <div>
                                            <label class="form-label">Naam</label>
                                            <input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($g['name'] ?? ''); ?>">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="form-label">Beschrijving</label>
                                            <input type="text" class="form-input" name="description" value="<?php echo htmlspecialchars($g['description'] ?? ''); ?>">
                                        </div>
                                        <div class="md:col-span-3 flex items-center justify-between">
                                            <label class="inline-flex items-center gap-2">
                                                <input type="checkbox" name="visible" <?php echo !isset($g['visible']) || $g['visible'] ? 'checked' : ''; ?>>
                                                <span>Zichtbaar</span>
                                            </label>
                                            <button type="submit" class="btn btn-secondary">Groep bijwerken</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-6">
                    <div class="card-header">
                        <h2 class="card-title">Bestaande Teamleden</h2>
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
                                        <img src="<?php echo htmlspecialchars($m['image'] ? $m['image'] : 'https://placehold.co/400x300?text=Foto'); ?>" alt="Foto van <?php echo htmlspecialchars($m['name'] ?? ''); ?>" class="w-full h-full object-cover">
                                        <div class="dropzone absolute inset-0" data-target="team" data-member-id="<?php echo htmlspecialchars($mid); ?>"><span>Sleep foto of klik</span></div>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <form action="save.php" method="POST" class="space-y-2" id="update-form-<?php echo htmlspecialchars($mid); ?>">
                                        <input type="hidden" name="action" value="update_team_member">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                        <label class="form-label">Naam</label>
                                        <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>">
                                        <label class="form-label">Functie</label>
                                        <input type="text" name="role" class="form-input" value="<?php echo htmlspecialchars($m['role'] ?? ''); ?>">
                                        <label class="form-label">Functiegroep</label>
                                        <select name="group_id" class="form-input">
                                            <option value="">Geen (ongegroepeerd)</option>
                                            <?php foreach ($groups as $g): $gid = $g['id'] ?? ''; ?>
                                                <option value="<?php echo htmlspecialchars($gid); ?>" <?php echo (($m['group_id'] ?? '') === $gid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name'] ?? ''); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label class="form-label">Afspraak URL</label>
                                        <input type="text" name="appointment_url" class="form-input" value="<?php echo htmlspecialchars($m['appointment_url'] ?? ''); ?>">
                                        <label class="inline-flex items-center gap-2 mt-2">
                                            <input type="checkbox" name="visible" <?php echo !isset($m['visible']) || $m['visible'] ? 'checked' : ''; ?>>
                                            <span>Zichtbaar</span>
                                        </label>
                                    </form>
                                    <div class="flex gap-2 pt-2">
                                        <button type="submit" form="update-form-<?php echo htmlspecialchars($mid); ?>" class="btn btn-secondary">Opslaan</button>
                                        <form action="save.php" method="POST" class="delete-form">
                                            <input type="hidden" name="action" value="delete_team_member">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($mid); ?>">
                                            <button type="submit" class="btn btn-danger">Verwijder</button>
                                        </form>
                                    </div>
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
                            <input type="text" class="form-input" name="title" placeholder="Titel nieuwe pagina" required>
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
                                            <form action="save.php" method="POST" class="delete-form">
                                                <input type="hidden" name="action" value="delete_practice_page">
                                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Verwijder</button>
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
                            <form action="save.php" method="POST">
                                <div class="card-body">
                                    <input type="hidden" name="action" value="save_practice_page">
                                    <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
                                    <label class="form-label">Titel</label>
                                    <input type="text" class="form-input" name="title" value="<?php echo htmlspecialchars($pd['title'] ?? ''); ?>">
                                        <div class="space-y-3 mt-4" id="cards-<?php echo htmlspecialchars($slug); ?>">
                                            <?php foreach ($cards as $c): ?>
                                            <div class="border border-slate-200 rounded-md p-3">
                                                <div class="flex items-center justify-between">
                                                    <label class="form-label">Inhoud</label>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div.border').remove()">Verwijder kaart</button>
                                                </div>
                                                <textarea name="card_html[]" class="form-textarea richtext" rows="5"><?php echo htmlspecialchars($c['html'] ?? ''); ?></textarea>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-secondary mt-2" onclick="window.addPracticeCard('cards-<?php echo htmlspecialchars($slug); ?>')">Kaart toevoegen</button>
                                        </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">Pagina opslaan</button>
                                </div>
                            </form>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-team-sort-panel" class="admin-tab-panel">
            <div class="card mt-6">
                <div class="card-header">
                    <h2 class="card-title">Sortering Teamleden per Functiegroep</h2>
                </div>
                <div class="card-body space-y-6">
                    <?php
                        $members = isset($teamData['members']) && is_array($teamData['members']) ? $teamData['members'] : [];
                        $groups = isset($teamData['groups']) && is_array($teamData['groups']) ? $teamData['groups'] : [];
                        $membersByGroup = [];
                        foreach ($members as $mm) { $gid = $mm['group_id'] ?? ''; $membersByGroup[$gid] = $membersByGroup[$gid] ?? []; $membersByGroup[$gid][] = $mm; }
                    ?>
                    <?php foreach ($groups as $g): $gid = $g['id'] ?? ''; ?>
                    <div>
                        <h3 class="font-semibold mb-2"><?php echo htmlspecialchars($g['name'] ?? ''); ?></h3>
                        <div class="space-y-2 team-members-list" data-group-id="<?php echo htmlspecialchars($gid); ?>">
                            <?php foreach (($membersByGroup[$gid] ?? []) as $mm): ?>
                            <div class="flex items-center justify-between border border-slate-200 rounded-md p-2" data-id="<?php echo htmlspecialchars($mm['id'] ?? ''); ?>">
                                <div class="flex items-center gap-2">
                                    <span class="drag-handle cursor-move text-slate-400" title="Sleep">&#9776;</span>
                                    <span><?php echo htmlspecialchars(($mm['name'] ?? '') . ' - ' . ($mm['role'] ?? '')); ?></span>
                                </div>
                                <?php if (isset($mm['visible']) && !$mm['visible']): ?><span class="text-xs text-slate-500">Verborgen</span><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!empty($membersByGroup[''])): ?>
                    <div>
                        <h3 class="font-semibold mb-2">Ongegroepeerd</h3>
                        <div class="space-y-2 team-members-list" data-group-id="">
                            <?php foreach ($membersByGroup[''] as $mm): ?>
                            <div class="flex items-center gap-2 border border-slate-200 rounded-md p-2" data-id="<?php echo htmlspecialchars($mm['id'] ?? ''); ?>">
                                <span class="drag-handle cursor-move text-slate-400" title="Sleep">&#9776;</span>
                                <span><?php echo htmlspecialchars(($mm['name'] ?? '') . ' - ' . ($mm['role'] ?? '')); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>

            <div id="tab-links" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Nuttige links</h2></div>
                    <?php $hero = $linksData['hero'] ?? ['title' => '', 'image' => '']; $items = $linksData['items'] ?? []; ?>
                     <form action="save.php" method="POST">
                        <div class="card-body space-y-4">
                            <input type="hidden" name="action" value="save_links">
                            <div>
                                <label class="form-label">Hero Titel</label>
                                <input type="text" class="form-input" name="hero_title" value="<?php echo htmlspecialchars($hero['title'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="form-label">Hero Afbeelding</label>
                                <div class="relative group w-full aspect-[16/6] bg-slate-100 rounded-md overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($hero['image'] ? $hero['image'] : 'https://placehold.co/1200x400?text=Hero+Afbeelding'); ?>" alt="Hero afbeelding voor nuttige links" class="w-full h-full object-cover">
                                    <div class="dropzone absolute inset-0" data-target="links_hero"><span>Sleep foto of klik</span></div>
                                </div>
                            </div>
                            <div id="links-list" class="space-y-2">
                                <?php foreach ($items as $it): $iid = $it['id'] ?? uniqid('link_', true); ?>
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-center" data-id="<?php echo htmlspecialchars($iid); ?>">
                                    <input type="hidden" name="link_id[]" value="<?php echo htmlspecialchars($iid); ?>">
                                    <div class="flex items-center gap-2">
                                        <span class="drag-handle cursor-move" title="Sleep om te verplaatsen">&#9776;</span>
                                        <input type="text" class="form-input flex-1" name="link_label[]" placeholder="Naam" value="<?php echo htmlspecialchars($it['label'] ?? ''); ?>">
                                    </div>
                                    <input type="text" class="form-input" name="link_url[]" placeholder="https://... of www..." value="<?php echo htmlspecialchars($it['url'] ?? ''); ?>">
                                    <input type="text" class="form-input" name="link_tel[]" placeholder="Telefoon (optioneel)" value="<?php echo htmlspecialchars($it['tel'] ?? ''); ?>">
                                    <input type="text" class="form-input" name="link_category[]" placeholder="Categorie (optioneel)" value="<?php echo htmlspecialchars($it['category'] ?? ''); ?>">
                                    <div class="flex gap-2 items-center">
                                        <input type="text" class="form-input flex-1" name="link_desc[]" placeholder="Omschrijving (optioneel)" value="<?php echo htmlspecialchars($it['description'] ?? ''); ?>">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.grid').remove()">X</button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div>
                                <button type="button" class="btn btn-secondary" onclick="window.addLinkItem()">Link toevoegen</button>
                            </div>
                        </div>
                        <div class="card-footer">
                             <button type="submit" class="btn btn-primary">Links Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-settings" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Algemene instellingen</h2></div>
                    <?php $settings = $content['settings'] ?? []; ?>
                    <form action="save.php" method="POST">
                        <div class="card-body space-y-4">
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
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-2 items-center">
                                        <input type="text" class="form-input" name="phone_label[]" placeholder="Naam" value="<?php echo htmlspecialchars($ph['label'] ?? ''); ?>">
                                        <input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon" value="<?php echo htmlspecialchars($ph['tel'] ?? ''); ?>">
                                        <input type="text" class="form-input" name="phone_desc[]" placeholder="Omschrijving (optioneel)" value="<?php echo htmlspecialchars($ph['desc'] ?? ''); ?>">
                                        <div class="flex gap-2 items-center">
                                            <input type="text" class="form-input flex-1" name="phone_url[]" placeholder="Link (optioneel)" value="<?php echo htmlspecialchars($ph['url'] ?? ''); ?>">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.grid').remove()">X</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary mt-2" onclick="window.addPhoneItem()">Nummer toevoegen</button>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Instellingen opslaan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-pinned" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Gepinde berichten</h2></div>
                    <?php $pinned = isset($content['pinned']) && is_array($content['pinned']) ? $content['pinned'] : []; ?>
                    <form action="save.php" method="POST">
                        <div class="card-body">
                            <input type="hidden" name="action" value="save_pinned">
                            <div class="admin-table-container mb-4">
                                <table class="admin-table" id="pinned-table">
                                    <thead>
                                        <tr>
                                            <th style="width:2rem;"></th>
                                            <th>Titel</th>
                                            <th>Home</th>
                                            <th>Team</th>
                                            <th>Praktijk</th>
                                            <th>Links</th>
                                            <th>Telefoon</th>
                                            <th>Alle</th>
                                            <th>Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pinned-table-body">
                                        <?php foreach ($pinned as $pin): $pid = $pin['id'] ?? uniqid('pin_', true); $scope = $pin['scope'] ?? []; if (!is_array($scope)) { $scope = ($scope==='all') ? ['all'] : []; } ?>
                                        <tr data-id="<?php echo htmlspecialchars($pid); ?>">
                                            <td class="text-slate-400"><span class="drag-handle" title="Sleep">&#9776;</span></td>
                                            <td class="font-medium"><button type="button" class="btn btn-secondary btn-sm" onclick="document.querySelector('.pinned-item-editor[data-id=\'<?php echo htmlspecialchars($pid); ?>\']')?.classList.toggle('hidden')">Bewerk</button> <?php echo htmlspecialchars($pin['title'] ?? '(zonder titel)'); ?></td>
                                            <td><input type="checkbox" name="pinned_scope_home[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('all',$scope)||in_array('home',$scope)?'checked':''; ?>></td>
                                            <td><input type="checkbox" name="pinned_scope_team[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('all',$scope)||in_array('team',$scope)?'checked':''; ?>></td>
                                            <td><input type="checkbox" name="pinned_scope_practice[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('all',$scope)||in_array('practice',$scope)?'checked':''; ?>></td>
                                            <td><input type="checkbox" name="pinned_scope_links[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('all',$scope)||in_array('links',$scope)?'checked':''; ?>></td>
                                            <td><input type="checkbox" name="pinned_scope_phones[]" value="<?php echo htmlspecialchars($pid); ?>" <?php echo in_array('all',$scope)||in_array('phones',$scope)?'checked':''; ?>></td>
                                            <td>
                                                <input type="hidden" name="pinned_scope_all[]" value="<?php echo in_array('all',$scope)?htmlspecialchars($pid):''; ?>">
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="window.togglePinnedAll(this)"><?php echo in_array('all',$scope)?'Wis':'Alle'; ?></button>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="(function(btn){ const id='<?php echo htmlspecialchars($pid); ?>'; const box=document.querySelector('.pinned-item-editor[data-id=\''+id+'\']'); if(box){ box.remove(); } const row=btn.closest('tr'); row?.remove(); })(this)">Verwijder</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="pinned-list" class="space-y-4">
                                <?php foreach ($pinned as $pin): $pid = $pin['id'] ?? uniqid('pin_', true); ?>
                                <div class="border border-slate-200 rounded-lg p-4 pinned-item-editor hidden" data-id="<?php echo htmlspecialchars($pid); ?>">
                                    <input type="hidden" name="pinned_id[]" value="<?php echo htmlspecialchars($pid); ?>">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="form-label mb-0">Titel</label>
                                        <span class="drag-handle cursor-move text-slate-400" title="Sleep om te verplaatsen">&#9776;</span>
                                    </div>
                                    <input type="text" class="form-input" name="pinned_title[]" value="<?php echo htmlspecialchars($pin['title'] ?? ''); ?>">
                                    <label class="form-label mt-2">Tekst</label>
                                    <textarea class="form-textarea richtext" name="pinned_text[]" rows="4"><?php echo htmlspecialchars($pin['text'] ?? ''); ?></textarea>
                                    <p class="text-xs text-slate-500 mt-2">Scopes aanpassen via de tabel hierboven. Verwijderen kan in de tabel.</p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-secondary mt-4" onclick="window.addPinnedItem()">Bericht toevoegen</button>
                        </div>
                         <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Gepinde Berichten Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="tab-security" class="admin-tab-panel">
                 <div class="card">
                    <div class="card-header"><h2 class="card-title">Beveiliging</h2></div>
                    <form action="save.php" method="POST">
                        <div class="card-body">
                            <?php if ($pass_status === 'success'): ?><div class="alert alert-success">Wachtwoord succesvol gewijzigd.</div><?php elseif ($pass_status === 'error_wrong'): ?><div class="alert alert-danger">Huidige wachtwoord is onjuist.</div><?php elseif ($pass_status === 'error_mismatch'): ?><div class="alert alert-danger">Nieuwe wachtwoorden komen niet overeen.</div><?php endif; ?>
                            <div class="space-y-4 max-w-md">
                                <input type="hidden" name="action" value="change_password">
                                <div><label class="form-label" for="old_password">Huidig Wachtwoord</label><input class="form-input" type="password" name="old_password" id="old_password" required></div>
                                <div><label class="form-label" for="new_password">Nieuw Wachtwoord</label><input class="form-input" type="password" name="new_password" id="new_password" required></div>
                                <div><label class="form-label" for="confirm_password">Bevestig Nieuw Wachtwoord</label><input class="form-input" type="password" name="confirm_password" id="confirm_password" required></div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Wachtwoord Wijzigen</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="tab-mailbox" class="admin-tab-panel">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Mailbox instellingen</h2></div>
                    <form action="save.php" method="POST">
                        <div class="card-body">
                            <?php if ($mailbox_status === 'success'): ?><div class="alert alert-success">Mailbox-instellingen opgeslagen.</div><?php elseif ($mailbox_status === 'error_file'): ?><div class="alert alert-danger">Kon config niet wegschrijven.</div><?php endif; ?>
                            <div class="space-y-4 max-w-md">
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
                                <p class="text-sm text-slate-500 pt-2">Dit adres en wachtwoord wordt gebruikt voor contactformulier mails en voor resetlinks bij "Wachtwoord vergeten".</p>
                            </div>
                        </div>
                        <div class="card-footer">
                             <button type="submit" class="btn btn-primary">Opslaan</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-popup" class="toast-popup"></div>
    <div id="confirm-modal" class="modal-overlay hidden">
        <div class="modal-content">
            <div class="modal-body text-center">
                <p id="confirm-text" class="text-lg mb-6">Weet je het zeker?</p>
                <div class="flex justify-center gap-4">
                    <button id="confirm-no" class="btn btn-secondary">Annuleren</button>
                    <button id="confirm-yes" class="btn btn-danger">Ja, doorgaan</button>
                </div>
            </div>
        </div>
    </div>
    <div id="upload-progress-container" class="hidden">
        <div class="upload-progress-header">
            <span id="upload-progress-summary">Uploads</span>
            <button type="button" id="upload-clear-btn" class="text-xs underline">Annuleren</button>
        </div>
        <div id="upload-progress-list"></div>
    </div>
    
    <script>
    // Initialize CKEditor on any .richtext textarea
    window.initRichtext = function(el){
      if (!window.ClassicEditor || !el || el._ck_inited) return;
      ClassicEditor.create(el, { toolbar: ['heading','bold','italic','link','bulletedList','numberedList','undo','redo'] })
        .then(editor => { el._ck = editor; el._ck_inited = true; })
        .catch(() => {});
    };
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('textarea.richtext').forEach(window.initRichtext);
    });

    // Helpers to add dynamic fields
    window.addWelcomeCard = function(){
      const c = document.getElementById('welcome-cards'); if(!c) return;
      const d = document.createElement('div');
      d.className = 'border border-slate-200 rounded-md p-3';
      d.innerHTML = '<div class="flex items-center justify-between"><label class="form-label">Inhoud</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.border\').remove()">Verwijder</button></div><textarea name="welcome_card_html[]" class="form-textarea richtext" rows="4"></textarea>';
      c.appendChild(d);
      window.initRichtext(d.querySelector('textarea.richtext'));
    };
    window.togglePinnedAll = function(btn){
      // Try row-based (table) first
      let container = btn.closest('tr[data-id]');
      if (container) {
        const id = container.dataset.id;
        const checks = container.querySelectorAll('input[type=checkbox]');
        const allInput = container.querySelector('input[name="pinned_scope_all[]"]');
        const turnOn = !allInput || allInput.value === '';
        checks.forEach(cb => cb.checked = turnOn);
        if (allInput) allInput.value = turnOn ? id : '';
        btn.textContent = turnOn ? 'Wis' : "Alle";
        return;
      }
      // Fallback: old editor box
      container = btn.closest('.border[data-id]');
      if (!container) return;
      const id = container.dataset.id;
      const checkboxes = container.querySelectorAll('input[type=checkbox]');
      const allInput = container.querySelector('input[name="pinned_scope_all[]"]');
      const turnOn = allInput && allInput.value === '';
      checkboxes.forEach(cb => cb.checked = turnOn);
      if (allInput) allInput.value = turnOn ? id : '';
      btn.textContent = turnOn ? 'Selectie wissen' : "Alle pagina's";
    };
    window.addPracticeCard = function(containerId){
      const c = document.getElementById(containerId); if(!c) return;
      const wrap = document.createElement('div');
      wrap.className = 'border border-slate-200 rounded-md p-3';
      wrap.innerHTML = '<div class="flex items-center justify-between"><label class="form-label">Inhoud</label><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.border\').remove()">Verwijder</button></div><textarea name="card_html[]" class="form-textarea richtext" rows="5"></textarea>';
      c.appendChild(wrap);
      window.initRichtext(wrap.querySelector('textarea.richtext'));
    };
    window.addLinkItem = function(){
      const list = document.getElementById('links-list'); if(!list) return;
      const id = 'link_' + Math.random().toString(36).substring(2, 9);
      const row = document.createElement('div');
      row.className = 'grid grid-cols-1 md:grid-cols-5 gap-2 items-center';
      row.dataset.id = id;
      row.innerHTML = '<input type="hidden" name="link_id[]" value="'+id+'">'
        + '<div class="flex items-center gap-2"><span class="drag-handle cursor-move" title="Sleep">&#9776;</span><input type="text" class="form-input flex-1" name="link_label[]" placeholder="Naam"></div>'
        + '<input type="text" class="form-input" name="link_url[]" placeholder="https://... of www...">'
        + '<input type="text" class="form-input" name="link_tel[]" placeholder="Telefoon (optioneel)">'
        + '<input type="text" class="form-input" name="link_category[]" placeholder="Categorie (optioneel)">'
        + '<div class="flex gap-2 items-center"><input type="text" class="form-input flex-1" name="link_desc[]" placeholder="Omschrijving (optioneel)"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div.grid\').remove()">X</button></div>';
      list.appendChild(row);
    };
    window.addPhoneItem = function(){
      const list = document.getElementById('phones-list'); if(!list) return;
      const row = document.createElement('div');
      row.className = 'grid grid-cols-1 md:grid-cols-4 gap-2 items-center';
      row.innerHTML = '<input type="text" class="form-input" name="phone_label[]" placeholder="Naam">'
        + '<input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon">'
        + '<input type="text" class="form-input" name="phone_desc[]" placeholder="Omschrijving (optioneel)">'
        + '<div class="flex gap-2 items-center"><input type="text" class="form-input flex-1" name="phone_url[]" placeholder="Link (optioneel)"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'div.grid\').remove()">X</button></div>';
      list.appendChild(row);
    };
    window.addPinnedItem = function(){
      const list = document.getElementById('pinned-list'); if(!list) return;
      const id = 'pin_' + Math.random().toString(36).substring(2, 9);
      const box = document.createElement('div');
      box.className = 'border border-slate-200 rounded-lg p-4';
      box.dataset.id = id;
      box.innerHTML = `<input type="hidden" name="pinned_id[]" value="${id}">
        <div class="flex items-center justify-between mb-2">
            <label class="form-label mb-0">Titel</label>
            <div class="flex items-center gap-2">
                <span class="drag-handle cursor-move text-slate-400" title="Sleep">&#9776;</span>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">Verwijder Bericht</button>
            </div>
        </div>
        <input type="text" class="form-input" name="pinned_title[]" value="">
        <label class="form-label mt-2">Tekst</label>
        <textarea class="form-textarea richtext" name="pinned_text[]" rows="4"></textarea>
        <p class="text-xs text-slate-500 mt-2">Scopes instellen na opslaan in de tabel hierboven.</p>`;
      list.appendChild(box);
      window.initRichtext(box.querySelector('textarea.richtext'));
    };
    </script>

    <?php $adminJsVersion = @filemtime('admin.js'); ?>
    <script src="admin.js?v=<?php echo $adminJsVersion; ?>"></script>
</body>
</html>

