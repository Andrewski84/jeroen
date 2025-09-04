<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

function loadJsonSafe($file) {
    if (file_exists($file)) { return json_decode(file_get_contents($file), true) ?: []; }
    return [];
}

$siteContent = loadJsonSafe(CONTENT_FILE);
// Respect visibility setting; redirect to home if hidden
if (isset($siteContent['pages']['pricing']['visible']) && !$siteContent['pages']['pricing']['visible']) {
    header('Location: index.php');
    exit;
}
$pricingData = loadJsonSafe(PRICING_FILE);
$items = $pricingData['items'] ?? [];

$page = 'pricing';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarieven - Andrew</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body class="antialiased flex flex-col min-h-screen">
    <?php include TEMPLATES_DIR . '/header.php'; ?>
    <main class="container mx-auto px-6 py-16 flex-grow">
        <div class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-serif mb-3">Tarieven</h1>
            <p class="max-w-2xl mx-auto text-lg text-slate-300">Een overzicht van populaire pakketten. Op maat nodig? Neem gerust contact op.</p>
        </div>

        <?php if (empty($items)): ?>
            <p class="text-center text-slate-400">Er zijn nog geen tarieven toegevoegd.</p>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($items as $it): ?>
                <div class="rounded-xl overflow-hidden border border-[var(--border)] bg-[var(--surface)] shadow-lg h-full flex flex-col">
                    <?php if (!empty($it['image'])): ?>
                    <img src="<?php echo htmlspecialchars(toPublicPath($it['image'])); ?>" alt="<?php echo htmlspecialchars($it['title'] ?? ''); ?>" class="w-full h-48 object-cover">
                    <?php endif; ?>
                    <div class="p-5 flex-1 flex flex-col">
                        <div class="flex items-baseline justify-between mb-2">
                            <h3 class="font-serif text-2xl"><?php echo htmlspecialchars($it['title'] ?? ''); ?></h3>
                            <?php if (!empty($it['price'])): ?>
                                <span class="text-lg font-medium text-white bg-white/10 rounded-full px-3 py-1 border border-[var(--border)]"><?php echo htmlspecialchars($it['price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($it['description'])): ?>
                        <p class="text-slate-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($it['description'])); ?></p>
                        <?php endif; ?>
                        <div class="mt-auto pt-4">
                            <button class="btn btn-primary" onclick="openPricingForm('<?php echo htmlspecialchars(addslashes($it['title'] ?? '')); ?>')">Boek</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <?php include TEMPLATES_DIR . '/footer.php'; ?>
    
    <!-- Boekingsformulier Modal -->
    <div id="pricing-modal" class="fixed inset-0 z-[300] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/60"></div>
        <div class="relative bg-[var(--surface)] border border-[var(--border)] rounded-xl shadow-2xl w-11/12 max-w-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-2xl font-serif">Boekingsaanvraag</h3>
                <button id="pricing-modal-close" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
            </div>
            <form id="pricing_form" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="contact_form_json">
                <input type="hidden" name="pricing_title" id="pricing_title" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1">Naam*</label>
                        <input type="text" name="name" required class="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white/20">
                    </div>
                    <div>
                        <label class="block mb-1">E-mail*</label>
                        <input type="email" name="email" required class="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white/20">
                    </div>
                </div>
                <div>
                    <label class="block mb-1">Telefoon</label>
                    <input type="tel" name="phone" class="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white/20">
                </div>
                <div>
                    <label class="block mb-1">Bericht (optioneel)</label>
                    <textarea name="message" rows="4" class="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-white/20"></textarea>
                </div>
                <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                    <label for="address2">Adresregel 2</label>
                    <input id="address2" name="address2" type="text" value="" autocomplete="off" tabindex="-1">
                </div>
                <div class="text-right">
                    <button type="submit" class="btn btn-primary">Versturen</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bedankt Modal -->
    <div id="pricing-thanks" class="fixed inset-0 z-[350] hidden items-center justify-center">
        <div class="absolute inset-0 bg-black/60"></div>
        <div class="relative bg-[var(--surface)] border border-[var(--border)] rounded-xl shadow-2xl w-11/12 max-w-md p-6 text-center">
            <h3 class="text-xl font-semibold mb-2" style="font-family: var(--font-heading);">Bedankt voor je interesse</h3>
            <p class="text-slate-300 mb-4">We nemen zo snel mogelijk contact met je op.</p>
            <button id="pricing-thanks-close" class="btn btn-secondary">Sluiten</button>
        </div>
    </div>

    <script>
    function openPricingForm(title) {
        const m = document.getElementById('pricing-modal');
        document.getElementById('pricing_title').value = title || '';
        m.classList.remove('hidden');
        m.classList.add('flex');
    }
    (function(){
        const modal = document.getElementById('pricing-modal');
        const closeBtn = document.getElementById('pricing-modal-close');
        closeBtn?.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); });
        modal.addEventListener('click', (e) => { if (e.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } });
        const form = document.getElementById('pricing_form');
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            try {
                const res = await fetch('save.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
                const data = await res.json();
                if (data && data.status === 'ok') {
                    modal.classList.add('hidden'); modal.classList.remove('flex');
                    const thanks = document.getElementById('pricing-thanks');
                    thanks.classList.remove('hidden'); thanks.classList.add('flex');
                } else {
                    showToast('Er ging iets mis bij het verzenden. Probeer later opnieuw.', false);
                }
            } catch (err) {
                showToast('Er ging iets mis bij het verzenden. Probeer later opnieuw.', false);
            }
        });
        document.getElementById('pricing-thanks-close')?.addEventListener('click', () => {
            const thanks = document.getElementById('pricing-thanks');
            thanks.classList.add('hidden'); thanks.classList.remove('flex');
        });
    })();

    function showToast(msg, ok) {
        let toast = document.getElementById('toast-popup');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-popup';
            toast.className = 'fixed bottom-5 right-5 bg-gray-800 text-white px-6 py-3 rounded-full shadow-lg opacity-0 transform translate-y-4 z-[1000]';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.backgroundColor = ok ? '#22c55e' : '#ef4444';
        toast.classList.remove('opacity-0','translate-y-4');
        setTimeout(function(){ toast.classList.add('opacity-0','translate-y-4'); }, 2500);
    }
    </script>
</body>
</html>
