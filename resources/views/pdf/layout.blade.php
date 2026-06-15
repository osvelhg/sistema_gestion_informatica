<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Documento PDF')</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 90px 30px 56px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }

        .pdf-header {
            position: fixed;
            top: -78px;
            left: 0;
            right: 0;
            min-height: 60px;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 6px;
        }
        .pdf-header-inner { width: 100%; display: table; table-layout: fixed; }
        .pdf-header-logo { display: table-cell; width: 56px; vertical-align: middle; padding-right: 8px; }
        .pdf-header-logo img { max-height: 50px; max-width: 54px; display: block; }
        .pdf-header-text { display: table-cell; vertical-align: middle; }
        .pdf-header .line-1 { font-size: 11px; font-weight: 700; color: #0f172a; }
        .pdf-header .line-2 { margin-top: 2px; font-size: 9px; color: #334155; }
        .pdf-header .line-3 { margin-top: 4px; font-size: 8px; color: #1d4ed8; text-transform: uppercase; letter-spacing: .08em; }

        .pdf-footer {
            position: fixed;
            bottom: -42px;
            left: 0;
            right: 0;
            height: 32px;
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8px;
            padding-top: 5px;
        }
        .footer-row { width: 100%; }
        .footer-left { float: left; }
        .footer-right { float: right; }
        .footer-center { text-align: center; }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: #1e3a8a;
            margin: 12px 0 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }
        table.data { width: 100%; border-collapse: collapse; margin: 8px 0; }
        table.data th { background: #1e3a8a; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; vertical-align: top; }
        table.data tr:nth-child(even) { background: #f8fafc; }

        .status-bien { color: #15803d; font-weight: 700; }
        .status-regular { color: #a16207; font-weight: 700; }
        .status-mal { color: #b91c1c; font-weight: 700; }
    </style>
    @stack('styles')
</head>
<body>
    <header class="pdf-header">
        <div class="pdf-header-inner">
            @if(!empty($brandingLogoDataUrl ?? null))
                <div class="pdf-header-logo">
                    <img src="{{ $brandingLogoDataUrl }}" alt="" />
                </div>
            @endif
            <div class="pdf-header-text">
                <div class="line-1">{{ $branding['organization_name'] }}</div>
                <div class="line-2">{{ $branding['system_name'] }}</div>
                <div class="line-3">{{ $branding['header_title'] }}</div>
            </div>
        </div>
    </header>

    <footer class="pdf-footer">
        <div class="footer-row">
            <div class="footer-left">{{ $branding['footer_left'] }}</div>
            <div class="footer-right">{{ $branding['footer_right'] }} - {{ now()->format('d/m/Y H:i') }}</div>
            <div class="footer-center">@yield('footer_center', 'Pagina') </div>
        </div>
    </footer>

    <main>
        @yield('content')
    </main>
</body>
</html>
