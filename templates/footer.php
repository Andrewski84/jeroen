<?php
/**
 * Global site footer
 *
 * Expects $instagramUrl variable (optional).
 */
?>
<footer class="py-10" style="background-color: var(--surface);">
    <div class="container mx-auto px-6 text-center text-sm">
        <?php if (!empty($instagramUrl)): ?>
        <a href="<?php echo htmlspecialchars($instagramUrl); ?>" target="_blank" class="text-2xl mb-4 inline-block">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-7 h-7"><path d="M7.75 2h8.5A5.76 5.76 0 0 1 22 7.75v8.5A5.76 5.76 0 0 1 16.25 22h-8.5A5.76 5.76 0 0 1 2 16.25v-8.5A5.76 5.76 0 0 1 7.75 2zm0 1.5A4.26 4.26 0 0 0 3.5 7.75v8.5A4.26 4.26 0 0 0 7.75 20.5h8.5a4.26 4.26 0 0 0 4.25-4.25v-8.5A4.26 4.26 0 0 0 16.25 3.5h-8.5z" /><path d="M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 1.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.5 6.75a1.25 1.25 0 1 1-2.5 0 1.25 1.25 0 0 1 2.5 0z" /></svg>
        </a>
        <?php endif; ?>
        <p>&copy; <?php echo date('Y'); ?> Andrew Smeets Fotografie. All rights reserved.</p>
    </div>
</footer>
