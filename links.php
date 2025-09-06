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
<div class="main-content">
<main>
  <?php if (!empty($hero['image'])): ?>
  <section class="page-hero" style="background-image: url('<?php echo htmlspecialchars($hero['image']); ?>');">
      <h1 class="text-4xl md:text-5xl font-semibold">
        <?php echo htmlspecialchars($hero['title'] ?? 'Nuttige links'); ?>
      </h1>
  </section>
  <?php endif; ?>

  <section class="py-16">
    <div class="container mx-auto px-6">
      <?php
        $pinned = ($siteContent['pinned'] ?? []);
        $pinnedList = array_filter($pinned, function($pin) {
            $scope = $pin['scope'] ?? [];
            if (!is_array($scope)) $scope = ($scope === 'all') ? ['all'] : [];
            return in_array('all', $scope) || in_array('links', $scope);
        });
        $hasPinned = !empty($pinnedList);
      ?>
      <div class="grid gap-8 <?php echo $hasPinned ? 'lg:grid-cols-3' : 'lg:grid-cols-1'; ?> items-start">
        <div class="<?php echo $hasPinned ? 'lg:col-span-2' : ''; ?>">
          <?php if (empty($hero['image'])): ?>
            <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Nuttige links</h1>
          <?php endif; ?>

          <?php if (empty($items)): ?>
            <p class="text-slate-600">Nog geen links beschikbaar.</p>
          <?php else: ?>
          <!-- Desktop/tablet: tabelweergave -->
          <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full bg-white rounded-xl overflow-hidden shadow-lg">
              <thead class="bg-slate-100">
                <tr>
                  <th class="text-left px-6 py-4 font-semibold">Naam</th>
                  <th class="text-left px-6 py-4 font-semibold">Website</th>
                  <th class="text-left px-6 py-4 font-semibold">Telefoon</th>
                  <th class="text-left px-6 py-4 font-semibold">Categorie</th>
                  <th class="text-left px-6 py-4 font-semibold">Omschrijving</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <tr class="border-t border-slate-200 hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($it['label'] ?? ''); ?></td>
                    <td class="px-6 py-4">
                      <?php if (!empty($it['url'])): ?>
                        <a href="<?php echo htmlspecialchars(safeUrl($it['url'])); ?>" class="text-blue-600" target="_blank" rel="noopener">Bezoek website</a>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                      <?php if (!empty($it['tel'])): ?>
                        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $it['tel'])); ?>" class="text-blue-600 font-semibold"><?php echo htmlspecialchars($it['tel']); ?></a>
                      <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($it['category'] ?? ''); ?></td>
                    <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($it['description'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Mobiel: kaartweergave -->
          <div class="grid gap-4 md:hidden">
            <?php foreach ($items as $it): ?>
            <div class="mobile-table-card p-4">
              <h3 class="text-lg font-semibold mb-1"><?php echo htmlspecialchars($it['label'] ?? ''); ?></h3>
              <?php if (!empty($it['category'])): ?>
                <p class="text-slate-600 mb-2"><?php echo htmlspecialchars($it['category']); ?></p>
              <?php endif; ?>
              <?php if (!empty($it['description'])): ?>
                <p class="text-slate-600 mb-2"><?php echo htmlspecialchars($it['description']); ?></p>
              <?php endif; ?>
              <div class="flex flex-col gap-2 mt-2">
                <?php if (!empty($it['url'])): ?>
                  <a href="<?php echo htmlspecialchars(safeUrl($it['url'])); ?>" class="btn btn-secondary w-full text-center" target="_blank" rel="noopener">Bezoek website</a>
                <?php endif; ?>
                <?php if (!empty($it['tel'])): ?>
                  <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $it['tel'])); ?>" class="text-blue-600 font-semibold"><?php echo htmlspecialchars($it['tel']); ?></a>
                <?php endif; ?>
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
    </div>
  </section>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
