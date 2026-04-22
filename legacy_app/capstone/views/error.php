<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../helpers/Auth.php';
Auth::sendNoCacheHeaders();

$requested = trim((string)($_GET['requested'] ?? ''));
Auth::sendNoCacheHeaders();

$requested = trim((string)($_GET['requested'] ?? ''));
$requested = $requested !== '' ? '/' . ltrim($requested, '/') : '/';
$isLoggedIn = !empty($_SESSION['user_id']);
$backHref = $isLoggedIn ? Auth::appUrl('dashboard') : Auth::appUrl('login');
$backLabel = $isLoggedIn ? 'Back to Dashboard' : 'Go to Login';
$stylesHref = Auth::appUrl('assets/css/styles.css');
$iconHref = Auth::appUrl('pictures/DEPED.jpg');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Leave System</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($stylesHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        html, body { min-height: 100vh; }
        body.error-page-body {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 24px;
        }
        body.error-page-body .error-shell {
            margin: 0 auto;
        }
    </style>
</head>
<body class="error-page-body">
    <div class="error-orb error-orb-a"></div>
    <div class="error-orb error-orb-b"></div>

    <main class="error-shell" id="errorShell">
        <section class="error-card">
            <span class="error-kicker">Error</span>
            <h1>The page you entered is not available.</h1>
            <p class="error-copy">The URL under this site does not exist or cannot be opened from here. Please go back to a valid page and continue from there.</p>

            <div class="error-url-chip" title="Requested path"><?= htmlspecialchars($requested, ENT_QUOTES, 'UTF-8'); ?></div>

            <div class="error-actions">
                <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><?= htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></a>
                <button type="button" class="btn btn-secondary" onclick="history.length > 1 ? history.back() : window.location.href='<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>'">Go Back</button>
            </div>
        </section>
    </main>

    <script>
    (function () {
        const shell = document.getElementById('errorShell');
        const orbs = document.querySelectorAll('.error-orb');
        if (!shell || !orbs.length) return;

        window.addEventListener('mousemove', function (event) {
            const x = (event.clientX / window.innerWidth) - 0.5;
            const y = (event.clientY / window.innerHeight) - 0.5;
            shell.style.transform = 'translate(' + (x * 8) + 'px,' + (y * 8) + 'px)';
            orbs.forEach(function (orb, index) {
                const strength = index === 0 ? 20 : 12;
                orb.style.transform = 'translate(' + (x * strength) + 'px,' + (y * strength) + 'px)';
            });
        });
    })();
    </script>
</body>
</html>
