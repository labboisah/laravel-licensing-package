<!doctype html>
@php
    $productCode = strtoupper((string) $productCode);
    $isEdunexa = $productCode === 'EDUNEXA';
    $isMediflow = $productCode === 'MEDIFLOW';
    $brandName = config('kernelbridge-licensing.ui.brand_name') ?: ($isEdunexa ? 'EduNexa' : ($isMediflow ? 'MediFlow' : ($productCode ?: 'KernelBridge')));
    $brandSubtitle = config('kernelbridge-licensing.ui.brand_subtitle') ?: ($isEdunexa ? 'School management platform' : ($isMediflow ? 'Healthcare management' : 'Secure product activation'));
    $logoPath = $isEdunexa ? 'images/welcome-logo.png' : config('kernelbridge-licensing.ui.logo_path', 'vendor/kernelbridge-licensing/kernelbridge-logo.png');
    $heading = config('kernelbridge-licensing.ui.heading') ?: ($isEdunexa ? 'Your school workspace starts here.' : 'Your '. $brandName .' workspace starts here.');
    $description = config('kernelbridge-licensing.ui.description') ?: ('Activate '. $brandName .' with the licence issued for this installation. This computer will be registered and the modules included in your paid plan will be enabled.');
    $baseRouteName = (string) (config('kernelbridge-licensing.redirects.activation_route') ?: (config('kernelbridge-licensing.routes.name', 'kernelbridge.license.') . 'show'));
    $licenseActivateRoute = str_ends_with($baseRouteName, '.show') ? substr($baseRouteName, 0, -5).'.activate' : $baseRouteName.'.activate';
    $licenseVerifyRoute = str_ends_with($baseRouteName, '.show') ? substr($baseRouteName, 0, -5).'.verify' : $baseRouteName.'.verify';
    $licenseReprovisionRoute = str_ends_with($baseRouteName, '.show') ? substr($baseRouteName, 0, -5).'.reprovision' : $baseRouteName.'.reprovision';
    $licenseDeactivateRoute = str_ends_with($baseRouteName, '.show') ? substr($baseRouteName, 0, -5).'.deactivate' : $baseRouteName.'.deactivate';
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b3d6e">
    <title>Activate {{ $brandName }}</title>
    <style>
        :root { color-scheme: light; --navy: #0b3157; --blue: #0b3d6e; --teal: #0797a5; --ink: #17324d; --muted: #62758a; --line: #dce6ee; --paper: #fff; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; color: var(--ink); background: radial-gradient(ellipse at 7% 6%, rgba(7,151,165,.12), transparent 30rem), radial-gradient(ellipse at 96% 94%, rgba(11,61,110,.10), transparent 32rem), #f3f7fa; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .page { width: min(1120px, calc(100% - 40px)); min-height: 100vh; margin: auto; display: flex; flex-direction: column; }
        .topbar { min-height: 88px; display: flex; align-items: center; justify-content: space-between; gap: 18px; border-bottom: 1px solid rgba(11,61,110,.10); }
        .brand { display: inline-flex; align-items: center; gap: 12px; color: var(--navy); text-decoration: none; font-size: 17px; font-weight: 800; }
        .brand img { display: block; width: 48px; height: 48px; object-fit: contain; }
        .secure-label { display: inline-flex; align-items: center; gap: 8px; color: #42627d; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .secure-label svg { color: var(--teal); }
        .content { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) minmax(390px, 490px); align-items: center; gap: clamp(44px, 8vw, 112px); padding: 62px 0 76px; }
        .intro { max-width: 500px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; color: #087e8b; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .eyebrow-mark { width: 8px; height: 8px; border-radius: 50%; background: var(--teal); box-shadow: 0 0 0 5px rgba(7,151,165,.12); }
        h1 { margin: 20px 0 18px; color: var(--navy); font-size: clamp(38px, 5vw, 58px); font-weight: 800; line-height: 1.06; }
        .lead { max-width: 460px; margin: 0; color: #5c7187; font-size: 16px; line-height: 1.8; }
        .process { display: grid; gap: 17px; margin: 34px 0 0; padding: 0; list-style: none; }
        .process li { display: flex; align-items: center; gap: 13px; color: #35536e; font-size: 14px; font-weight: 650; }
        .process-num { display: grid; width: 30px; height: 30px; flex: 0 0 30px; place-items: center; border: 1px solid #c8dce7; border-radius: 50%; color: #087e8b; background: rgba(255,255,255,.72); font-size: 12px; }
        .card { position: relative; overflow: hidden; padding: 36px; border: 1px solid rgba(11,61,110,.09); border-radius: 18px; background: var(--paper); box-shadow: 0 24px 65px rgba(23,50,77,.12), 0 3px 12px rgba(23,50,77,.04); }
        .card::before { position: absolute; inset: 0 0 auto; height: 5px; background: linear-gradient(90deg, var(--teal), #35c4c2 55%, #86d8d0); content: ""; }
        .card-icon { display: grid; width: 54px; height: 54px; place-items: center; border-radius: 12px; color: #07818d; background: #e9f8f6; }
        .card h2 { margin: 22px 0 8px; color: var(--navy); font-size: 25px; line-height: 1.2; }
        .card-copy { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.65; }
        .notice { display: flex; gap: 11px; margin-top: 18px; padding: 13px 14px; border: 1px solid #f2d99d; border-radius: 8px; color: #795418; background: #fff9e9; font-size: 13px; line-height: 1.55; }
        .notice.error { border-color: #f1c6c3; color: #9a302d; background: #fff2f1; }
        .notice.success { border-color: #b7e3ce; color: #176246; background: #effaf4; }
        .field { margin-top: 21px; }
        .field label { display: block; margin-bottom: 8px; color: #294864; font-size: 13px; font-weight: 750; }
        .field input { display: block; width: 100%; min-height: 50px; padding: 0 14px; border: 1px solid #cbd8e2; border-radius: 8px; outline: none; color: var(--navy); background: #fbfdfe; font: inherit; font-size: 14px; transition: border-color .18s, box-shadow .18s, background .18s; }
        .field input:focus { border-color: var(--teal); background: #fff; box-shadow: 0 0 0 4px rgba(7,151,165,.13); }
        .submit { display: flex; width: 100%; min-height: 52px; align-items: center; justify-content: center; gap: 10px; margin-top: 19px; border: 0; border-radius: 8px; color: #fff; background: linear-gradient(100deg, #0b3d6e, #087f91); box-shadow: 0 8px 18px rgba(11,61,110,.19); cursor: pointer; font: inherit; font-size: 14px; font-weight: 750; }
        .privacy { display: flex; align-items: flex-start; gap: 9px; margin: 18px 0 0; color: #74879a; font-size: 11px; line-height: 1.55; }
        .privacy svg { flex: 0 0 15px; color: #66869c; }
        .footer { padding: 0 0 25px; color: #91a0ae; text-align: center; font-size: 11px; }
        @media (max-width: 820px) { .content { grid-template-columns: 1fr; gap: 34px; max-width: 560px; margin: 0 auto; padding: 44px 0 56px; } .intro { max-width: none; } h1 { max-width: 520px; font-size: clamp(38px, 10vw, 52px); } .process { margin-top: 25px; } }
        @media (max-width: 520px) { .page { width: min(100% - 28px, 500px); } .topbar { min-height: 72px; } .brand { font-size: 15px; } .brand img { width: 40px; height: 40px; } .secure-label { font-size: 10px; } .content { padding-top: 38px; } .card { padding: 27px 22px; } .lead { font-size: 15px; } }
        @media (prefers-reduced-motion: no-preference) { .card { animation: card-enter .4s ease-out both; } @keyframes card-enter { from { opacity: 0; transform: translateY(9px); } to { opacity: 1; transform: translateY(0); } } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ $brandName }} home">
                @if (config('kernelbridge-licensing.ui.show_logo', true))
                    <img src="{{ asset($logoPath) }}" alt="{{ config('kernelbridge-licensing.ui.logo_alt') ?: $brandName }}">
                @endif
                <span>{{ $brandName }}<small style="display:block;color:#698197;font-size:10px;font-weight:700;margin-top:3px">{{ $brandSubtitle }}</small></span>
            </a>
            <span class="secure-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3m-11 0h12a2 2 0 0 1 2 2v8H4v-8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 14v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>Secure activation</span>
        </header>
        <main class="content">
            <section class="intro" aria-labelledby="page-title">
                <span class="eyebrow"><span class="eyebrow-mark" aria-hidden="true"></span>{{ $isEdunexa ? 'School platform setup' : $brandSubtitle }}</span>
                <h1 id="page-title">{{ $heading }}</h1>
                <p class="lead">{{ $description }}</p>
                <ol class="process" aria-label="Activation steps">
                    <li><span class="process-num">01</span>Enter your private activation code</li>
                    <li><span class="process-num">02</span>Verify this computer with KernelBridge</li>
                    <li><span class="process-num">03</span>Continue to your {{ $brandName }} workspace</li>
                </ol>
            </section>
            <section class="card" aria-labelledby="activate-title">
                <div class="card-icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M12 3 19 6v5c0 4.7-3 8-7 10-4-2-7-5.3-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                <h2 id="activate-title">{{ $hasUsableLicense ? 'License status' : 'Activate your licence' }}</h2>
                <p class="card-copy">{{ $hasUsableLicense ? 'This installation is connected to KernelBridge.' : 'Use the activation code provided after your invoice was paid.' }}</p>
                @if (session('kernelbridge_license_status'))<div class="notice success" role="status">{{ session('kernelbridge_license_status') }}</div>@endif
                @if (! $hasUsableLicense)<div class="notice" role="status">{{ $activationMessage }}</div>@endif
                @if ($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
                @if ($hasUsableLicense)
                    <div class="field"><label>Product</label><input value="{{ $brandName }}" readonly></div>
                    <div class="field"><label>License status</label><input value="{{ ucfirst($state->status) }}" readonly></div>
                    <form method="POST" action="{{ route($licenseVerifyRoute) }}">@csrf<button class="submit" type="submit">Verify this installation</button></form>
                    @if (config('kernelbridge-licensing.ui.show_reprovision_button', false))
                        <form method="POST" action="{{ route($licenseReprovisionRoute) }}">@csrf<button class="submit" type="submit" style="background:#0d6d5d; margin-top: 10px;">Re-provision deployment profile</button></form>
                    @endif
                    <form method="POST" action="{{ route($licenseDeactivateRoute) }}">@csrf @method('delete')<button class="submit" type="submit" style="background:#9a302d">Deactivate licence</button></form>
                @else
                    <form method="POST" action="{{ route($licenseActivateRoute) }}">
                        @csrf
                        <div class="field"><label for="license_key">Activation licence code</label><input id="license_key" name="license_key" value="{{ old('license_key') }}" required autocomplete="off" spellcheck="false" placeholder="Paste your private activation code"></div>
                        <div class="field"><label for="device_name">Computer name</label><input id="device_name" name="device_name" value="{{ old('device_name', gethostname() ?: config('app.name')) }}" required autocomplete="off"></div>
                        <div class="field">
                            <label for="platform_url">Your {{ $brandName }} URL</label>
                            <input id="platform_url" name="platform_url" type="url" value="{{ old('platform_url', config('app.url')) }}" required autocomplete="url" placeholder="https://school.example.com">
                            <small style="display:block;margin-top:6px;color:#698197;">This is the public URL of this {{ $brandName }} installation, not the KernelBridge server URL.</small>
                        </div>
                        <button class="submit" type="submit">Activate {{ $brandName }} <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
                    </form>
                @endif
                <p class="privacy"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3m-11 0h12a2 2 0 0 1 2 2v8H4v-8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg><span>The licence is bound to this computer. Keep the activation code and KernelBridge settings private.</span></p>
            </section>
        </main>
        <footer class="footer">{{ $brandName }} {{ $brandSubtitle }}</footer>
    </div>
</body>
</html>

