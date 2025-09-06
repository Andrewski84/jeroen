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
      <?php if (empty($hero['image'])): ?>
        <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Nuttige links</h1>
      <?php endif; ?>

      <?php if (empty($items)): ?>
        <p class="text-slate-600">Nog geen links beschikbaar.</p>
      <?php else: ?>
      <div class="overflow-x-auto">
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
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
</body>
</html>
