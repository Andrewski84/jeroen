<?php
/**
 * Global site footer for Groepspraktijk Elewijt
 */
// Load settings from content file
$cfg = [];
if (defined('CONTENT_FILE') && file_exists(CONTENT_FILE)) {
    $cfg = json_decode(file_get_contents(CONTENT_FILE), true) ?: [];
}
$settings = $cfg['settings'] ?? [];
$appointmentUrl = $settings['appointment_url'] ?? '';
$address1 = $settings['address_line_1'] ?? '';
$address2 = $settings['address_line_2'] ?? '';
$phone = $settings['phone'] ?? '';
$usefulPhones = isset($settings['footer_phones']) && is_array($settings['footer_phones']) ? $settings['footer_phones'] : [];
$mapEmbed = $settings['map_embed'] ?? '';
?>
<footer class="site-footer">
  <div class="container mx-auto px-6">
    <div class="flex flex-col lg:flex-row gap-10">
      <div class="flex-1">
        <?php if (!empty($appointmentUrl)): ?>
          <a href="<?php echo htmlspecialchars($appointmentUrl); ?>" target="_blank" class="btn btn-primary mb-6">Maak een afspraak</a>
        <?php endif; ?>
        <h3 class="text-xl font-semibold mb-2" style="font-family: var(--font-heading);">Groepspraktijk Elewijt</h3>
        <?php if ($address1): ?><p class="text-slate-700"><?php echo htmlspecialchars($address1); ?></p><?php endif; ?>
        <?php if ($address2): ?><p class="text-slate-700"><?php echo htmlspecialchars($address2); ?></p><?php endif; ?>
        <?php if ($phone): ?><p class="mt-2">Tel: <a class="text-blue-600" href="tel:<?php echo htmlspecialchars($phone); ?>"><?php echo htmlspecialchars($phone); ?></a></p><?php endif; ?>

        <!-- Telefoonnummers staan niet langer in de footer; zie aparte pagina -->
      </div>
      <div class="flex-1">
        <?php if (!empty($mapEmbed)): ?>
          <div class="rounded-xl overflow-hidden shadow bg-white map-embed h-80">
            <?php echo $mapEmbed; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <p class="text-center text-sm text-slate-500 mt-8">&copy; <?php echo date('Y'); ?> Groepspraktijk Elewijt</p>
  </div>
</footer>
