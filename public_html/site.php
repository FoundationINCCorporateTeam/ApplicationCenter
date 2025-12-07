<?php
// index.php - Server-rendered site using ?page=... routing
// Drop into your web root. Uses styles.css and scripts.js in same directory.
// Basic contact form handling included (mail fallback safe).

// Helper: sanitize page input
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9\-]/i', '', $_GET['page']) : 'home';

// Simple mail handler (works when PHP mail() is available). Safe fallback.
$messageSent = false;
$sendError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $formType = $_POST['form_type'];
    if ($formType === 'contact') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if ($email && $msg) {
            $to = 'hello@astroyds.dev';
            $subject = "PolarisONE Contact: " . ($company ?: 'No company') . " — " . ($name ?: 'Visitor');
            $body = "From: {$name}\nEmail: {$email}\nCompany: {$company}\n\nMessage:\n{$msg}\n\n--\nSent from PolarisONE site.";
            $headers = "From: no-reply@polarisone.example\r\nReply-To: {$email}\r\n";
            // Try to send, but don't error out for demo environments
            if (function_exists('mail')) {
                $ok = @mail($to, $subject, $body, $headers);
                if ($ok) $messageSent = true;
                else $sendError = 'Mail function failed (server may not be configured).';
            } else {
                // Fallback: store to a local file as demonstration (append)
                $logLine = "[" . date('c') . "] Contact: {$email} | {$company} | {$name}\n{$msg}\n\n";
                @file_put_contents(__DIR__ . '/.form-contacts.log', $logLine, FILE_APPEND | LOCK_EX);
                $messageSent = true;
            }
        } else {
            $sendError = 'Please provide at least an email and a message.';
        }
    }
}

// Page metadata mapping
$meta = [
    'home' => ['title' => 'PolarisONE — AI for Roblox Organizations', 'desc' => 'AI-driven game, group and user management for Roblox organizations.'],
    'features' => ['title' => 'Features — PolarisONE', 'desc' => 'Explore PolarisONE features: AI application centers, rank systems, training pipelines and more.'],
    'feature-ai-application-centers' => ['title' => 'AI Application Centers — PolarisONE', 'desc' => 'Hosted inference endpoints and orchestration for live Roblox experiences.'],
    'feature-rank-centers' => ['title' => 'Rank Centers — PolarisONE', 'desc' => 'Role and permissions automation with audits and rollbacks.'],
    'feature-ai-training-centers' => ['title' => 'AI Training Centers — PolarisONE', 'desc' => 'Curate, train and deploy models using session traces and labs.'],
    'solutions' => ['title' => 'Solutions — PolarisONE', 'desc' => 'Packages for Creators, Studios and Enterprises.'],
    'pricing' => ['title' => 'Pricing — PolarisONE', 'desc' => 'Transparent pricing and add-ons.'],
    'enterprise' => ['title' => 'Enterprise — PolarisONE', 'desc' => 'White-glove onboarding and custom SLAs.'],
    'faq' => ['title' => 'FAQ — PolarisONE', 'desc' => 'Frequently asked questions about PolarisONE.'],
    'contact' => ['title' => 'Contact — PolarisONE', 'desc' => 'Contact sales or support at PolarisONE.'],
    'docs' => ['title' => 'Docs — PolarisONE', 'desc' => 'Developer docs, SDKs and integration guides.'],
    'changelog' => ['title' => 'Changelog — PolarisONE', 'desc' => 'Release notes and changelog for PolarisONE.'],
    '404' => ['title' => '404 — PolarisONE', 'desc' => 'Page not found.'],
];

$pageMeta = $meta[$page] ?? $meta['404'];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($pageMeta['title']) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageMeta['desc']) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,600&family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-paper text-ink antialiased min-h-screen" data-page="<?= htmlspecialchars($page) ?>">
  <!-- Grain -->
  <div class="grain fixed inset-0 pointer-events-none z-50" aria-hidden="true"></div>

  <!-- Topbar -->
  <header class="topbar sticky top-0 z-40 backdrop-blur bg-glass border-b">
    <div class="container mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <a href="?page=home" class="logo paper-stamp flex items-center gap-3" title="PolarisONE - Home">
          <svg width="48" height="48" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
            <rect width="64" height="64" rx="8" fill="#071132"/>
            <path d="M16 48L32 16L48 48H16Z" fill="#fff"/>
          </svg>
          <div>
            <div class="font-playfair text-lg leading-tight">PolarisONE</div>
            <div class="text-xs opacity-70">By <strong>Astroyds</strong></div>
          </div>
        </a>
      </div>

      <nav class="hidden lg:flex gap-6 items-center" aria-label="Main">
        <a class="nav-link" href="?page=home">Home</a>
        <div class="relative group">
          <button class="nav-link inline-flex items-center gap-2" aria-haspopup="true">Features <span class="chev">▾</span></button>
          <div class="dropdown-panel absolute right-0 mt-3 w-96 p-4 rounded-2xl shadow-xl glass opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all" aria-hidden="true">
            <ul class="space-y-3">
              <li><a href="?page=feature-ai-application-centers" class="feature-link">AI Application Centers</a></li>
              <li><a href="?page=feature-rank-centers" class="feature-link">Rank Centers</a></li>
              <li><a href="?page=feature-ai-training-centers" class="feature-link">AI Training Centers</a></li>
              <li><a href="?page=feature-ai-application-centers" class="feature-link subtle">AI In-Game Analytics <span class="tag">coming</span></a></li>
              <li><a href="?page=feature-ai-application-centers" class="feature-link subtle">AI Moderation <span class="tag">coming</span></a></li>
            </ul>
          </div>
        </div>
        <a class="nav-link" href="?page=solutions">Solutions</a>
        <a class="nav-link" href="?page=pricing">Pricing</a>
        <a class="nav-link" href="?page=enterprise">Enterprise</a>
      </nav>

      <div class="flex items-center gap-3">
        <a href="?page=contact" class="btn-ghost hidden sm:inline">Sign in</a>
        <a href="?page=pricing" class="btn-primary">Join the Future</a>

        <button id="mobileToggle" class="lg:hidden p-2" aria-label="Open menu">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Drawer -->
  <div id="mobileDrawer" class="mobile-drawer lg:hidden" aria-hidden="true">
    <div class="drawer-inner p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <div class="font-semibold">PolarisONE</div>
          <div class="text-xs opacity-70">By Astroyds</div>
        </div>
        <button id="closeDrawer" class="p-2" aria-label="Close">✕</button>
      </div>
      <nav class="flex flex-col gap-3">
        <a href="?page=home" class="panel-link">Home</a>
        <div class="panel-sub">
          <div class="font-semibold">Features</div>
          <a href="?page=feature-ai-application-centers" class="panel-link ml-3">AI Application Centers</a>
          <a href="?page=feature-rank-centers" class="panel-link ml-3">Rank Centers</a>
          <a href="?page=feature-ai-training-centers" class="panel-link ml-3">AI Training Centers</a>
        </div>
        <a href="?page=solutions" class="panel-link">Solutions</a>
        <a href="?page=pricing" class="panel-link">Pricing</a>
        <a href="?page=enterprise" class="panel-link">Enterprise</a>
        <a href="?page=docs" class="panel-link">Docs</a>
      </nav>
    </div>
  </div>

  <!-- Main -->
  <main id="main" class="container mx-auto px-6 py-10">
<?php
// Content rendering per page. Each page is extensive with multiple sections.
switch ($page) {
    case 'home':
    default:
        ?>
    <section class="hero grid lg:grid-cols-2 gap-10 items-center">
      <div>
        <div class="kicker">AI • MANAGEMENT • ROBLOX</div>
        <h1 class="display text-5xl font-playfair">PolarisONE — The AI newsroom for your Roblox organization</h1>
        <p class="lead mt-4 text-lg opacity-80">Operate safer, ship faster and personalize every player session with AI-powered tooling for rank systems, training, moderation and analytics. PolarisONE gives teams a single audited control plane.</p>

        <div class="mt-6 flex flex-wrap gap-3">
          <a href="?page=features" class="btn-cta">Explore Features</a>
          <a href="?page=pricing" class="btn-ghost">View Pricing</a>
        </div>

        <div class="mt-12 grid md:grid-cols-3 gap-4">
          <div class="card-news p-6">
            <h4 class="font-semibold">Latest Deploy</h4>
            <p class="text-sm opacity-70 mt-2">Polaris v2.4 — Live experiment framework & deterministic replay for training.</p>
          </div>
          <div class="card-news p-6">
            <h4 class="font-semibold">Case Study</h4>
            <p class="text-sm opacity-70 mt-2">How Studio H reduced moderation load by 68% using custom models and review queues.</p>
          </div>
          <div class="card-news p-6">
            <h4 class="font-semibold">Enterprise Pilot</h4>
            <p class="text-sm opacity-70 mt-2">Onboarding options and migration paths for large studios.</p>
          </div>
        </div>
      </div>

      <aside class="vfx-panel relative">
        <div class="panel paper-viewport p-6">
          <div class="ticker">LIVE • AI FEED</div>
          <div class="feed">
            <div class="feed-row">Analyzing session heatmaps — 22 servers</div>
            <div class="feed-row">Rank sync completed: 14.2k users</div>
            <div class="feed-row">Training lanes: 18 active</div>
            <div class="feed-row">Moderation queue: 5 flagged items</div>
          </div>
        </div>
      </aside>
    </section>

    <section class="mt-16">
      <h2 class="section-title">Featured Services</h2>
      <div class="grid md:grid-cols-3 gap-6 mt-6">
        <div class="service-card p-6">
          <h4 class="font-semibold">AI Application Centers</h4>
          <p class="mt-2 text-sm opacity-70">Host inference endpoints close to players; orchestrate triggers and fallback behaviors with business rules.</p>
        </div>
        <div class="service-card p-6">
          <h4 class="font-semibold">Rank Centers</h4>
          <p class="mt-2 text-sm opacity-70">Cross-game roles, conditional promotions and audit trails for compliance and rollback.</p>
        </div>
        <div class="service-card p-6">
          <h4 class="font-semibold">AI Training Centers</h4>
          <p class="mt-2 text-sm opacity-70">Curate traces, annotate, run experiments and promote models to production in one flow.</p>
        </div>
      </div>
    </section>

    <section class="mt-16 grid lg:grid-cols-2 gap-8">
      <div>
        <h3 class="section-title">Deep Dives & Resources</h3>
        <p class="text-sm opacity-80">We've prepared guides, SDK docs, and sample integrations to get you from zero to running models in minutes.</p>

        <div class="mt-6 grid md:grid-cols-2 gap-4">
          <div class="panel p-4">
            <h5 class="font-semibold">Lua SDK</h5>
            <p class="mt-2 text-sm opacity-70">Embed the client SDK inside your game to send traces and receive real-time model responses with deterministic latency SLAs.</p>
          </div>
          <div class="panel p-4">
            <h5 class="font-semibold">Webhooks & APIs</h5>
            <p class="mt-2 text-sm opacity-70">Integrate admin tooling and analytics with secure webhook channels and signed REST endpoints.</p>
          </div>
        </div>
      </div>

      <div>
        <h3 class="section-title">What teams say</h3>
        <div class="mt-4 space-y-4">
          <div class="panel p-4">
            <strong>“PolarisONE replaced 4 separate tools — our moderation noise dropped 68%.”</strong>
            <div class="mt-2 text-sm opacity-70">— Titan Creations</div>
          </div>
          <div class="panel p-4">
            <strong>“Seamless rank sync across games — saved us hours a week.”</strong>
            <div class="mt-2 text-sm opacity-70">— Studio 9</div>
          </div>
        </div>
      </div>
    </section>

    <section class="mt-16">
      <h3 class="section-title">Get started quickly</h3>
      <div class="mt-6 grid lg:grid-cols-3 gap-4">
        <div class="panel p-6">
          <h5 class="font-semibold">Sandbox</h5>
          <p class="mt-2 text-sm opacity-70">Spin up a sandbox environment with sample data and pre-trained policies.</p>
        </div>
        <div class="panel p-6">
          <h5 class="font-semibold">Onboarding</h5>
          <p class="mt-2 text-sm opacity-70">Guided migrations and playbook sessions for studio teams.</p>
        </div>
        <div class="panel p-6">
          <h5 class="font-semibold">Support</h5>
          <p class="mt-2 text-sm opacity-70">SLAs, dedicated channels and periodic reviews for enterprise accounts.</p>
        </div>
      </div>
    </section>

    <?php
    break;

    case 'features':
        ?>
    <section>
      <div class="kicker">FEATURES</div>
      <h2 class="display font-playfair text-4xl mt-2">Everything you need to run production AI for Roblox</h2>
      <p class="mt-4 text-sm opacity-80">PolarisONE combines model hosting, management, observability, and moderation into a single product designed for game teams.</p>

      <div class="mt-10 grid lg:grid-cols-3 gap-6">
        <div class="service-card p-6">
          <h4 class="font-semibold">Model Hosting</h4>
          <p class="mt-2 text-sm opacity-70">Low-latency endpoints with multi-region failover and canary deployments.</p>
          <ul class="mt-3 ml-4 list-disc text-sm opacity-80">
            <li>Versioning & snapshots</li>
            <li>Canary & staged rollouts</li>
            <li>Human-in-loop toggles</li>
          </ul>
        </div>
        <div class="service-card p-6">
          <h4 class="font-semibold">Moderation & Review</h4>
          <p class="mt-2 text-sm opacity-70">Queues, labeling tools and reviewer workflows to surface edge cases</p>
        </div>
        <div class="service-card p-6">
          <h4 class="font-semibold">Analytics & Observability</h4>
          <p class="mt-2 text-sm opacity-70">Dashboard metrics, cohort analysis and drift monitoring.</p>
        </div>
      </div>

      <section class="mt-10">
        <h3 class="section-title">Feature deep-dive</h3>
        <article class="panel p-6 mt-4">
          <h4 class="font-semibold">AI Application Centers</h4>
          <p class="mt-2 text-sm opacity-75">Deploy specialized model endpoints (NPC behavior, event generation, dynamic offers). The system supports triggers, scheduled processes, and integration with Roblox server flows.</p>
          <h5 class="mt-4 font-semibold">Operational controls</h5>
          <ul class="ml-6 list-disc text-sm opacity-80">
            <li>Rate limiting, quotas and per-model throttling</li>
            <li>Rollback safe deploys with audit trails</li>
            <li>Simulation lanes for offline testing</li>
          </ul>
        </article>
      </section>
    </section>
    <?php
    break;

    case 'feature-ai-application-centers':
        ?>
    <section>
      <div class="kicker">FEATURE</div>
      <h2 class="article-title font-playfair">AI Application Centers</h2>
      <p class="article-sub">Flexible model hosting & orchestration for live Roblox experiences.</p>

      <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <div class="prose lg:col-span-2">
          <h3>Overview</h3>
          <p>Host inference endpoints close to players, attach event-based triggers and orchestrate fallback behaviors. Designed for deterministic latency and high availability.</p>

          <h4 class="mt-4">Supported use-cases</h4>
          <ul class="list-disc ml-6">
            <li>Player segmentation & personalized offers</li>
            <li>Dynamic NPC behaviors responsive to session traces</li>
            <li>Procedural event generators for live event scaling</li>
          </ul>

          <h4 class="mt-4">Operational YAML</h4>
          <pre class="code-block"><code># Example: simple canary
deploy:
  model: npc-behavior-v3
  canary: 10%
  monitors: [latency, error_rate]
</code></pre>

          <h4 class="mt-4">Integrations</h4>
          <p>Lua SDK for in-game calls, REST admin APIs, and event webhooks for orchestration and alerts.</p>
        </div>

        <aside class="lg:col-span-1">
          <div class="panel p-6">
            <h5 class="font-semibold">Quick Specs</h5>
            <ul class="mt-2 text-sm opacity-80 space-y-2">
              <li>99.95% SLA (regional)</li>
              <li>Canary & progressive rollout</li>
              <li>Realtime metrics & traces</li>
            </ul>
            <a href="?page=pricing" class="btn-cta mt-4 block text-center">Get started</a>
          </div>

          <div class="panel p-6 mt-6">
            <h5 class="font-semibold">Security</h5>
            <p class="mt-2 text-sm opacity-80">Encryption in transit & at rest, signed SDK keys and role-based access controls for model management.</p>
          </div>
        </aside>
      </div>

      <section class="mt-8">
        <h4 class="section-title">Case study</h4>
        <div class="panel p-4 mt-3">
          <strong>Studio Gamma:</strong> Reduced false positives by 42% through custom model labeling and a human-review loop that improved training datasets by 9x.
        </div>
      </section>
    </section>
    <?php
    break;

    case 'feature-rank-centers':
        ?>
    <section>
      <div class="kicker">FEATURE</div>
      <h2 class="article-title font-playfair">Rank Centers</h2>
      <p class="article-sub">Automated role, rank and permission management with audit & rollback.</p>

      <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <div class="prose lg:col-span-2">
          <h3>What it provides</h3>
          <p>Conditional rank rules, promotion workflows and CSV/SSO-based imports. Track each action with actor, timestamp and reason for compliance and support workflows.</p>

          <h4 class="mt-4">Admin features</h4>
          <ul class="list-disc ml-6">
            <li>Dry-run simulation mode</li>
            <li>Bulk role changes with rollback</li>
            <li>Permission visualizer & inheritance trees</li>
          </ul>

          <h4 class="mt-4">Common scenarios</h4>
          <ol class="list-decimal ml-6">
            <li>Time-based promotions with probationary periods</li>
            <li>AI-driven promotions based on engagement prediction</li>
            <li>Transactional rank events after purchases</li>
          </ol>
        </div>

        <aside class="lg:col-span-1">
          <div class="panel p-6">
            <h5 class="font-semibold">Admin Tools</h5>
            <p class="mt-2 text-sm opacity-80">Bulk CSV imports, policy history, and integration with studio identity providers.</p>
            <a href="?page=solutions" class="btn-ghost mt-4 block text-center">See Solutions</a>
          </div>
        </aside>
      </div>
    </section>
    <?php
    break;

    case 'feature-ai-training-centers':
        ?>
    <section>
      <div class="kicker">FEATURE</div>
      <h2 class="article-title font-playfair">AI Training Centers</h2>
      <p class="article-sub">End-to-end model training, evaluation and promotion pipelines.</p>

      <div class="mt-6 grid lg:grid-cols-3 gap-6">
        <div class="prose lg:col-span-2">
          <h3>Capabilities</h3>
          <p>Collect session traces, curate datasets, run experiments in sandbox lanes and promote validated models into the Application Centers for production.</p>

          <h4 class="mt-4">Workflow</h4>
          <ol class="ml-6 list-decimal">
            <li>Collect sessions with instrumentation</li>
            <li>Annotate & curate datasets</li>
            <li>Train models & evaluate with controlled cohorts</li>
            <li>Deploy via canary & monitor</li>
          </ol>

          <h4 class="mt-4">Observability</h4>
          <p>Model metrics, fairness reports and distribution drift alerts for each promotion.</p>
        </div>

        <aside class="lg:col-span-1">
          <div class="panel p-6">
            <h5 class="font-semibold">Pricing note</h5>
            <p class="mt-2 text-sm opacity-80">Training credits vary by dataset size and runtime. See pricing for detailed breakdowns.</p>
            <a href="?page=pricing" class="btn-cta mt-4 block text-center">View Pricing</a>
          </div>
        </aside>
      </div>
    </section>
    <?php
    break;

    case 'solutions':
        ?>
    <section>
      <div class="kicker">SOLUTIONS</div>
      <h2 class="display font-playfair">Packages & services for every studio size</h2>
      <p class="mt-4 text-sm opacity-80">Choose the package that matches your team's maturity and traffic patterns. We offer migration support, SLAs and dedicated security reviews.</p>

      <div class="mt-8 grid md:grid-cols-3 gap-6">
        <div class="price-card p-6">
          <h3 class="font-semibold">Creators</h3>
          <p class="price-amt">Starter</p>
          <p class="text-sm opacity-70 mt-2">Quickly integrate with minimal ops overhead.</p>
          <ul class="ml-6 list-disc mt-3 text-sm opacity-80">
            <li>Prebuilt workflows</li>
            <li>Sandbox environment</li>
          </ul>
        </div>

        <div class="price-card featured p-6">
          <h3 class="font-semibold">Studios</h3>
          <p class="price-amt">$299/mo</p>
          <p class="text-sm opacity-70 mt-2">Multi-game support & advanced analytics.</p>
          <ul class="ml-6 list-disc mt-3 text-sm opacity-80">
            <li>Team accounts & SSO</li>
            <li>Priority support</li>
          </ul>
        </div>

        <div class="price-card p-6">
          <h3 class="font-semibold">Enterprise</h3>
          <p class="price-amt">Custom</p>
          <p class="text-sm opacity-70 mt-2">SLA, dedicated support & custom integrations.</p>
          <ul class="ml-6 list-disc mt-3 text-sm opacity-80">
            <li>Dedicated account manager</li>
            <li>Custom security reviews</li>
          </ul>
        </div>
      </div>

      <section class="mt-10">
        <h3 class="section-title">Professional Services</h3>
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div class="panel p-4">
            <h5 class="font-semibold">Migration</h5>
            <p class="text-sm opacity-70 mt-2">We help migrate rank systems and policies with minimal downtime and complete audit preservation.</p>
          </div>
          <div class="panel p-4">
            <h5 class="font-semibold">Custom Models</h5>
            <p class="text-sm opacity-70 mt-2">Data engineering and model tuning for specialized gameplay mechanics.</p>
          </div>
        </div>
      </section>
    </section>
    <?php
    break;

    case 'pricing':
        ?>
    <section>
      <div class="kicker">PRICING</div>
      <h2 class="display font-playfair">Simple, transparent pricing</h2>
      <p class="mt-2 text-sm opacity-80">Start free, pay as you scale. Training and inference are metered separately to keep costs predictable.</p>

      <div class="mt-6 grid md:grid-cols-3 gap-6">
        <div class="price-card p-6">
          <h3 class="font-semibold">Hobby</h3>
          <p class="price-amt">$0</p>
          <p class="text-sm opacity-70">Up to 1k monthly active players. Community support.</p>
        </div>
        <div class="price-card featured p-6">
          <h3 class="font-semibold">Studio</h3>
          <p class="price-amt">$299/mo</p>
          <p class="text-sm opacity-70">Multi-game support, analytics and priority support.</p>
        </div>
        <div class="price-card p-6">
          <h3 class="font-semibold">Enterprise</h3>
          <p class="price-amt">Custom</p>
          <p class="text-sm opacity-70">SLAs, dedicated account manager and integrations.</p>
        </div>
      </div>

      <section class="mt-8">
        <h3 class="section-title">Metering</h3>
        <table class="table mt-4">
          <thead>
            <tr><th>Metric</th><th>Hobby</th><th>Studio</th><th>Enterprise</th></tr>
          </thead>
          <tbody>
            <tr><td>MAU</td><td>≤1k</td><td>≤100k</td><td>Custom</td></tr>
            <tr><td>Training credits</td><td>Pay-as-you-go</td><td>Included baseline</td><td>Custom</td></tr>
            <tr><td>Support</td><td>Community</td><td>Priority</td><td>Dedicated</td></tr>
          </tbody>
        </table>
      </section>
    </section>
    <?php
    break;

    case 'enterprise':
        ?>
    <section>
      <div class="kicker">ENTERPRISE</div>
      <h2 class="display font-playfair">White-glove onboarding, SLAs and tailored integrations</h2>
      <p class="mt-2 text-sm opacity-80">We partner with studio engineering and ops teams to implement scalable, secure and auditable flows.</p>

      <div class="mt-8 grid md:grid-cols-2 gap-6">
        <div class="panel p-6">
          <h4 class="font-semibold">What you get</h4>
          <ul class="mt-3 list-disc ml-6">
            <li>Dedicated account manager</li>
            <li>Custom integrations & SSO</li>
            <li>Priority security & compliance reviews</li>
          </ul>
        </div>
        <div class="panel p-6">
          <h4 class="font-semibold">Contact Sales</h4>
          <form method="post" action="?page=enterprise" class="mt-4" onsubmit="return true;">
            <input type="hidden" name="form_type" value="contact" />
            <label class="block text-sm">Company<input name="company" class="mt-1 block w-full rounded-md border p-2" /></label>
            <label class="block text-sm mt-2">Email<input name="email" type="email" required class="mt-1 block w-full rounded-md border p-2" /></label>
            <label class="block text-sm mt-2">Message<textarea name="message" rows="3" class="mt-1 block w-full rounded-md border p-2"></textarea></label>
            <div class="mt-3"><button class="btn-primary" type="submit">Request Contact</button></div>
          </form>
        </div>
      </div>
    </section>
    <?php
    break;

    case 'faq':
        ?>
    <section>
      <div class="kicker">HELP</div>
      <h2 class="display font-playfair">Frequently Asked Questions</h2>
      <div class="mt-6 space-y-4">
        <details class="panel p-4"><summary class="font-semibold">How does pricing work?</summary><div class="mt-2 text-sm opacity-70">Base tiers plus usage-based credits for training and inference. Contact sales for enterprise details.</div></details>
        <details class="panel p-4"><summary class="font-semibold">Is PolarisONE secure?</summary><div class="mt-2 text-sm opacity-70">Yes: TLS, RBAC, audit logs and regular security audits for enterprise customers.</div></details>
        <details class="panel p-4"><summary class="font-semibold">Do you offer SSO?</summary><div class="mt-2 text-sm opacity-70">Yes — SAML and OIDC integrations are available.</div></details>
      </div>
    </section>
    <?php
    break;

    case 'contact':
        ?>
    <section>
      <div class="kicker">CONTACT</div>
      <h2 class="display font-playfair">Get in touch</h2>
      <p class="mt-2 text-sm opacity-80">Sales, partnerships, or support — reach out and we'll respond promptly.</p>

      <div class="mt-6 grid md:grid-cols-2 gap-6">
        <div class="panel p-6">
          <h4 class="font-semibold">Contact</h4>
          <p class="mt-2 text-sm">hello@astroyds.dev</p>
          <p class="mt-1 text-sm">Or use the form to send us a message.</p>
        </div>

        <div class="panel p-6">
          <?php if ($messageSent): ?>
            <div class="notice success p-4">Thank you — your message was received. We'll be in touch shortly.</div>
          <?php elseif ($sendError): ?>
            <div class="notice error p-4"><?= htmlspecialchars($sendError) ?></div>
          <?php endif; ?>

          <form method="post" action="?page=contact" class="space-y-3">
            <input type="hidden" name="form_type" value="contact" />
            <label class="block text-sm">Name<input name="name" class="mt-1 block w-full rounded-md border p-2" /></label>
            <label class="block text-sm">Email<input name="email" type="email" required class="mt-1 block w-full rounded-md border p-2" /></label>
            <label class="block text-sm">Company<input name="company" class="mt-1 block w-full rounded-md border p-2" /></label>
            <label class="block text-sm">Message<textarea name="message" rows="4" required class="mt-1 block w-full rounded-md border p-2"></textarea></label>
            <div><button class="btn-primary" type="submit">Send Message</button></div>
          </form>
        </div>
      </div>
    </section>
    <?php
    break;

    case 'docs':
        ?>
    <section>
      <div class="kicker">DOCS</div>
      <h2 class="display font-playfair">Developer Docs & SDKs</h2>
      <p class="mt-2 text-sm opacity-80">Guides, API references, SDKs and quickstarts to help you integrate PolarisONE into your games.</p>

      <div class="mt-6 grid lg:grid-cols-2 gap-6">
        <div class="panel p-4">
          <h4 class="font-semibold">Quickstart</h4>
          <ol class="ml-6 list-decimal mt-3">
            <li>Install the Lua SDK inside your game.</li>
            <li>Register your experience and obtain a signed key.</li>
            <li>Send session traces and configure a model trigger in the dashboard.</li>
          </ol>
        </div>

        <div class="panel p-4">
          <h4 class="font-semibold">API Reference</h4>
          <p class="mt-2 text-sm opacity-70">REST endpoints for admin tasks and webhook examples for event-based integration.</p>
        </div>
      </div>
    </section>
    <?php
    break;

    case 'changelog':
        ?>
    <section>
      <div class="kicker">RELEASES</div>
      <h2 class="display font-playfair">Changelog</h2>
      <div class="mt-6 space-y-4">
        <div class="panel p-4">
          <strong>v2.4 — 2025-11-01</strong>
          <ul class="ml-6 list-disc mt-2 text-sm opacity-80">
            <li>Experiment framework & deterministic replay for training.</li>
            <li>Security hardening & SDK updates.</li>
          </ul>
        </div>
        <div class="panel p-4">
          <strong>v2.3 — 2025-08-18</strong>
          <ul class="ml-6 list-disc mt-2 text-sm opacity-80">
            <li>Rank sync performance improvements.</li>
            <li>Training pipeline upgrades.</li>
          </ul>
        </div>
      </div>
    </section>
    <?php
    break;

    case '404':
    ?>
    <section class="text-center">
      <h2 class="display font-playfair">404 — Page Not Found</h2>
      <p class="mt-4 text-sm opacity-70">We couldn't find that page. Try returning home or browsing features.</p>
      <div class="mt-6">
        <a href="?page=home" class="btn-cta">Return Home</a>
        <a href="?page=features" class="btn-ghost ml-3">Explore Features</a>
      </div>
    </section>
    <?php
    break;
}
?>
  </main>

  <!-- Footer -->
  <footer class="border-t mt-12 pt-8 pb-12">
    <div class="container mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-8">
      <div>
        <h3 class="font-playfair text-xl">PolarisONE</h3>
        <p class="mt-2 max-w-md text-sm opacity-80">AI-driven tools for managing Roblox games, groups and users — built by Astroyds.</p>
      </div>
      <div>
        <h4 class="font-semibold">Explore</h4>
        <ul class="mt-3 space-y-2 text-sm">
          <li><a href="?page=features" class="underline">Features</a></li>
          <li><a href="?page=pricing" class="underline">Pricing</a></li>
          <li><a href="?page=docs" class="underline">Docs</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-semibold">Contact</h4>
        <p class="mt-2 text-sm">hello@astroyds.dev</p>
        <p class="mt-1 text-sm">© PolarisONE — Astroyds</p>
      </div>
    </div>
  </footer>

  <script src="scripts.js" type="module"></script>
</body>
</html>