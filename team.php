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
    <?php
      $pinned = ($siteContent['pinned'] ?? []);
      $pinnedList = array_filter($pinned, function($pin) {
          $scope = $pin['scope'] ?? [];
          if (!is_array($scope)) $scope = ($scope === 'all') ? ['all'] : [];
          return in_array('all', $scope) || in_array('team', $scope);
      });
      $hasPinned = !empty($pinnedList);
    ?>

    <div class="grid gap-x-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h1 class="text-4xl font-semibold mb-4" style="font-family: var(--font-heading);">Ons Team</h1>
        </div>
        <?php if ($hasPinned): ?>
        <div class="lg:col-span-1">
            <h2 class="text-3xl font-semibold mb-4 hidden lg:block" style="font-family: var(--font-heading);">Belangrijke mededelingen</h2>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid gap-x-8 <?php echo $hasPinned ? 'lg:grid-cols-3' : 'lg:grid-cols-1'; ?> items-start">
      <div class="<?php echo $hasPinned ? 'lg:col-span-2' : ''; ?>">
        <?php if (empty($members)): ?>
          <p class="text-slate-600">Er zijn nog geen teamleden toegevoegd.</p>
        <?php else: ?>
        <?php
          $groups = isset($teamData['groups']) && is_array($teamData['groups']) ? $teamData['groups'] : [];
          $visibleMembers = array_values(array_filter($members, fn($m) => !isset($m['visible']) || $m['visible']));
          $membersByGroup = [];
          foreach ($visibleMembers as $mm) {
              $gid = $mm['group_id'] ?? '';
              if (!isset($membersByGroup[$gid])) $membersByGroup[$gid] = [];
              $membersByGroup[$gid][] = $mm;
          }
        ?>
        <div class="space-y-3">
            <?php foreach ($groups as $g): $gid = $g['id'] ?? ''; if (isset($g['visible']) && !$g['visible']) continue; $list = $membersByGroup[$gid] ?? []; if (empty($list)) continue; ?>
              <section class="team-group">
                <h3 class="text-2xl font-semibold mb-2" style="font-family: var(--font-heading);">
                  <?php echo htmlspecialchars($g['name'] ?? ''); ?>
                </h3>
                <?php if (!empty($g['description'])): ?><p class="text-slate-600 mb-4 max-w-2xl"><?php echo htmlspecialchars($g['description']); ?></p><?php endif; ?>
                <div class="team-grid grid gap-6 grid-cols-2 sm:grid-cols-2 lg:grid-cols-3">
                  <?php foreach ($list as $m): ?>
                    <?php $cardUrl = $m['appointment_url'] ?? $appointmentUrl; ?>
                    <article class="team-card group">
                      <div class="relative overflow-hidden rounded-t-lg">
                        <?php if (!empty($m['image'])): ?>
                        <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>" class="w-full aspect-[4/5] object-cover">
                        <?php endif; ?>
                        <?php if (!empty($cardUrl)): ?>
                          <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="btn btn-primary">Maak afspraak</a>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="p-5 text-center">
                        <h4 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h4>
                        <p class="text-slate-600"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endforeach; ?>

            <?php if (!empty($membersByGroup[''])): ?>
              <section class="team-group">
                <h3 class="text-2xl font-semibold mb-2" style="font-family: var(--font-heading);">Overige</h3>
                <div class="team-grid grid gap-6 grid-cols-2 sm:grid-cols-2 lg:grid-cols-3">
                  <?php foreach ($membersByGroup[''] as $m): ?>
                    <?php $cardUrl = $m['appointment_url'] ?? $appointmentUrl; ?>
                    <article class="team-card group">
                       <div class="relative overflow-hidden rounded-t-lg">
                        <?php if (!empty($m['image'])): ?>
                        <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>" class="w-full aspect-[4/5] object-cover">
                        <?php endif; ?>
                        <?php if (!empty($cardUrl)): ?>
                          <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <a href="<?php echo htmlspecialchars(safeUrl($cardUrl)); ?>" target="_blank" class="btn btn-primary">Maak afspraak</a>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="p-5 text-center">
                        <h4 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h4>
                        <p class="text-slate-600"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($hasPinned): ?>
        <aside class="space-y-6 lg:col-span-1 sticky top-32">
          <h2 class="text-3xl font-semibold mb-4 lg:hidden" style="font-family: var(--font-heading);">Belangrijke mededelingen</h2>
          <?php foreach ($pinnedList as $pin): ?>
          <div class="p-6 pinned-card">
            <h4 class="text-lg font-semibold mb-2"><?php echo htmlspecialchars($pin['title'] ?? ''); ?></h4>
            <div class="prose max-w-none text-sm"><?php echo $pin['text'] ?? ''; ?></div>
          </div>
          <?php endforeach; ?>
        </aside>
      <?php endif; ?>
    </div>
  </div>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
