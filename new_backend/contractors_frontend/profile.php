<?php
// profile.php
// Dynamic contractor profile page. Can be called as profile.php?id=123
// or included by wrapper files that set $userId before including this file.



function resolveImageWebPath(string $stored): string {
    $stored = trim((string)$stored);
    if ($stored === '') return '';

    // If it's already an absolute URL, return it.
    if (preg_match('#^https?://#i', $stored)) return $stored;

    // Normalize slashes
    $storedNorm = str_replace('\\', '/', $stored);
    $candidateWeb = $storedNorm[0] === '/' ? $storedNorm : '/' . ltrim($storedNorm, '/');

    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

    // 0) Quick targeted mapping for known new_backend case (fast and explicit).
    if (strlen($candidateWeb) > 8 && stripos($candidateWeb, '/uploads/') === 0 && $docRoot !== '') {
        $maybeServer = $docRoot . '/new_backend' . $candidateWeb;
        if (is_file($maybeServer) && filesize($maybeServer) > 0) {
            return str_replace('\\', '/', '/new_backend' . $candidateWeb);
        }
    }

    // 1) Direct check under DOCUMENT_ROOT
    if ($docRoot !== '') {
        $serverPath = $docRoot . $candidateWeb;
        if (is_file($serverPath) && filesize($serverPath) > 0) {
            return str_replace('\\', '/', $candidateWeb);
        }
    }

    // 2) Check immediate children of DOCUMENT_ROOT (handles '/new_backend' etc.)
    if ($docRoot !== '') {
        $dirs = glob($docRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $serverCandidate = $dir . $candidateWeb;
            if (is_file($serverCandidate) && filesize($serverCandidate) > 0) {
                $sub = '/' . trim(basename($dir), '/\\') . $candidateWeb;
                return str_replace('\\', '/', $sub);
            }
        }
    }

    // 3) Try script-relative path (same dir as this script)
    $serverPath2 = __DIR__ . DIRECTORY_SEPARATOR . ltrim($storedNorm, '/');
    if (is_file($serverPath2) && filesize($serverPath2) > 0) {
        if ($docRoot !== '' && strpos(realpath($serverPath2), realpath($docRoot)) === 0) {
            $web = str_replace('\\', '/', substr(realpath($serverPath2), strlen(realpath($docRoot))));
            if ($web === '' || $web[0] !== '/') $web = '/' . $web;
            return $web;
        }
        return '/' . ltrim($storedNorm, '/');
    }

    // 4) Walk up parents to find file
    $dir = __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $cand = $dir . DIRECTORY_SEPARATOR . ltrim($storedNorm, '/');
        if (is_file($cand) && filesize($cand) > 0) {
            $real = realpath($cand);
            if ($docRoot !== '' && strpos($real, realpath($docRoot)) === 0) {
                $web = str_replace('\\', '/', substr($real, strlen(realpath($docRoot))));
                if ($web === '' || $web[0] !== '/') $web = '/' . $web;
                return $web;
            }
            // try to map common server roots inside path
            $lower = strtolower(str_replace('\\', '/', $real));
            $roots = ['htdocs','public_html','www','httpdocs','wwwroot','html'];
            foreach ($roots as $r) {
                $pos = strpos($lower, '/' . $r . '/');
                if ($pos !== false) {
                    $web = substr($real, $pos + 1);
                    $web = str_replace('\\', '/', $web);
                    if ($web === '' || $web[0] !== '/') $web = '/' . $web;
                    return $web;
                }
            }
            return '/' . ltrim($storedNorm, '/');
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }

    // final fallback: return candidate web path
    return str_replace('\\', '/', $candidateWeb);
}

require_once __DIR__ . '/../config.php';
$pdo = getPDO();

// determine contractor id (allow wrapper to set $userId)
if (isset($userId) && is_int($userId)) {
    $uid = (int)$userId;
} else {
    $uid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}

if ($uid <= 0) {
    http_response_code(400);
    echo "Invalid contractor id.";
    exit;
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// Fetch user + profile
$stmt = $pdo->prepare('SELECT u.user_id, u.name, u.email, u.phone, u.role, p.* FROM `User` u LEFT JOIN ContractorProfile p ON p.contractor_id = u.user_id WHERE u.user_id = :id LIMIT 1');
$stmt->execute([':id' => $uid]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    http_response_code(404);
    echo "Contractor not found.";
    exit;
}

// services
$sStmt = $pdo->prepare('SELECT service_name, details FROM ContractorService WHERE contractor_id = :id ORDER BY service_id ASC');
$sStmt->execute([':id' => $uid]);
$services = $sStmt->fetchAll(PDO::FETCH_ASSOC);

// certs
$cStmt = $pdo->prepare('SELECT cert_name, issuer, issue_date FROM ContractorCertification WHERE contractor_id = :id ORDER BY cert_id ASC');
$cStmt->execute([':id' => $uid]);
$certs = $cStmt->fetchAll(PDO::FETCH_ASSOC);

// projects (portfolio)
$pStmt = $pdo->prepare('SELECT title, description, year_completed FROM ContractorProject WHERE contractor_id = :id ORDER BY portfolio_id ASC');
$pStmt->execute([':id' => $uid]);
$projects = $pStmt->fetchAll(PDO::FETCH_ASSOC);

// ratings summary
$rStmt = $pdo->prepare('SELECT AVG(stars) as avg_stars, COUNT(*) as cnt FROM Rating WHERE contractor_id = :id');
$rStmt->execute([':id' => $uid]);
$r = $rStmt->fetch(PDO::FETCH_ASSOC);
$avg = $r && $r['avg_stars'] ? round($r['avg_stars'],1) : null;
$cnt = $r ? (int)$r['cnt'] : 0;

// FIXED: Simplified profile image resolution - use only one clean approach
$profileImage = !empty($u['profile_image']) ? resolveImageWebPath($u['profile_image']) : '/m.jpg';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($u['name'] ?: 'Contractor') ?> — Profile</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

  <style>
    /* Back link style (blue, clearer like ) */
    .breadcrumb { margin: 8px 0 18px; }
    .back-link { color: #0b84ff; text-decoration:none; display:inline-flex; align-items:center; gap:10px; font-weight:600; font-size:14px; }
    .back-link svg { transform: translateY(-1px); opacity:0.95; }

    /* Footer /preserved  original contractors.html) */
    .site-footer{background:linear-gradient(180deg,#1f8b88,#176a99);color:#fff;padding:56px 0 18px;margin-top:0;border-top:1px solid rgba(255,255,255,0.03);}
    .footer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:28px;align-items:start;max-width:1100px;margin:0 auto;padding:0 18px;}
    .site-footer h4{font-size:16px;margin-bottom:12px;color:#fff;}
    .site-footer .muted{color:rgba(255,255,255,0.92);line-height:1.6;}
    .links{list-style:none;padding:0;margin:0;}
    .links li{margin-bottom:8px;opacity:0.95;}
    .links a{color:inherit;text-decoration:none;}
    .contact-item{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
    .site-footer .contact-item svg,
    .site-footer .contact-item svg path,
    .site-footer .contact-item svg circle,
    .site-footer .contact-item svg rect,
    .site-footer .contact-item svg line,
    .site-footer .contact-item svg g {
      fill: currentColor !important;
      stroke: currentColor !important;
      color: rgba(255,255,255,0.95) !important;
    }
    .site-footer .contact-item a{color:rgba(255,255,255,0.95);text-decoration:none;}
    .social-row{margin-top:8px;display:flex;gap:12px;}
    .social-row a{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.95);text-decoration:none;}
    .copyright{color:rgba(255,255,255,0.8);text-align:center;padding:14px 18px;border-top:1px solid rgba(255,255,255,0.04);max-width:1100px;margin:18px auto 0;}
    .floating-help{position:fixed;right:18px;bottom:18px;width:44px;height:44px;border-radius:50%;background:linear-gradient(180deg,#2b9ea2,#1b6d74);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(13,40,44,0.25);z-index:120;}
    .floating-help svg{width:18px;height:18px;fill:#fff;}

    /* Layout tweaks inside profile page (keeps same look) */
    .main-grid{display:grid;grid-template-columns:320px 1fr;gap:28px;align-items:start;max-width:1100px;margin:0 auto;padding:0 18px;}
    .sidebar-card{background:#fff;border-radius:10px;padding:18px;box-shadow:0 6px 20px rgba(12,40,50,0.06);}
    .sidebar-top{display:flex;flex-direction:column;align-items:center;gap:8px;padding-bottom:12px;border-bottom:1px solid #f0f4f6;}
    .profile-circle img{width:120px;height:120px;border-radius:50%;object-fit:cover;}
    .sidebar-name{font-weight:700;font-size:20px;margin-top:6px;}
    .sidebar-specialty{color:#6b7280;margin-bottom:6px;}
    .sidebar-rating{font-weight:600;color:#1f8b88;}
    .book-btn{display:inline-block;margin-top:12px;padding:10px 14px;border-radius:8px;background:#0b84ff;color:#fff;text-decoration:none;font-weight:600;}
    .small-btn{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;background:#f4f6f8;color:#0b4a6c;text-decoration:none;margin-right:8px;}
    .panel{background:#fff;border-radius:10px;padding:18px;margin-bottom:16px;box-shadow:0 6px 18px rgba(12,40,50,0.04);}
    .services-grid .service-list{list-style:disc inside;margin:0;padding-left:14px;}
    .cert-item{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
    .portfolio-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
    .project-card{background:#fff;border-radius:8px;padding:12px;box-shadow:0 6px 18px rgba(12,40,50,0.04);}

    @media (max-width:980px){
      .main-grid{grid-template-columns:1fr; padding:0 12px;}
      .portfolio-grid{grid-template-columns:repeat(2,1fr);}
    }
    @media (max-width:520px){
      .portfolio-grid{grid-template-columns:1fr;}
    }

    /* CTA/footer seam fix */
    .cta-band {
      margin: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      padding-bottom: 52px !important;
      transform: translateZ(0);
    }
    .site-footer.seam-fix { margin-top: -1px !important; }
  </style>
</head>
<body>
  <header class="hero">
    <div class="container narrow">
      <div class="breadcrumb">
        <a class="back-link" href="contractors.php" title="Back to contractors" style="color:#0b84ff !important;">
          <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          ← Back to Contractors
        </a>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="main-grid">
      <aside class="sidebar-card">
        <div class="sidebar-top">
          <div class="profile-circle">
            <!-- FIXED: Use the correct $profileImage variable -->
            <img src="<?= e($profileImage) ?>" alt="<?= e($u['name']) ?>" onerror="this.style.display='none'">
          </div>
          <div class="sidebar-name"><?= e($u['name']) ?></div>
          <div class="sidebar-specialty"><?= e($u['specialization'] ?: '') ?></div>
          <div class="sidebar-rating"><span class="star">★</span> <?= e($avg ?? '—') ?> <span class="small muted">(<?= $cnt ?> reviews)</span></div>
        </div>

        <div class="sidebar-meta" style="margin-top:12px;">
          <div class="meta-row"><svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:8px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"></path></svg> <?= e($u['location'] ?: '—') ?></div>
          <div class="meta-row" style="margin-top:8px;"><svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:8px;"><path d="M6 2v6h12V2zM3 10h18v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7z"/></svg> <?= ($u['experience_years'] !== null && $u['experience_years'] !== '') ? e($u['experience_years'] . ' years Experience') : '—' ?></div>
          <div class="meta-row" style="margin-top:8px;"><svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align:middle;margin-right:8px;"><circle cx="12" cy="12" r="6"/></svg> License: <?= e($u['license_number'] ?: '—') ?></div>
        </div>

        <a class="book-btn" href="mailto:<?= e($u['email'] ?: 'info@mimar.sa') ?>?subject=Project%20Inquiry">Book Contractor</a>

        <div class="action-row" style="margin-top:12px;">
          <?php if (!empty($u['phone'])): ?>
            <a class="small-btn" href="tel:<?= e($u['phone']) ?>"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M6.62 10.79a15.05 15.05 0 0 0 7.2 7.2"/></svg> Call</a>
          <?php endif; ?>
          <a class="small-btn" href="mailto:<?= e($u['email'] ?: 'info@mimar.sa') ?>"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M2 6.5V18a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6.5L12 11 2 6.5z"/></svg> Email</a>
        </div>
      </aside>

      <section>
        <div class="panel">
          <h3>About <?= e($u['name']) ?></h3>
          <p><?= nl2br(e($u['about'] ?: 'No biography available.')) ?></p>
        </div>

        <div class="panel">
          <h3>Contact Information</h3>
          <table class="contact-table" style="width:100%;border-collapse:collapse">
            <tr><td style="padding:8px 10px;width:160px;font-weight:600">Name</td><td style="padding:8px 10px;"><?= e($u['name']) ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">Phone</td><td style="padding:8px 10px;"><?= e($u['phone'] ?: '—') ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">Email</td><td style="padding:8px 10px;"><?= e($u['email'] ?: '—') ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">Specialization</td><td style="padding:8px 10px;"><?= e($u['specialization'] ?: '—') ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">License Number</td><td style="padding:8px 10px;"><?= e($u['license_number'] ?: '—') ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">Location</td><td style="padding:8px 10px;"><?= e($u['location'] ?: '—') ?></td></tr>
            <tr><td style="padding:8px 10px;font-weight:600">Evaluation Score</td><td style="padding:8px 10px;"><span class="star">★</span> <?= e($avg ?? '—') ?> / 5.0 (<?= $cnt ?>)</td></tr>
          </table>
        </div>

        <div class="panel">
          <h3>Services Offered</h3>
          <div class="services-grid">
            <?php if (!empty($services)): ?>
              <ul class="service-list">
                <?php foreach ($services as $s): ?><li><?= e($s['service_name']) ?><?= !empty($s['details']) ? ' — ' . e($s['details']) : '' ?></li><?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="muted">No services listed.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel">
          <h3>Certifications</h3>
          <div class="cert-list">
            <?php if (!empty($certs)): foreach ($certs as $c): ?>
              <div class="cert-item"><svg viewBox="0 0 24 24" width="18" height="18"><circle cx="12" cy="12" r="6"/></svg> <?= e($c['cert_name']) ?> <?= !empty($c['issuer']) ? ' — ' . e($c['issuer']) : '' ?></div>
            <?php endforeach; else: ?>
              <p class="muted">No certifications listed.</p>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>
  </main>

  <section class="portfolio-band" style="margin-top:20px;">
    <div class="portfolio-inner" style="max-width:1100px;margin:0 auto;padding:0 18px;">
      <h2>Portfolio</h2>
      <p>Browse through some of <?= e($u['name']) ?>'s completed projects.</p>

      <div class="portfolio-grid">
        <?php if (!empty($projects)): foreach ($projects as $proj): ?>
          <div class="project-card">
            <img src="./proj1.jpg" alt="<?= e($proj['title']) ?>" onerror="this.style.display='none'" style="width:100%;height:140px;object-fit:cover;border-radius:6px;">
            <div style="padding-top:8px;font-weight:600;"><?= e($proj['title']) ?></div>
            <div style="font-size:13px;color:#6b7280;"><?= e($proj['description'] ?: '') ?> <?= !empty($proj['year_completed']) ? ' — ' . e($proj['year_completed']) : '' ?></div>
          </div>
        <?php endforeach; else: ?>
          <div class="muted">No portfolio items available.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- CTA band (preserved) -->
  <section class="cta-band" style="margin-top:28px;">
    <div class="container narrow cta-inner">
      <div>
        <h2>Are You a Contractor?</h2>
        <p>Join our platform and connect with clients looking for reliable construction services.</p>
      </div>
      <div>
        <a class="btn ghost big" href="#">Get Started</a>
      </div>
    </div>
  </section>

  <!-- FOOTER (copied from contractors.html to preserve exact icons and look) -->
  <footer class="site-footer seam-fix">
    <div class="container footer-grid">
      <div class="col">
        <h4>Mimar</h4>
        <p class="muted">We build trust. We build the future. Connecting clients with trusted construction contractors in Saudi Arabia.</p>
      </div>

      <div class="col">
        <h4>Quick Links</h4>
        <ul class="links">
          <li><a href="#">About Us</a></li>
          <li><a href="#">Projects</a></li>
          <li><a href="#">Contractors</a></li>
          <li><a href="#">Contact</a></li>
        </ul>
      </div>

      <div class="col">
        <h4>Services</h4>
        <ul class="links">
          <li>Residential Construction</li>
          <li>Commercial Building</li>
          <li>Renovation</li>
          <li>Infrastructure</li>
        </ul>
      </div>

      <div class="col contact-col" aria-label="Contact information">
        <h4>Contact Us</h4>
        <div class="contact-item">
          <!-- email icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>
          </svg>
          <a href="mailto:info@mimar.sa">info@mimar.sa</a>
        </div>

        <div class="contact-item">
          <!-- phone icon -->
          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 4a1 1 0 0 1 1-1h2.5a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.01l-2.21 2.21z"/>
          </svg>
          <a href="tel:+966112345678">+966 11 234 5678</a>
        </div>

        <div class="social-row" aria-hidden="false">
          <a href="#" aria-label="Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">
              <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 6.5A4.5 4.5 0 1 0 16.5 13 4.5 4.5 0 0 0 12 8.5zm6.5-.9a1.1 1.1 0 1 1-1.1-1.1 1.1 1.1 0 0 1 1.1 1.1zM12 10.5A1.5 1.5 0 1 1 10.5 12 1.5 1.5 0 0 1 12 10.5z"/>
            </svg>
          </a>
          <a href="#" aria-label="Messages/Chat">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="currentColor">
              <path d="M21 6a2 2 0 0 0-2-2H5C3.9 4 3 4.9 3 6v9a2 2 0 0 0 2 2h11l4 4V6z"/>
            </svg>
          </a>
        </div>
      </div>
    </div>

    <div class="container">
      <div class="copyright">© <?= date('Y') ?> Mimar. All rights reserved. | Terms & Conditions | Privacy Policy</div>
    </div>

    <div class="floating-wrapper" aria-live="polite">
      <a href="#" class="floating-help" id="floatingBtn" aria-label="Open chat">
        <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true" focusable="false">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  </footer>
</body>
</html>