<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

// Resolve file paths with fallbacks
$teamFile = defined('TEAM_FILE') ? TEAM_FILE : (defined('DATA_DIR') ? DATA_DIR . '/team/team.json' : __DIR__ . '/data/team/team.json');
$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (defined('DATA_DIR') ? DATA_DIR . '/content.json' : __DIR__ . '/data/content.json');

$siteContent = loadJsonFile($contentFile);
$teamData = loadJsonFile($teamFile);

$metaTitle = 'Team - Groepspraktijk Elewijt';
$metaDescription = $siteContent['meta_description'] ?? '';
$instagramUrl = $siteContent['contact']['instagram_url'] ?? '';
$settings = $siteContent['settings'] ?? [];
$appointmentUrl = $settings['appointment_url'] ?? '';

$members = isset($teamData['members']) && is_array($teamData['members']) ? $teamData['members'] : [];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($metaTitle); ?></title>
  <?php if ($metaDescription): ?><meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>"><?php endif; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body class="antialiased">
<?php $page = 'team'; include TEMPLATES_DIR . '/header.php'; ?>
<div class="main-content">
<main class="py-16">
  <div class="container mx-auto px-6">
    <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Team</h1>
    <div class="grid gap-8 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <?php if (empty($members)): ?>
          <p class="text-slate-600">Er zijn nog geen teamleden toegevoegd.</p>
        <?php else: ?>
        <?php
          // Prepare groups and visible members by group
          $groups = isset($teamData['groups']) && is_array($teamData['groups']) ? $teamData['groups'] : [];
          $visibleMembers = array_values(array_filter($members, function($m){ return !isset($m['visible']) || $m['visible']; }));
          $membersByGroup = [];
          foreach ($visibleMembers as $mm) { $gid = $mm['group_id'] ?? ''; if (!isset($membersByGroup[$gid])) { $membersByGroup[$gid] = []; } $membersByGroup[$gid][] = $mm; }
          $rendered = false;
        ?>
        <?php foreach ($groups as $g): $gid = $g['id'] ?? ''; if (isset($g['visible']) && !$g['visible']) continue; $list = $membersByGroup[$gid] ?? []; if (empty($list)) continue; $rendered = true; ?>
          <section class="mb-8">
            <h2 class="text-2xl font-semibold mb-2" style="font-family: var(--font-heading);">
              <?php echo htmlspecialchars($g['name'] ?? ''); ?>
            </h2>
            <?php if (!empty($g['description'])): ?><p class="text-slate-600 mb-4"><?php echo htmlspecialchars($g['description']); ?></p><?php endif; ?>
            <div class="team-grid grid gap-6 grid-cols-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
              <?php foreach ($list as $m): ?>
                <?php $anchor = 'm-' . (!empty($m['id']) ? preg_replace('/[^a-zA-Z0-9_-]/','', $m['id']) : substr(md5(($m['name'] ?? '') . ($m['role'] ?? '')),0,8)); ?>
                <?php $cardUrl = $m['appointment_url'] ?? $appointmentUrl; ?>
                <article id="<?php echo htmlspecialchars($anchor); ?>" class="team-card rounded-xl overflow-hidden shadow-md bg-white relative <?php echo !empty($cardUrl) ? 'cursor-pointer md:cursor-default' : ''; ?>">
                  <?php if (!empty($cardUrl)): ?>
                    <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="absolute inset-0 md:hidden z-10" aria-label="Maak een afspraak met <?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>"></a>
                  <?php endif; ?>
                  <?php if (!empty($m['image'])): ?>
                  <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>" class="w-full">
                  <?php endif; ?>
                  <div class="p-5">
                    <h3 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h3>
                    <p class="text-slate-600 mb-4"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
                    <?php if (!empty($cardUrl)): ?>
                      <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="btn btn-primary hidden md:inline-flex">Maak een afspraak</a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
        <?php if (!empty($membersByGroup[''])): $rendered = true; ?>
          <section class="mb-8">
            <h2 class="text-2xl font-semibold mb-2" style="font-family: var(--font-heading);">Overige</h2>
            <div class="team-grid grid gap-6 grid-cols-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
              <?php foreach ($membersByGroup[''] as $m): ?>
                <?php $anchor = 'm-' . (!empty($m['id']) ? preg_replace('/[^a-zA-Z0-9_-]/','', $m['id']) : substr(md5(($m['name'] ?? '') . ($m['role'] ?? '')),0,8)); ?>
                <?php $cardUrl = $m['appointment_url'] ?? $appointmentUrl; ?>
                <article id="<?php echo htmlspecialchars($anchor); ?>" class="team-card rounded-xl overflow-hidden shadow-md bg-white relative <?php echo !empty($cardUrl) ? 'cursor-pointer md:cursor-default' : ''; ?>">
                  <?php if (!empty($cardUrl)): ?>
                    <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="absolute inset-0 md:hidden z-10" aria-label="Maak een afspraak met <?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>"></a>
                  <?php endif; ?>
                  <?php if (!empty($m['image'])): ?>
                  <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>" class="w-full">
                  <?php endif; ?>
                  <div class="p-5">
                    <h3 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h3>
                    <p class="text-slate-600 mb-4"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
                    <?php if (!empty($cardUrl)): ?>
                      <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="btn btn-primary hidden md:inline-flex">Maak een afspraak</a>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
        <?php if (!empty($appointmentUrl) && $rendered): ?>
        <div class="mt-10 text-center">
          <a href="<?php echo htmlspecialchars(safeUrl($appointmentUrl)); ?>" target="_blank" class="btn btn-primary">Maak een afspraak</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php
      $pinned = ($siteContent['pinned'] ?? []);
      $pinnedList = [];
      foreach ($pinned as $pin) {
        $scope = $pin['scope'] ?? [];
        if (!is_array($scope)) $scope = ($scope==='all') ? ['all'] : [];
        if (in_array('all',$scope) || in_array('team',$scope)) $pinnedList[] = $pin;
      }
      ?>
      <aside class="space-y-4 lg:col-span-1">
        <?php foreach ($pinnedList as $pin): ?>
        <div class="rounded-xl p-5 pinned-card">
          <h3 class="text-lg font-semibold mb-2"><?php echo htmlspecialchars($pin['title'] ?? ''); ?></h3>
          <div class="prose max-w-none"><?php echo $pin['text'] ?? ''; ?></div>
        </div>
        <?php endforeach; ?>
      </aside>
    </div>
  </div>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
