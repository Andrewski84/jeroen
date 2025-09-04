<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

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

<main class="py-16">
  <div class="container mx-auto px-6">
    <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Team</h1>
    <div class="grid gap-8 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <?php if (empty($members)): ?>
          <p class="text-slate-600">Er zijn nog geen teamleden toegevoegd.</p>
        <?php else: ?>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-2">
          <?php foreach ($members as $m): ?>
            <?php $anchor = 'm-' . (!empty($m['id']) ? preg_replace('/[^a-zA-Z0-9_-]/','', $m['id']) : substr(md5(($m['name'] ?? '') . ($m['role'] ?? '')),0,8)); ?>
            <article id="<?php echo htmlspecialchars($anchor); ?>" class="rounded-xl overflow-hidden shadow-md bg-white">
              <?php if (!empty($m['image'])): ?>
              <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Teamlid'); ?>" class="w-full h-56 object-cover">
              <?php endif; ?>
              <div class="p-5">
                <h2 class="text-xl font-semibold mb-1"><?php echo htmlspecialchars($m['name'] ?? ''); ?></h2>
                <p class="text-slate-600 mb-4"><?php echo htmlspecialchars($m['role'] ?? ''); ?></p>
            <?php $url = $m['appointment_url'] ?? $appointmentUrl; if (!empty($url)): ?>
                  <a href="<?php echo htmlspecialchars(safeUrl($url)); ?>" target="_blank" class="btn btn-primary">Maak een afspraak</a>
            <?php endif; ?>
              </div>
            </article>
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
        if (in_array('all',$scope) || in_array('team',$scope)) $pinnedList[] = $pin;
      }
      ?>
      <aside class="space-y-4">
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

<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
