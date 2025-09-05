<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

$contentFile = defined('CONTENT_FILE') ? CONTENT_FILE : (defined('DATA_DIR') ? DATA_DIR . '/content.json' : __DIR__ . '/data/content.json');
$siteContent = loadJsonFile($contentFile);
$settings = $siteContent['settings'] ?? [];
$phones = isset($settings['footer_phones']) && is_array($settings['footer_phones']) ? $settings['footer_phones'] : [];
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
<main class="py-12">
  <div class="container mx-auto px-6">
    <h1 class="text-4xl font-semibold mb-8" style="font-family: var(--font-heading);">Nuttige telefoonnummers</h1>

    <?php if (empty($phones)): ?>
      <p class="text-slate-600">Nog geen telefoonnummers beschikbaar.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table id="phones-table" class="min-w-full bg-white rounded-xl overflow-hidden shadow">
        <thead class="bg-slate-100">
          <tr>
            <th class="text-left px-4 py-3 cursor-pointer" data-sort="label">Naam</th>
            <th class="text-left px-4 py-3 cursor-pointer" data-sort="tel">Telefoon</th>
            <th class="text-left px-4 py-3">Omschrijving</th>
            <th class="text-left px-4 py-3">Link</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($phones as $ph): ?>
          <tr class="border-t border-slate-200">
            <td class="px-4 py-3 font-medium" data-key="label"><?php echo htmlspecialchars($ph['label'] ?? ''); ?></td>
            <td class="px-4 py-3" data-key="tel">
              <?php if (!empty($ph['tel'])): ?>
                <a href="tel:<?php echo htmlspecialchars($ph['tel']); ?>" class="text-blue-600"><?php echo htmlspecialchars($ph['tel']); ?></a>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3"><?php echo htmlspecialchars($ph['desc'] ?? ''); ?></td>
            <td class="px-4 py-3">
              <?php if (!empty($ph['url'])): ?>
                <a href="<?php echo htmlspecialchars(safeUrl($ph['url'])); ?>" class="text-blue-600" target="_blank"><?php echo htmlspecialchars($ph['url']); ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>
<?php include TEMPLATES_DIR . '/footer.php'; ?>
<script>
// Simple sort for phones table on Naam and Telefoon
(function(){
  const table = document.getElementById('phones-table');
  if (!table) return;
  const getCellText = (tr, key) => {
    const cell = tr.querySelector(`[data-key="${key}"]`);
    if (!cell) return '';
    return (cell.textContent || '').trim().toLowerCase();
  };
  let sortState = { key: 'label', dir: 1 };
  table.querySelectorAll('thead th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
      const key = th.getAttribute('data-sort');
      sortState.dir = (sortState.key === key) ? -sortState.dir : 1;
      sortState.key = key;
      const rows = Array.from(table.tBodies[0].rows);
      rows.sort((a,b) => {
        const va = getCellText(a, key);
        const vb = getCellText(b, key);
        if (va < vb) return -1 * sortState.dir;
        if (va > vb) return  1 * sortState.dir;
        return 0;
      });
      rows.forEach(r => table.tBodies[0].appendChild(r));
    });
  });
})();
</script>
</body>
</html>

