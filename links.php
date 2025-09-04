<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

$linksFile = defined('LINKS_FILE') ? LINKS_FILE : (defined('DATA_DIR') ? DATA_DIR . '/links/links.json' : __DIR__ . '/data/links/links.json');
$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (defined('DATA_DIR') ? DATA_DIR . '/content.json' : __DIR__ . '/data/content.json');

$siteContent = loadJsonFile($contentFile);
$linksData = loadJsonFile($linksFile);

$metaTitle = 'Nuttige links - Groepspraktijk Elewijt';
$metaDescription = $siteContent['meta_description'] ?? '';
$hero = $linksData['hero'] ?? ['title' => '', 'image' => '', 'webp' => ''];
$items = isset($linksData['items']) && is_array($linksData['items']) ? $linksData['items'] : [];
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
<?php $page = 'links'; include TEMPLATES_DIR . '/header.php'; ?>

<main>
  <section class="h-64 bg-cover bg-center flex items-center justify-center" style="background-image: url('<?php echo htmlspecialchars($hero['image'] ?? ''); ?>');">
    <h1 class="text-4xl font-semibold text-white drop-shadow" style="font-family: var(--font-heading);">
      <?php echo htmlspecialchars($hero['title'] ?? 'Nuttige links'); ?>
    </h1>
  </section>
  <section class="py-12">
    <div class="container mx-auto px-6">
      <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <?php if (empty($items)): ?>
            <p class="text-slate-600">Nog geen links beschikbaar.</p>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-xl overflow-hidden shadow">
              <thead class="bg-slate-100">
                <tr>
                  <th class="text-left px-4 py-3">Naam</th>
                  <th class="text-left px-4 py-3">Link/Telefoon</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr class="border-t border-slate-200">
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($it['label'] ?? ''); ?></td>
                    <td class="px-4 py-3">
                  <?php if (!empty($it['url'])): ?>
                    <a href="<?php echo htmlspecialchars(safeUrl($it['url'])); ?>" class="text-blue-600" target="_blank"><?php echo htmlspecialchars(safeUrl($it['url'])); ?></a>
                      <?php elseif (!empty($it['tel'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($it['tel']); ?>" class="text-blue-600"><?php echo htmlspecialchars($it['tel']); ?></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <?php
        $pinned = ($siteContent['pinned'] ?? []);
        $pinnedList = [];
        foreach ($pinned as $pin) {
          $scope = $pin['scope'] ?? [];
          if (!is_array($scope)) $scope = ($scope==='all') ? ['all'] : [];
          if (in_array('all',$scope) || in_array('links',$scope)) $pinnedList[] = $pin;
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
  </section>
</main>

<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
