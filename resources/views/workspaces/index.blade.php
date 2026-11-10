<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>منطقة المستخدمين المميزين | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/justm/bootstrap-rtl.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/justm/fontawesome.min.css') }}">
    <style>
        :root{--navy:#101d2d;--navy2:#172b40;--gold:#d7a94b;--muted:#aebdca}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:linear-gradient(135deg,rgba(9,22,37,.97),rgba(18,43,64,.96)),radial-gradient(circle at 15% 20%,#35516b,transparent 40%);font-family:Tahoma,Arial,sans-serif;color:#fff}
        .topbar{height:76px;position:sticky;top:0;z-index:5;background:rgba(8,18,30,.92);border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:space-between;padding:0 clamp(18px,6vw,84px);backdrop-filter:blur(10px)}
        .brand{font-size:22px;font-weight:700;letter-spacing:.3px;color:#fff;text-decoration:none}.brand span{color:var(--gold)}
        .logout{border:1px solid rgba(255,255,255,.3);border-radius:4px;color:#fff;padding:9px 16px;background:transparent;font-size:13px}
        .hero{max-width:1120px;margin:0 auto;padding:76px 22px 60px;text-align:center}
        .eyebrow{color:var(--gold);font-size:12px;letter-spacing:2px;margin-bottom:15px}
        h1{font-size:clamp(27px,4vw,42px);font-weight:700;margin:0 0 15px}
        .intro{color:var(--muted);font-size:15px;line-height:2;margin:0 auto 46px}
        .cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;text-align:right}
        .workspace-card{min-height:205px;padding:27px 23px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.13);border-radius:7px;color:#fff;text-decoration:none;display:flex;flex-direction:column;transition:transform .22s ease,border-color .22s ease,background .22s ease}
        .workspace-card:hover{transform:translateY(-7px);border-color:var(--gold);background:rgba(255,255,255,.1);color:#fff;text-decoration:none}
        .workspace-icon{width:54px;height:54px;display:grid;place-items:center;border-radius:50%;background:rgba(215,169,75,.14);color:var(--gold);font-size:23px;margin-bottom:22px}
        .workspace-card h2{font-size:17px;font-weight:700;margin:0 0 10px}
        .workspace-card p{font-size:12px;line-height:1.9;color:var(--muted);margin:0 0 16px}
        .enter{margin-top:auto;color:var(--gold);font-size:12px;font-weight:700}
        .enter i{margin-right:6px}
        .footer{color:#8294a5;font-size:11px;margin-top:44px}
        @media(max-width:900px){.cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:520px){.topbar{height:64px;padding:0 16px}.brand{font-size:18px}.hero{padding:54px 16px 36px}.cards{grid-template-columns:1fr;gap:13px}.workspace-card{min-height:170px;padding:22px}.workspace-icon{margin-bottom:15px}}
        @media(prefers-reduced-motion:reduce){.workspace-card{transition:none}.workspace-card:hover{transform:none}}
    </style>
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('home') }}">Just-M<span>.</span></a>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit"><i class="fas fa-sign-out-alt ml-1"></i> تسجيل الخروج</button></form>
</header>
<main class="hero">
    <div class="eyebrow">JUST-M WORKSPACE</div>
    <h1>منطقة المستخدمين المميزين</h1>
    <p class="intro">من فضلك اختر وظيفة من الوظائف التالية</p>
    <section class="cards" aria-label="مساحات العمل">
        @foreach($workspaces as $key => $workspace)
            <a class="workspace-card" href="{{ route('workspaces.enter', ['workspace' => $key]) }}">
                <span class="workspace-icon"><i class="{{ $workspace['icon'] }}" aria-hidden="true"></i></span>
                <h2>{{ $workspace['title'] }}</h2>
                <p>{{ $workspace['description'] }}</p>
                <span class="enter">دخول <i class="fas fa-arrow-left" aria-hidden="true"></i></span>
            </a>
        @endforeach
    </section>
    <div class="footer">يتم عرض الوظائف المسندة إلى حسابك فقط</div>
</main>
</body>
</html>
