@php
    $requested = request()->path();
    $requested = $requested && $requested !== '/' ? '/' . ltrim($requested, '/') : '/';
    $isLoggedIn = auth()->check();
    $backHref = $isLoggedIn ? route('dashboard') : route('login');
    $backLabel = $isLoggedIn ? 'Back to Dashboard' : 'Go to Login';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Leave System</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('pictures/DEPED.jpg') }}">
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

            <div class="error-url-chip" title="Requested path">{{ $requested }}</div>

            <div class="error-actions">
                <a href="{{ $backHref }}" class="btn btn-primary">{{ $backLabel }}</a>
                <button type="button" class="btn btn-secondary" onclick="history.length > 1 ? history.back() : window.location.href='{{ $backHref }}'">Go Back</button>
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
