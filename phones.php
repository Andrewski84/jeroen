<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (defined('DATA_DIR') ? DATA_DIR . '/content.json' : __DIR__ . '/data/content.json');
$siteContent = loadJsonFile($contentFile);
$settings = $siteContent['settings'] ?? [];
$phones = isset($settings['footer_phones']) && is_array($settings['footer_phones']) ? $settings['footer_phones'] : [];
$hero = $siteContent['phones_hero'] ?? null;

$metaTitle = 'Nuttige telefoonnummers - Groepspraktijk Elewijt';
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
<?php $page = 'phones'; include TEMPLATES_DIR . '/header.php'; ?>
<div class="main-content">
<main>
    <?php if (!empty($hero['image'])): ?>
    <section class="page-hero" style="background-image: url('<?php echo htmlspecialchars($hero['image']); ?>');">
        <h1 class="text-4xl md:text-5xl font-semibold">
            <?php echo htmlspecialchars($hero['title'] ?? 'Nuttige telefoonnummers'); ?>
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
              return in_array('all', $scope) || in_array('phones', $scope);
          });
          $hasPinned = !empty($pinnedList);
        ?>
        <div class="grid gap-8 <?php echo $hasPinned ? 'lg:grid-cols-3' : 'lg:grid-cols-1'; ?> items-start">
            <div class="<?php echo $hasPinned ? 'lg:col-span-2' : ''; ?>">
                <?php if (empty($hero['image'])): ?>
                     <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Nuttige telefoonnummers</h1>
                <?php endif; ?>

                <?php if (empty($phones)): ?>
                  <p class="text-slate-600">Nog geen telefoonnummers beschikbaar.</p>
                <?php else: ?>
                <!-- Desktop/tablet: tabelweergave -->
                <div class="hidden md:block overflow-x-auto">
                  <table id="phones-table" class="min-w-full bg-white rounded-xl overflow-hidden shadow-lg">
                    <thead class="bg-slate-100">
                      <tr>
                        <th class="text-left px-6 py-4 font-semibold cursor-pointer" data-sort="label">Naam</th>
                        <th class="text-left px-6 py-4 font-semibold cursor-pointer" data-sort="tel">Telefoon</th>
                        <th class="text-left px-6 py-4 font-semibold">Omschrijving</th>
                        <th class="text-left px-6 py-4 font-semibold">Link</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($phones as $ph): ?>
                      <tr class="border-t border-slate-200 hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium" data-key="label"><?php echo htmlspecialchars($ph['label'] ?? ''); ?></td>
                        <td class="px-6 py-4" data-key="tel">
                          <?php if (!empty($ph['tel'])): ?>
                            <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $ph['tel'])); ?>" class="text-blue-600 font-semibold"><?php echo htmlspecialchars($ph['tel']); ?></a>
                          <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($ph['desc'] ?? ''); ?></td>
                        <td class="px-6 py-4">
                          <?php if (!empty($ph['url'])): ?>
                            <a href="<?php echo htmlspecialchars(safeUrl($ph['url'])); ?>" class="text-blue-600" target="_blank" rel="noopener">Bezoek website</a>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Mobiel: kaartweergave -->
                <div class="grid gap-4 md:hidden">
                  <?php foreach ($phones as $ph): ?>
                    <div class="mobile-table-card p-4">
                      <h3 class="text-lg font-semibold mb-2"><?php echo htmlspecialchars($ph['label'] ?? ''); ?></h3>
                      <?php if (!empty($ph['tel'])): ?>
                        <p class="mb-1">
                          <span class="text-slate-600">Telefoon: </span>
                          <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $ph['tel'])); ?>" class="text-blue-600 font-semibold"><?php echo htmlspecialchars($ph['tel']); ?></a>
                        </p>
                      <?php endif; ?>
                      <?php if (!empty($ph['desc'])): ?>
                        <p class="text-slate-600 mb-2"><?php echo htmlspecialchars($ph['desc']); ?></p>
                      <?php endif; ?>
                      <?php if (!empty($ph['url'])): ?>
                        <a href="<?php echo htmlspecialchars(safeUrl($ph['url'])); ?>" class="btn btn-secondary mt-2" target="_blank" rel="noopener">Bezoek website</a>
                      <?php endif; ?>
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
<script>
(function(){
  const table = document.getElementById('phones-table');
  if (!table) return;
  const getCellText = (tr, key) => (tr.querySelector(`[data-key="${key}"]`)?.textContent || '').trim().toLowerCase();
  let sortState = { key: 'label', dir: 1 };
  table.querySelectorAll('thead th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.getAttribute('data-sort');
      sortState.dir = (sortState.key === key) ? -sortState.dir : 1;
      sortState.key = key;
      Array.from(table.tBodies[0].rows)
        .sort((a,b) => getCellText(a, key).localeCompare(getCellText(b, key)) * sortState.dir)
        .forEach(r => table.tBodies[0].appendChild(r));
    });
  });
})();
</script>
</body>
</html>
