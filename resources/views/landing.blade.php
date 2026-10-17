<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NB Poultry Farm Management System | Thesis</title>
    <meta name="description" content="A web-based NB Poultry Farm Management System with QR code egg tracking and egg production forecasting for San Jose, Batangas.">
    <meta name="theme-color" content="#216E4B">
    <meta name="application-name" content="NB Poultry Farm">
    <meta name="author" content="NB Poultry Farm">
    <meta name="keywords" content="NB Poultry Farm, San Jose Batangas, egg production, inventory, dispatch, QR tracking, forecasting">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="NB Poultry Farm">
    <meta property="og:title" content="NB Poultry Farm Management System | Thesis">
    <meta property="og:description" content="A web-based NB Poultry Farm Management System with QR code egg tracking and egg production forecasting.">
    <meta property="og:image" content="{{ asset('images/nb-farm-logo.png') }}">
    <meta property="og:locale" content="en_PH">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="NB Poultry Farm Management System">
    <meta name="twitter:description" content="QR code egg tracking and egg production forecasting for NB Poultry Farm.">
    <meta name="twitter:image" content="{{ asset('images/nb-farm-logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ versioned_asset('css/landing.css') }}">
</head>
<body>
    <div class="thesis-page" id="top">
        <header class="thesis-header">
            <a class="thesis-mark" href="#top" aria-label="NB Poultry Farm">
                <img
                    src="{{ asset('images/nb-farm-logo.png') }}"
                    alt="NB Poultry Farm logo"
                    width="52"
                    height="52"
                >
            </a>
            <nav aria-label="Primary">
                <a href="#about">About</a>
                <a href="#modules">System</a>
                <a href="#team">Team</a>
                <a class="thesis-login" href="{{ route('login') }}">Log in</a>
            </nav>
        </header>

        <main>
            <section class="thesis-hero">
                <div class="thesis-wrap">
                    <h1>NB Poultry Farm Management System</h1>
                    <p class="thesis-sub">
                        A web-based, service-oriented management system with QR code egg tracking
                        and egg production forecasting, built for NB Poultry Farm in San Jose, Batangas.
                    </p>
                    <div class="thesis-cta">
                        <a class="thesis-btn thesis-btn-primary" href="{{ route('login') }}">Log in</a>
                        <a class="thesis-btn thesis-btn-ghost" href="#about">About the system</a>
                    </div>
                </div>

                <div class="egg-tray" aria-hidden="true">
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                    <span class="thesis-egg"></span>
                </div>
            </section>

            <section class="thesis-section thesis-overview" id="about">
                <div class="thesis-wrap">
                    <h2>Overview</h2>
                    <p class="thesis-lead">
                        NB Poultry Farm still records egg production, stock, dispatch, and sales
                        in logbooks and ledgers. This study develops a web-based management system
                        that replaces those records, tracks egg batches with QR codes, and forecasts
                        egg production using Linear Regression.
                    </p>
                </div>
            </section>

            <section class="thesis-section thesis-modules" id="modules">
                <div class="thesis-wrap">
                    <h2>What the system does</h2>
                    <div class="thesis-module-grid">
                        <article class="thesis-module">
                            <h3>Production Monitoring</h3>
                            <p>Daily egg counts by batch, with QR code tracking to identify and trace each egg batch.</p>
                        </article>
                        <article class="thesis-module">
                            <h3>Inventory Management</h3>
                            <p>Feeds, vitamins, and trays on hand, with stock updates.</p>
                        </article>
                        <article class="thesis-module">
                            <h3>Dispatch and Sales</h3>
                            <p>Sorting, delivery, stock recall, and revenue records.</p>
                        </article>
                        <article class="thesis-module">
                            <h3>Forecasting</h3>
                            <p>Predict egg production, availability, and stock levels.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="thesis-section" id="team">
                <div class="thesis-wrap">
                    <h2>Researchers</h2>
                    <div class="thesis-team">
                        <div class="thesis-person"><b>Gawaran, Louise Ann 1</b><span>Researcher</span></div>
                        <div class="thesis-person"><b>Laqui, Charisse</b><span>Researcher</span></div>
                        <div class="thesis-person"><b>Medrazo, Klyzza Mae 3</b><span>Researcher</span></div>
                        <div class="thesis-person"><b>Saclao, Ma. Jaira 3</b><span>Researcher</span></div>
                    </div>

                    <div class="thesis-info">
                        <div><small>Adviser Name</small><b>Zymon Andrew Maquinto</b></div>
                        <div><small>College</small><b>College of Informatics and Computing Sciences</b></div>
                        <div><small>University</small><b>Batangas State University, Lipa Campus</b></div>
                        <div><small>Academic year</small><b>2026</b></div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="thesis-footer">
            NB Poultry Farm Management System, San Jose, Batangas. 2026.
            <a href="{{ route('login') }}">Log in</a>
        </footer>
    </div>

    @if (false)
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="#home" aria-label="NB Farm home">
                <img
                    class="brand-logo"
                    src="{{ asset('images/nb-farm-logo.png') }}"
                    alt="NB Farm logo"
                    width="40"
                    height="40"
                >
                <span class="brand-copy">
                    <strong>NB Poultry Farm</strong>
                    <small>San Jose, Batangas</small>
                </span>
            </a>

            <nav class="desktop-nav" aria-label="Primary">
                <a href="#home">Home</a>
                <a href="#about">About</a>
                <a href="#features">Features</a>
                <a href="#contact">Contact</a>
            </nav>

            <div class="topbar-actions">
                <a class="btn btn-ink" href="{{ route('login') }}">Login</a>
                <button
                    class="menu-btn"
                    type="button"
                    aria-expanded="false"
                    aria-controls="mobile-menu"
                    data-nav-toggle
                >
                    <span class="visually-hidden">Open menu</span>
                    Menu
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-menu" id="mobile-menu" data-mobile-drawer hidden>
        <div class="mobile-sheet" role="dialog" aria-label="Mobile menu">
            <a href="#home" data-drawer-link>Home</a>
            <a href="#about" data-drawer-link>About</a>
            <a href="#features" data-drawer-link>Features</a>
            <a href="#contact" data-drawer-link>Contact</a>
            <a class="mobile-login" href="{{ route('login') }}">Login</a>
        </div>
    </div>

    <main>
        <section class="hero" id="home">
            <div class="hero-sheet">
                <div class="hero-copy">
                    <p class="eyebrow">San Jose, Batangas · NB Poultry Farm</p>
                    <div class="hero-title-row">
                        <h1>Track Production. Manage Inventory.</h1>
                        <aside class="egg-stock" aria-label="Total egg stock">
                            <p class="egg-stock-label">Total Egg Stock</p>
                            <p class="egg-stock-value">{{ number_format($eggsStock ?? 0) }}</p>
                            <p class="egg-stock-note">Eggs on hand</p>
                        </aside>
                    </div>
                </div>

                <aside class="preview-panel weather-panel" aria-label="Weather forecast">
                    <div class="preview-head">
                        <p class="preview-label">Weather Forecast</p>
                        <p class="preview-note">{{ $weather['location'] ?? 'San Jose, Batangas' }}</p>
                    </div>

                    @if (! empty($weather))
                        <div class="weather-now weather-now--{{ $weather['modifier'] ?? 'cloud' }}">
                            <div>
                                <p class="weather-now-label">Now</p>
                                <p class="weather-now-temp">{{ $weather['temperature'] }}<span>°C</span></p>
                                <p class="weather-now-condition">{{ $weather['condition'] }}</p>
                            </div>
                            <div class="weather-now-meta">
                                <p><span>Humidity</span> {{ $weather['humidity'] }}%</p>
                                <p><span>Wind</span> {{ $weather['wind'] }} km/h</p>
                                <p><span>Updated</span> {{ $weather['date_label'] ?? '' }}</p>
                            </div>
                        </div>

                        <div class="weather-days">
                            @foreach (array_slice($weather['forecast'] ?? [], 0, 5) as $day)
                                <article class="weather-day weather-day--{{ $day['modifier'] ?? 'cloud' }}">
                                    <p class="weather-day-name">{{ $day['day'] }}</p>
                                    <p class="weather-day-temp">{{ $day['max'] }}° <span>/ {{ $day['min'] }}°</span></p>
                                    <p class="weather-day-condition">{{ $day['condition'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p class="weather-empty">Weather forecast is unavailable right now.</p>
                    @endif
                </aside>
            </div>
            <div class="hero-photo" role="img" aria-label="NB Poultry Farm"></div>
        </section>

        <section class="section section-muted" id="about">
            <div class="container about-layout">
                <div class="about-copy reveal">
                    <p class="section-kicker">About NB Farm</p>
                    <h2>Designed for Egg Production Monitoring at NB Poultry Farm in San Jose</h2>
                    <p class="section-intro">
                        NB Poultry Farm Management System brings owners, managers, and poultry staff
                        together so production counts, stock levels, and outbound trays stay aligned
                        day to day.
                    </p>
                    <p class="section-intro">
                        From daily egg logs and QR tray scans to inventory alerts, dispatch records,
                        and production forecasts, the system replaces scattered notebooks and chat
                        threads with a single source of truth for the coop.
                    </p>
                </div>
                <figure class="about-visual reveal">
                    <img
                        src="{{ asset('images/farm/farm.jpg') }}"
                        alt="NB Poultry Farm — layers house in San Jose, Batangas"
                        width="1600"
                        height="1200"
                        loading="lazy"
                    >
                    <figcaption class="about-visual-frame">
                        <strong>NB Poultry Farm</strong>
                        <span>San Jose, Batangas</span>
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="section" id="features">
            <div class="container">
                <div class="reveal">
                    <p class="section-kicker">What it does</p>
                    <h2>Egg Production Monitoring and Farm Management System</h2>
                    <p class="section-intro">
                        Everything the farm team needs to record, review, and act on — without
                        switching between spreadsheets and paper logs.
                    </p>
                </div>

                <div class="feature-grid">
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">🥚</div>
                        <div class="feature-body">
                            <h3>Production Monitoring</h3>
                            <p>Log daily egg counts by batch and see how the flock is performing over time.</p>
                        </div>
                    </article>
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">📦</div>
                        <div class="feature-body">
                            <h3>Inventory Management</h3>
                            <p>Keep feeds, vitamins, and trays on hand with stock history and low-stock alerts.</p>
                        </div>
                    </article>
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">📱</div>
                        <div class="feature-body">
                            <h3>QR Egg Tracking</h3>
                            <p>Generate and scan tray codes so eggs move in and out of the coop with a clear trail.</p>
                        </div>
                    </article>
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">🚚</div>
                        <div class="feature-body">
                            <h3>Dispatch & Sales</h3>
                            <p>Record outbound deliveries, update status, and keep sales history ready for review.</p>
                        </div>
                    </article>
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">📈</div>
                        <div class="feature-body">
                            <h3>Forecasting</h3>
                            <p>Look ahead at where production is heading so planning stays a step ahead of demand.</p>
                        </div>
                    </article>
                    <article class="feature reveal">
                        <div class="feature-icon" aria-hidden="true">📋</div>
                        <div class="feature-body">
                            <h3>Reports & Alerts</h3>
                            <p>Share operational reports, weather context, and push alerts when something needs attention.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="section section-dark" id="contact">
            <div class="container">
                <div class="footer-grid reveal">
                    <div class="footer-col">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="#home">Home</a></li>
                            <li><a href="#about">About</a></li>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#contact">Contact</a></li>
                        </ul>
                    </div>
                    <div class="footer-col">
                        <h3>Utility Pages</h3>
                        <ul>
                            <li><a href="{{ route('terms') }}">Terms of Service</a></li>
                            <li><a href="{{ route('login') }}">Login</a></li>
                        </ul>
                    </div>
                    <div class="footer-col">
                        <h3>Farm</h3>
                        <p>NB Poultry Farm</p>
                        <p>San Jose, Batangas</p>
                    </div>
                    <div class="footer-col">
                        <h3>Contact Us</h3>
                        <p>NB Poultry Farm</p>
                        <p>San Jose, Batangas</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            © {{ date('Y') }} NB Poultry Farm · San Jose, Batangas
        </div>
    </footer>

    <script>
        (() => {
            const toggle = document.querySelector('[data-nav-toggle]');
            const drawer = document.querySelector('[data-mobile-drawer]');
            const links = document.querySelectorAll('[data-drawer-link]');

            const setOpen = (open) => {
                if (!toggle || !drawer) return;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                drawer.classList.toggle('is-open', open);
                drawer.hidden = !open;
                document.body.style.overflow = open ? 'hidden' : '';
            };

            toggle?.addEventListener('click', () => {
                setOpen(toggle.getAttribute('aria-expanded') !== 'true');
            });

            drawer?.addEventListener('click', (event) => {
                if (event.target === drawer) setOpen(false);
            });

            links.forEach((link) => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });

            const reveals = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window)) {
                reveals.forEach((el) => el.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting) return;
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                },
                { threshold: 0.16, rootMargin: '0px 0px -40px 0px' }
            );

            reveals.forEach((el) => observer.observe(el));
        })();
    </script>
    @endif
</body>
</html>
