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
    <link rel="stylesheet" href="style.css">
</head>
<body id="index-page" class="antialiased">
    <?php
        $page = 'index';
        include TEMPLATES_DIR . '/header.php';
    ?>
<div class="main-content">
    <?php
        function loadContent($file) {
            if (file_exists($file)) { return json_decode(file_get_contents($file), true); }
            return [];
        }
        $content = $siteContent;
        // Load team data for homepage grid
        $teamFile = defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/data/team/team.json');
        $teamData = file_exists($teamFile) ? (json_decode(file_get_contents($teamFile), true) ?: []) : [];
        $teamMembers = $teamData['members'] ?? [];
        // Respect visibility flags if set
        if (is_array($teamMembers)) {
            $teamMembers = array_values(array_filter($teamMembers, function($m){ return !isset($m['visible']) || $m['visible']; }));
        }
    ?>

    <section id="home" class="h-screen min-h-[600px] bg-cover bg-center bg-fixed flex items-center justify-center stagger-container" style="background-image: url('<?php echo htmlspecialchars($content['hero']['image'] ?? ''); ?>');">
        <div class="w-full h-full flex items-center justify-center px-4">
            <div class="text-center max-w-4xl reveal">
              <h1 class="text-4xl md:text-5xl" style="font-family: var(--font-heading);">
                <?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>
              </h1>
              <?php if (!empty($content['hero']['body'])): ?>
              <div class="mt-4 leading-relaxed hero-body mx-auto">
                <?php echo $content['hero']['body']; ?>
              </div>
              <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="welkom" style="background-color: var(--surface);">
        <div class="container mx-auto px-6 stagger-container">
            <div class="grid gap-8 lg:grid-cols-3 items-start">
                <div class="lg:col-span-2">
                    <div class="text-center md:text-left reveal">
                        <h2 class="text-4xl md:text-5xl mb-6"><?php echo htmlspecialchars($content['welcome']['title'] ?? 'Welkom'); ?></h2>
                        <?php if (!empty($content['welcome']['text'])): ?>
                        <div class="prose max-w-none mb-8"><?php echo $content['welcome']['text']; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php $wcards = isset($content['welcome']['cards']) && is_array($content['welcome']['cards']) ? $content['welcome']['cards'] : []; ?>
                    <?php if (!empty($wcards)): ?>
                    <div class="grid gap-6 md:grid-cols-2">
                        <?php foreach ($wcards as $card): ?>
                        <div class="rounded-xl p-6 welcome-card reveal">
                            <div class="prose max-w-none"><?php echo $card['html'] ?? ''; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php
                $pinned = ($siteContent['pinned'] ?? []);
                $pinnedList = [];
                foreach ($pinned as $pin) {
                  $scope = $pin['scope'] ?? [];
                  if (!is_array($scope)) $scope = ($scope==='all') ? ['all'] : [];
                  if (in_array('all',$scope) || in_array('home',$scope)) $pinnedList[] = $pin;
                }
                ?>
                <aside class="space-y-4 reveal lg:col-span-1">
                    <?php foreach ($pinnedList as $pin): ?>
                    <div class="rounded-xl p-5 pinned-card">
                        <h3 class="text-lg font-semibold mb-2"><?php echo htmlspecialchars($pin['title'] ?? ''); ?></h3>
                        <div class="prose max-w-none"><?php echo $pin['text'] ?? ''; ?></div>
                    </div>
                    <?php endforeach; ?>
                </aside>
            </div>
        </div>
    </section>

    <section id="team-home" style="background-color: var(--background);">
        <div class="container mx-auto px-6 stagger-container">
            <div class="text-center reveal">
                <h2 class="text-4xl md:text-5xl mb-12">Ons Team</h2>
            </div>
            <?php if (!empty($teamMembers)): ?>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($teamMembers as $m): ?>
                <article class="team-card rounded-xl overflow-hidden shadow-md bg-white reveal">
                    <?php if (!empty($m['image'])): ?>
                    <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? ''); ?>" class="w-full">
                    <?php endif; ?>
                    <div class="p-5">
                        <h3 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h3>
                        <p class="text-slate-600 mb-4"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
                        <?php $url = $m['appointment_url'] ?? ($siteContent['settings']['appointment_url'] ?? ''); if ($url): ?>
                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="btn btn-primary">Maak een afspraak</a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="text-center text-slate-600 reveal">Team wordt binnenkort toegevoegd.</p>
            <?php endif; ?>
        </div>
    </section>

</div>
    <?php include TEMPLATES_DIR . '/footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
