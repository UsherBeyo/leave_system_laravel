<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Leave System')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('pictures/DEPED.jpg') }}">
    <style>
        .flash-stack{position:fixed;top:18px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:10px}
        .flash{background:#111827;color:#fff;padding:14px 16px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.18);min-width:280px}
        .flash.success{background:#166534}.flash.error{background:#991b1b}.flash.warning{background:#92400e}

        /* Native Laravel page compatibility styles, aligned with the original capstone design system. */
        .metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-bottom:24px}
        .metric-card,.section-card,.request-card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 4px 14px rgba(15,23,42,0.06);padding:24px}
        .metric-label{font-size:13px;color:var(--muted);margin-bottom:8px;font-weight:600}
        .metric-value{font-size:32px;font-weight:700;line-height:1.1;color:var(--text)}
        .metric-sub,.help-text{font-size:13px;color:var(--muted)}
        .clean-table{width:100%;border-collapse:collapse;min-width:560px}
        .clean-table th,.clean-table td{padding:12px 16px;text-align:left;border-bottom:1px solid var(--border)}
        .clean-table th{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
        .clean-table tbody tr:hover{background:var(--bg)}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
        .field{display:flex;flex-direction:column;gap:6px;min-width:0}
        .field label{font-size:14px;font-weight:600;color:var(--text)}
        .field input,.field select,.field textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:12px;background:#fff;color:var(--text);font-size:14px}
        .field textarea{min-height:110px}
        .rule-box{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;padding:16px}
        .danger-text{color:#b91c1c;font-size:13px}
        .inline-check{display:flex;align-items:center;gap:8px;line-height:1.45;color:var(--secondary-text)}
        .inline-check input{flex-shrink:0}
        .tab-links{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
        .tab-links a{padding:8px 14px;border-radius:999px;text-decoration:none;background:var(--bg);color:var(--text);border:1px solid var(--border)}
        .tab-links a.active,.tab-links a:hover{background:var(--primary);border-color:var(--primary);color:#fff}
        .request-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
        .request-actions input,.request-actions select{min-height:40px}
        .page-shell{max-width:1320px}
        .page-shell .btn-primary{margin-right:0}
        .page-shell .btn-secondary,.page-shell .btn-danger,.page-shell .btn-ghost{margin-right:0}
        .profile-menu form{margin:0}
        .profile-menu button.profile-menu-item{font:inherit;color:inherit}
        @media (max-width: 900px){
            .request-actions > *{flex:1 1 100%}
            .page-shell{padding-left:0;padding-right:0}
        }
    </style>
    @stack('head')
</head>
<body class="@yield('body_class')">
<div class="flash-stack">
@foreach(['success','error','warning'] as $msgType)
@if(session($msgType))<div class="flash {{ $msgType }}">{{ session($msgType) }}</div>@endif
@endforeach
@if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif
</div>
@auth
<div class="app-shell">
    @include('partials.header')
    @include('partials.sidebar')
    <main class="app-main">
        <div class="page-shell">
            @yield('content')
        </div>
    </main>
</div>
@else
    @yield('content')
@endauth
@stack('scripts')
</body>
</html>
