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
$hero = $practiceData['hero'] ?? null;

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
<div class="main-content">
<main>
    <?php if (!empty($hero['image'])): ?>
    <section class="page-hero" style="background-image: url('<?php echo htmlspecialchars($hero['image']); ?>');">
        <h1 class="text-4xl md:text-5xl font-semibold">
            <?php echo htmlspecialchars($pageData['title'] ?? 'Praktijkinfo'); ?>
        </h1>
    </section>
    <?php endif; ?>

  <section class="py-16">
    <div class="container mx-auto px-6">
        <?php if (empty($hero['image'])): ?>
            <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">
              <?php echo htmlspecialchars($pageData['title'] ?? 'Praktijkinfo'); ?>
            </h1>
        <?php endif; ?>

        <?php if (!$pageData): ?>
          <p class="text-slate-600 mb-6">Deze pagina bestaat niet. Kies een pagina:</p>
          <ul class="list-disc pl-6">
            <?php foreach ($pages as $s => $pd): ?>
              <li><a class="text-blue-600" href="practice.php?slug=<?php echo urlencode($s); ?>"><?php echo htmlspecialchars($pd['title'] ?? $s); ?></a></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <?php
            $cards = isset($pageData['cards']) && is_array($pageData['cards']) ? $pageData['cards'] : [];
            $pinned = ($siteContent['pinned'] ?? []);
            $pinnedList = array_filter($pinned, function($pin) {
                $scope = $pin['scope'] ?? [];
                if (!is_array($scope)) $scope = ($scope === 'all') ? ['all'] : [];
                return in_array('all', $scope) || in_array('practice', $scope);
            });
            $hasPinned = !empty($pinnedList);
          ?>
          <div class="grid gap-8 <?php echo $hasPinned ? 'lg:grid-cols-3' : 'lg:grid-cols-1'; ?> items-start">
            <div class="<?php echo $hasPinned ? 'lg:col-span-2' : ''; ?>">
              <?php if (empty($cards)): ?>
                <p class="text-slate-600">Nog geen inhoud beschikbaar.</p>
              <?php else: ?>
              <div class="grid gap-6">
                <?php foreach ($cards as $card): ?>
                  <div class="p-8 practice-card">
                    <div class="prose max-w-none">
                      <?php echo $card['html'] ?? ''; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
            <?php if ($hasPinned): ?>
            <aside class="space-y-6 lg:col-span-1 sticky top-32">
              <h3 class="text-2xl font-semibold">Belangrijke mededelingen</h3>
              <?php foreach ($pinnedList as $pin): ?>
              <div class="p-6 pinned-card">
                <h4 class="text-lg font-semibold mb-2"><?php echo htmlspecialchars($pin['title'] ?? ''); ?></h4>
                <div class="prose max-w-none text-sm"><?php echo $pin['text'] ?? ''; ?></div>
              </div>
              <?php endforeach; ?>
            </aside>
            <?php endif; ?>
          </div>
        <?php endif; ?>
    </div>
  </section>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
