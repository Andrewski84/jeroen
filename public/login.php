<?php
session_start();
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: /admin/admin.php');
    exit;
}
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Andrew Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <?php /* Het dynamische kleurenthema is verwijderd. Voorheen werd hier
       theme_loader.php ingeladen om CSS-variabelen te definiÃ«ren.
       De kleuren zijn nu statisch via style.css gedefinieerd. */ ?>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-sm p-8 bg-white rounded-lg shadow-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-6">Beheer</h1>
        <form action="/includes/auth.php" method="POST">
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Wachtwoord</label>
                <input type="password" name="password" id="password" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring focus:ring-indigo-200" required>
            </div>
            <?php if ($error): ?>
                <p class="text-red-500 text-sm mb-4">Ongeldig wachtwoord. Probeer het opnieuw.</p>
            <?php endif; ?>
            <button type="submit" class="w-full bg-gray-700 text-white py-2 rounded-md hover:bg-gray-800 transition-colors">Inloggen</button>
        </form>
        <div class="text-center mt-4">
            <a href="#" onclick="alert('Deze functie is nog niet geÃ¯mplementeerd.'); return false;" class="text-sm text-gray-600 hover:underline">Wachtwoord vergeten?</a>
        </div>
    </div>
<!-- inject password reset helper -->
<script>
(function(){
  const container = document.querySelector('.text-center.mt-4');
  const link = container ? container.querySelector('a[href="#"]') : null;
  if (!link) return;
  try { link.onclick = null; } catch (e) {}
  let status = document.getElementById('forgot-status');
  if (!status) {
    status = document.createElement('p');
    status.id = 'forgot-status';
    status.className = 'text-sm mt-2';
    container.appendChild(status);
  }
  link.addEventListener('click', function(e){
    e.preventDefault();
    status.textContent = 'Bezig met verzenden...';
    status.className = 'text-sm text-gray-600 mt-2';
    fetch('/admin/save.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'action=request_password_reset' })
      .then(r => r.json()).then(data => {
        if (data.status === 'success') {
          status.textContent = 'Er werd een resetlink naar de admin gemaild.';
          status.className = 'text-sm text-green-600 mt-2';
        } else {
          status.textContent = 'Verzenden mislukt. Probeer later opnieuw.';
          status.className = 'text-sm text-red-600 mt-2';
        }
      }).catch(() => { status.textContent = 'Netwerkfout.'; status.className = 'text-sm text-red-600 mt-2'; });
  });
})();
</script>
</body>
</html>

