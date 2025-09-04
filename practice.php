<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

$practiceFile = defined('PRACTICE_FILE') ? PRACTICE_FILE : (defined('DATA_DIR') ? DATA_DIR . '/practice/practice.json' : __DIR__ . '/data/practice/practice.json');
$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (defined('DATA_DIR') ? DATA_DIR . '/content.json' : __DIR__ . '/data/content.json');

$siteContent = loadJsonFile($contentFile);
$practiceData = loadJsonFile($practiceFile);
$pages = isset($practiceData['pages']) && is_array($practiceData['pages']) ? $practiceData['pages'] : [];
$slug = $_GET['slug'] ?? array_key_first($pages);
$pageData = ($slug && isset($pages[$slug])) ? $pages[$slug] : null;

$metaTitle = ($pageData['title'] ?? 'Praktijkinfo') . ' - Groepspraktijk Elewijt';
$metaDescription = $siteContent['meta_description'] ?? '';
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
<?php $page = 'practice'; include TEMPLATES_DIR . '/header.php'; ?>

<main class="py-16">
  <div class="container mx-auto px-6">
    <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">
      <?php echo htmlspecialchars($pageData['title'] ?? 'Praktijkinfo'); ?>
    </h1>
    <?php if (!$pageData): ?>
      <p class="text-slate-600 mb-6">Deze pagina bestaat niet. Kies een pagina:</p>
      <ul class="list-disc pl-6">
        <?php foreach ($pages as $s => $pd): ?>
          <li><a class="text-blue-600" href="practice.php?slug=<?php echo urlencode($s); ?>"><?php echo htmlspecialchars($pd['title'] ?? $s); ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?php $cards = isset($pageData['cards']) && is_array($pageData['cards']) ? $pageData['cards'] : []; ?>
      <div class="grid gap-8 lg:grid-cols-3 items-start">
        <div class="lg:col-span-2">
          <?php if (empty($cards)): ?>
            <p class="text-slate-600">Nog geen inhoud beschikbaar.</p>
          <?php else: ?>
          <div class="grid gap-6 md:grid-cols-2">
            <?php foreach ($cards as $card): ?>
              <div class="rounded-xl p-6 practice-card">
                <div class="prose max-w-none">
                  <?php echo $card['html'] ?? ''; ?>
                </div>
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
          if (in_array('all',$scope) || in_array('practice',$scope)) $pinnedList[] = $pin;
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
    <?php endif; ?>
  </div>
<?php
// Load pinned messages for practice
$pinned = ($siteContent['pinned'] ?? []);
$pinnedList = [];
foreach ($pinned as $pin) {
  $scope = $pin['scope'] ?? [];
  if (!is_array($scope)) $scope = ($scope==='all') ? ['all'] : [];
  if (in_array('all',$scope) || in_array('practice',$scope)) $pinnedList[] = $pin;
}
?>
<?php if (!empty($pinnedList)): ?>
<section class="py-10">
  <div class="container mx-auto px-6">
    <div class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2"></div>
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
</section>
<?php endif; ?>

</main>

<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
