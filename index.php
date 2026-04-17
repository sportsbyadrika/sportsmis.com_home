<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SportsMIS – Empowering Sports Communities</title>
  <meta name="description" content="SportsMIS is a Sportsbya Tech subsidiary platform for athletes, coaches, and institutions: performance analytics, event management, and community support." />
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{
      --sbya-navy:#0b1f3a;       /* deep blue used across Sportsbya visuals */
      --sbya-accent:#f59e0b;     /* warm orange accent */
      --sbya-teal:#14b8a6;       /* tech teal accent */
      --sbya-ink:#0f172a;        /* near-black for headings */
      --card-radius:1.25rem;
      --shadow-soft:0 8px 24px rgba(2,8,23,.08);
    }
    body{font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; color:#1f2937;}
    .navbar{box-shadow:0 1px 0 rgba(2,8,23,.06);}
    .brand-badge{font-size:.75rem; letter-spacing:.06em; text-transform:uppercase}
    .btn-accent{background:var(--sbya-accent); color:#06121f; border:none}
    .btn-accent:hover{background:#d58809; color:#06121f}
    .btn-outline-ghost{border:1px solid rgba(255,255,255,.6); color:#fff}
    .btn-outline-ghost:hover{background:rgba(255,255,255,.1); color:#fff}
    .hero{
      background: radial-gradient(1200px 600px at 20% -10%, rgba(245,158,11,.20), transparent 60%),
                  radial-gradient(900px 500px at 90% 20%, rgba(20,184,166,.20), transparent 60%),
                  linear-gradient(180deg, #0b1f3a 0%, #0a1830 100%);
      color:#e5e7eb;
    }
    .hero h1{color:#fff}
    .hero .lead{color:#d1d5db}
    .feature-card{
      border:none; border-radius:var(--card-radius); box-shadow:var(--shadow-soft);
    }
    .feature-card .icon{
      width:3rem; height:3rem; display:grid; place-items:center; border-radius:1rem; color:#fff;
    }
    .icon-analytics{background:linear-gradient(135deg, var(--sbya-teal), #22d3ee)}
    .icon-events{background:linear-gradient(135deg, var(--sbya-accent), #fbbf24)}
    .icon-community{background:linear-gradient(135deg, #6366f1, #22c55e)}
    .pill{background:rgba(2,8,23,.06); border-radius:999px; padding:.35rem .75rem; font-size:.8rem}
    .section-title{color:var(--sbya-ink)}
    .logo-img{height:34px}
    .card-media{aspect-ratio:16/9; background:#0b1f3a10; border-radius:1rem; overflow:hidden}
    .badge-sub{background:rgba(245,158,11,.14); color:#b45309}
    .footer{background:#0a1324; color:#93a3b8}
    .footer a{color:#cbd5e1; text-decoration:none}
    .footer a:hover{color:#fff}
      /* Event list cards */
    .event-card{border:1px solid rgba(2,8,23,.06); border-radius:1rem; box-shadow:var(--shadow-soft); transition:transform .15s ease, box-shadow .15s ease; text-decoration:none; color:inherit; background:#fff}
    .event-card:hover{transform:translateY(-2px); box-shadow:0 12px 28px rgba(2,8,23,.12)}
    .event-logo{width:48px; height:48px; border-radius:.75rem; background:#f8fafc; display:grid; place-items:center; overflow:hidden}
    .event-meta{font-size:.875rem; color:#6b7280}
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg bg-white sticky-top">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="#">
        <!-- Replace src with your actual logo path -->
        <img src="sba-logo.png" alt="SportsMIS" class="logo-img" />
        <span class="fw-bold">SportsMIS</span>
      </a>
      <span class="brand-badge text-muted ms-2">by Sportsbya Tech Pvt. Ltd.</span>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#stakeholders">Stakeholders</a></li>
          
          <li class="nav-item"><a class="nav-link" href="#events">Events</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        </ul>
        <div class="d-flex gap-2 ms-lg-3">
          <a class="btn btn-outline-primary" href="#demo">Request Demo</a>
          <a class="btn btn-primary" href="/mis">Login</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero py-5 py-lg-6">
    <div class="container">
      <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-6">
          <span class="pill text-uppercase fw-semibold mb-3 d-inline-flex align-items-center gap-2">
            <i class="bi bi-lightning-charge-fill text-warning"></i> Empowering Athletes with Technology
          </span>
          <h1 class="display-5 fw-extrabold mb-3">Connect. Collaborate. <span style="color:var(--sbya-accent)">Excel.</span></h1>
          <p class="lead mb-4">A unified platform for <strong>athletes</strong>, <strong>coaches</strong>, and <strong>institutions</strong>—built by <strong>Sportsbya Tech</strong>—to track performance, manage events, and grow thriving sports communities.</p>
          <div class="d-flex flex-wrap gap-2">
            <a href="/mis" class="btn btn-accent btn-lg px-4">Login</a>
            <a href="#cards" class="btn btn-outline-ghost btn-lg px-4">Explore Modules</a>
          </div>
          <div class="d-flex gap-3 mt-4 small">
            <span><i class="bi bi-shield-check me-1 text-success"></i>Secure & Scalable</span>
            <span><i class="bi bi-graph-up-arrow me-1 text-info"></i>Real-time Analytics</span>
            <span><i class="bi bi-cloud-arrow-up me-1 text-primary"></i>Cloud Ready</span>
          </div>
        </div>
        <div class="col-lg-6">
          <!-- Replace with your hero image (e.g., generated banner with new logo) -->
          <div class="card-media">
            <img src="athelete_performance.png" class="w-100 h-100 object-fit-cover" alt="Athlete analytics hero" />
          </div>
        </div>
      </div>
    </div>
  </section>
<!-- ACTIVE EVENTS -->
  <section id="events" class="py-5 bg-light">
    <div class="container">
      <div class="row text-center mb-4">
        <div class="col">
          <h2 class="section-title fw-bold">Active Events</h2>
          <p class="text-muted mb-0">Upcoming meets and programs. Click a card to view full details and register.</p>
        </div>
      </div>

      <div class="row g-4">
        <!-- Event Card Item -->
        <div class="col-md-6 col-lg-4">
          <a class="event-card d-flex align-items-center gap-3 p-3" href="/mis" aria-label="Kerala State Athletics Meet">
            <div class="event-logo">
              <img src="sahodaya.png" alt="Sahodaya" class="img-fluid" />
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold">CBSE South Zone Sahodaya</div>
              <div class="event-meta"><i class="bi bi-geo-alt me-1"></i> Attingal • <i class="bi bi-calendar-event ms-2 me-1"></i> 23-25 Oct 2025</div>
            </div>
            <i class="bi bi-arrow-right-short fs-4 text-secondary"></i>
          </a>
        </div>

        <!--<div class="col-md-6 col-lg-4">
          <a class="event-card d-flex align-items-center gap-3 p-3" href="/events/junior-swim-open" aria-label="Junior Swim Open">
            <div class="event-logo">
              <img src="assets/event-logo-swim.png" alt="Junior Swim Open" class="img-fluid" />
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold">Junior Swim Open</div>
              <div class="event-meta"><i class="bi bi-geo-alt me-1"></i> Kochi • <i class="bi bi-calendar-event ms-2 me-1"></i> 22 Oct 2025</div>
            </div>
            <i class="bi bi-arrow-right-short fs-4 text-secondary"></i>
          </a>
        </div>

        <div class="col-md-6 col-lg-4">
          <a class="event-card d-flex align-items-center gap-3 p-3" href="/events/shooting-championship" aria-label="District Shooting Championship">
            <div class="event-logo">
              <img src="assets/event-logo-shoot.png" alt="Shooting Championship" class="img-fluid" />
            </div>
            <div class="flex-grow-1">
              <div class="fw-semibold">District Shooting Championship</div>
              <div class="event-meta"><i class="bi bi-geo-alt me-1"></i> Thrissur • <i class="bi bi-calendar-event ms-2 me-1"></i> 05–07 Nov 2025</div>
            </div>
            <i class="bi bi-arrow-right-short fs-4 text-secondary"></i>
          </a>
        </div>-->
      </div>

    </div>
  </section>
  <!-- FEATURE HIGHLIGHTS -->
  <section id="features" class="py-5">
    <div class="container">
      <div class="row text-center mb-4">
        <div class="col">
          <h2 class="section-title fw-bold">Built for the Entire Sports Ecosystem</h2>
          <p class="text-muted">SportsMIS brings everyone together with tools that mirror Sportsbya’s clean, modern aesthetic.</p>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card card p-4 h-100">
            <div class="icon icon-analytics mb-3"><i class="bi bi-activity"></i></div>
            <h5 class="fw-semibold mb-2">Performance Analytics</h5>
            <p class="mb-0">Track progress with session logs, biometrics, and visual dashboards for informed training decisions.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card card p-4 h-100">
            <div class="icon icon-events mb-3"><i class="bi bi-calendar2-event"></i></div>
            <h5 class="fw-semibold mb-2">Event Management</h5>
            <p class="mb-0">Plan meets and training blocks, handle registrations, lane allocations, scoring, and results.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card card p-4 h-100">
            <div class="icon icon-community mb-3"><i class="bi bi-people"></i></div>
            <h5 class="fw-semibold mb-2">Community Support</h5>
            <p class="mb-0">Connect athletes, coaches, and institutions for mentoring, resources, and nearby-facility discovery.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STAKEHOLDERS -->
  <section id="stakeholders" class="py-5 bg-light">
    <div class="container">
      <div class="row text-center mb-4">
        <div class="col">
          <h2 class="section-title fw-bold">Who It’s For</h2>
          <p class="text-muted">Purpose-built journeys that align with Sportsbya’s product family.</p>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="badge bg-primary-subtle text-primary">Athletes</span>
              </div>
              <h5 class="fw-semibold">Track • Learn • Grow</h5>
              <ul class="mt-3 mb-0 small text-muted ps-3">
                <li>Verified athlete profiles & performance history</li>
                <li>Training logs, targets, and wellness insights</li>
                <li>Mentor matching & goal tracking</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="badge bg-success-subtle text-success">Coaches</span>
              </div>
              <h5 class="fw-semibold">Guide the Future</h5>
              <ul class="mt-3 mb-0 small text-muted ps-3">
                <li>Showcase expertise & certifications</li>
                <li>Plan sessions, share drills & feedback</li>
                <li>Roster management & progression reports</li>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-2">
                <span class="badge bg-warning-subtle text-warning">Institutions</span>
              </div>
              <h5 class="fw-semibold">Nurture Excellence</h5>
              <ul class="mt-3 mb-0 small text-muted ps-3">
                <li>Facility profiles, asset & event calendars</li>
                <li>Engagement analytics & compliance exports</li>
                <li>Federation-ready integrations & reports</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- THREE CORE CARDS 
  <section id="cards" class="py-5">
    <div class="container">
      <div class="row text-center mb-4">
        <div class="col">
          <h2 class="section-title fw-bold">Core Modules</h2>
          <p class="text-muted">Add these cards to the Sportsbya website grid. Replace images with your final artwork.</p>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <img src="assets/card-performance.jpg" class="w-100" alt="Performance Analytics card" />
            <div class="card-body">
              <h5 class="fw-semibold">Performance Analytics</h5>
              <p class="small text-muted mb-3">Session metrics, biometrics, progression charts, and report exports.</p>
              <a href="#" class="btn btn-sm btn-outline-primary">View Details</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <img src="assets/card-events.jpg" class="w-100" alt="Event Management card" />
            <div class="card-body">
              <h5 class="fw-semibold">Event Management</h5>
              <p class="small text-muted mb-3">Registrations, lane/relay allocation, scoring, schedules, and results.</p>
              <a href="#" class="btn btn-sm btn-outline-primary">View Details</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
            <img src="assets/card-community.jpg" class="w-100" alt="Community Support card" />
            <div class="card-body">
              <h5 class="fw-semibold">Community Support</h5>
              <p class="small text-muted mb-3">Mentorship, resources, and nearby sports facilities & groups.</p>
              <a href="#" class="btn btn-sm btn-outline-primary">View Details</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>-->
  

  <!-- CTA -->
  <section class="py-5" style="background:linear-gradient(180deg,#0b1f3a,#0a1324); color:#e5e7eb">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
          <h3 class="text-white fw-bold mb-2">Ready to level up your sports ecosystem?</h3>
          <p class="mb-0 text-light">Join SportsMIS—built by Sportsbya Tech—to unify performance, events, and community in one secure platform.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="#signup" class="btn btn-accent btn-lg">Create Account</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer py-4">
    <div class="container">
      <div class="row g-3 align-items-center">
        <div class="col-md-6">
          <div class="d-flex align-items-center gap-2">
            <img src="sba-logo.png" class="logo-img" alt="SportsMIS" />
            <strong class="text-white">SportsMIS</strong>
            <span class="badge badge-sub ms-2">Subsidiary of Sportsbya Tech Pvt. Ltd.</span>
          </div>
          <div class="small mt-2">© <span id="y"></span> Sportsbya Tech Pvt. Ltd. All rights reserved.</div>
        </div>
        <div class="col-md-6 text-md-end small">
          <a href="#">Privacy</a>
          <span class="mx-2">•</span>
          <a href="#">Terms</a>
          <span class="mx-2">•</span>
          <a href="#contact">Contact</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    document.getElementById('y').textContent = new Date().getFullYear();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
