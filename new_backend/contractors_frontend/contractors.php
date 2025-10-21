<?php
// contractors.php
// Dynamic contractors list page (replacement for contractors.html)
// Requires: config.php with getPDO() returning a PDO connection.
// Preserves original HTML/CSS and footer exactly as in your original contractors.html.
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

// Fetch contractors and their profile data + rating summary
$sql = "
  SELECT u.user_id, u.name, u.phone, u.email,
         p.specialization, p.license_number, p.location, p.experience_years, p.profile_image, p.created_at,
         COALESCE(ROUND(avg_r.avg_stars,1), '') AS avg_rating,
         COALESCE(rc.review_count, 0) AS review_count
  FROM `User` u
  LEFT JOIN ContractorProfile p ON p.contractor_id = u.user_id
  LEFT JOIN (
    SELECT contractor_id, AVG(stars) AS avg_stars
    FROM Rating GROUP BY contractor_id
  ) AS avg_r ON avg_r.contractor_id = u.user_id
  LEFT JOIN (
    SELECT contractor_id, COUNT(*) AS review_count
    FROM Rating GROUP BY contractor_id
  ) AS rc ON rc.contractor_id = u.user_id
  WHERE u.role = 'contractor'
  ORDER BY p.created_at DESC, u.name ASC
  LIMIT 500
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper to escape
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Find Contractors</title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">

  <style>
    /* Search icon inside input */
    .search-box .search-icon{
      position:absolute;
      left:14px;
      top:50%;
      transform:translateY(-50%);
      width:20px;
      height:20px;
      pointer-events:none;
      color:rgba(0,0,0,0.45);
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .search-box input{padding-left:46px;}

    .search-box{position:relative;display:flex;align-items:center;max-width:720px;margin:0 auto;}
    .search-box .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:rgba(0,0,0,0.45);pointer-events:none;}
    .search-box input{padding:12px 14px 12px 42px;border-radius:10px;border:1px solid #e6e6e6;width:100%;font-family:inherit;box-shadow:none;}

    /* Footer (copied from your original contractors.html) */
    .site-footer{background:linear-gradient(180deg,#1f8b88,#176a99);color:#fff;padding:56px 0 18px;margin-top:0;border-top:1px solid rgba(255,255,255,0.03);}
    .footer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:28px;align-items:start;max-width:1100px;margin:0 auto;padding:0 18px;}
    .site-footer h4{font-size:16px;margin-bottom:12px;color:#fff;}
    .site-footer .muted{color:rgba(255,255,255,0.92);line-height:1.6;}
    .links{list-style:none;padding:0;margin:0;}
    .links li{margin-bottom:8px;opacity:0.95;}
    .links a{color:inherit;text-decoration:none;}
    .contact-item{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
    /* Force footer contact icons to light color (and make social icons inherit currentColor) */
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
    /* Floating help/chat icon */
    .floating-help{position:fixed;right:18px;bottom:18px;width:44px;height:44px;border-radius:50%;background:linear-gradient(180deg,#2b9ea2,#1b6d74);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(13,40,44,0.25);z-index:120;}
    .floating-help svg{width:18px;height:18px;fill:#fff;}
    /* small styles for the custom selects */
    .select { position: relative; display:inline-block; }
    .select summary { list-style:none; cursor:pointer; padding:10px 12px; border-radius:8px; background:#fff; display:inline-flex; align-items:center; gap:8px; border:1px solid #eee; }
    .select summary .caret{margin-left:8px; color:#6b7280;}
    .select .select-menu { position:absolute; z-index:50; margin-top:6px; left:0; min-width:220px; background:#fff; border-radius:8px; box-shadow:0 6px 18px rgba(10,10,10,0.08); padding:8px 6px; display:none; list-style:none; max-height:260px; overflow:auto; }
    .select[open] .select-menu{display:block;}
    .select .option{padding:8px 10px;display:flex;align-items:center;gap:8px;border-radius:6px;cursor:pointer;}
    .select .option:hover{background:rgba(0,0,0,0.04);}
    .select .option a{color:inherit;text-decoration:none;width:100%;display:block;}
    @media (max-width:880px){
      .footer-grid{grid-template-columns:repeat(2,1fr);padding:0 12px;}
    }
    @media (max-width:520px){
      .footer-grid{grid-template-columns:1fr;row-gap:18px;padding:0 10px;}
      .search-box{max-width:100%;padding:0 10px;}
    }

    /* Strong CTA/footer seam fix (forces flush layout, removes shadows that create seams) */
    .cta-band {
      margin: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      padding-bottom: 52px !important;
      transform: translateZ(0);
    }

    .site-footer {
      margin-top: 0 !important;
      border-top: none !important;
      position: relative;
      z-index: 1;
      box-shadow: none !important;
    }

    /* If a tiny 1px seam persists due to subpixel rounding, the seam-fix class pulls footer up */
    .site-footer.seam-fix { margin-top: -1px !important; }
  </style>
</head>
<body>
  <!-- HERO / title area -->
  <header class="hero">
    <div class="container narrow">
      <h1 class="title">Find Contractors</h1>
      <p class="subtitle">Connect with verified, licensed contractors across Saudi Arabia for all your construction needs.</p>

      <div class="search-row">
        <!-- SEARCH BOX: search icon placed inside the input area -->
        <div class="search-box" role="search" aria-label="Search contractors">
          <!-- icon is inside the search-box and positioned over the input via CSS -->
          <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="20" height="20" role="img" xmlns="http://www.w3.org/2000/svg">
            <circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></circle>
            <path d="M21 21l-4.3-4.3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"></path>
          </svg>

          <input id="searchInput" type="text" placeholder="Search contractors or specialties..." aria-label="Search contractors">
        </div>

        <div class="filters">
          <!-- Specialties select (this one will drive filtering) -->
          <details id="specialtiesSelect" class="select" role="listbox" aria-label="Specialties">
            <summary class="select-btn" aria-haspopup="listbox" aria-expanded="false">
              <svg class="sel-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden>
                <path d="M3 11l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-8z" fill="currentColor"/>
              </svg>
              <span id="specialtiesLabel" class="sel-label">All Specialties</span>
              <span class="caret">▾</span>
            </summary>
            <ul class="select-menu" role="list">
              <li class="option" role="option" data-value="All Specialties"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 11l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-8z" fill="currentColor"/></svg><span>All Specialties</span></li>
              <li class="option" role="option" data-value="Residential"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 3l8 6v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9l8-6zM9 13h6v6H9v-6z" fill="currentColor"/></svg><span>Residential</span></li>
              <li class="option" role="option" data-value="Commercial"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 21h16v-2H4v2zM6 7h3v10H6zM10 4h3v13h-3zM14 10h3v7h-3z" fill="currentColor"/></svg><span>Commercial</span></li>
              <li class="option" role="option" data-value="Renovation"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M21 13v6a1 1 0 0 1-1 1h-4v-7l5 0zM3 13h5v8H4a1 1 0 0 1-1-1v-7zM10 3h4v10h-4z" fill="currentColor"/></svg><span>Renovation</span></li>
              <li class="option" role="option" data-value="Infrastructure"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2l4 4-4 4-4-4 4-4zm0 8v12" stroke="currentColor" stroke-width="1.2" fill="none"/></svg><span>Infrastructure</span></li>
            </ul>
          </details>

          <!-- Cities select (now clickable links that filter cards by city) -->
          <details id="citiesSelect" class="select" role="listbox" aria-label="Cities">
            <summary class="select-btn" aria-haspopup="listbox" aria-expanded="false">
              <svg class="sel-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden>
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/>
              </svg>
              <span id="citiesLabel" class="sel-label">All Cities</span>
              <span class="caret">▾</span>
            </summary>
            <ul class="select-menu" role="list">
              <li class="option" role="option" data-value="All Cities"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg><span>All Cities</span></li>
              <li class="option" role="option" data-value="Riyadh"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg><span>Riyadh</span></li>
              <li class="option" role="option" data-value="Jeddah"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg><span>Jeddah</span></li>
              <li class="option" role="option" data-value="Dammam"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg><span>Dammam</span></li>
            </ul>
          </details>
        </div>
      </div>
    </div>
  </header>

 <!-- MAIN content -->
  <main class="container">
    <section id="cardsGrid" class="cards-grid">
<?php
// For each contractor, render a card similar to original static layout
foreach ($contractors as $c):
    $uid = (int)$c['user_id'];
    $name = $c['name'] ?? 'Unknown';
    $spec = $c['specialization'] ?? 'General Contractor';
    $city = $c['location'] ?? '—';
    $years = ($c['experience_years'] !== null && $c['experience_years'] !== '') ? ((int)$c['experience_years']) : null;
    $yearsText = $years ? "{$years} years experience" : '';
    $license = $c['license_number'] ?? '—';
    $rating = $c['avg_rating'] !== '' ? e($c['avg_rating']) : '—';
    $reviews = (int)$c['review_count'];
    
    // FIXED: Resolve image path for each contractor individually
    $imgWeb = !empty($c['profile_image']) ? resolveImageWebPath($c['profile_image']) : '/m.jpg';
    
    // badges
    $badge = '';
    if (is_numeric($c['avg_rating']) && $c['avg_rating'] >= 4.85) $badge = '<span class="badge top-rated">Top Rated</span>';
    elseif (!empty($c['experience_years']) && $c['experience_years'] >= 12) $badge = '<span class="badge expert">Expert</span>';
?>
      <article class="card" data-specialty="<?= e($spec ?: '') ?>" data-city="<?= e($city ?: '') ?>">
        <?= $badge ?>
        <div class="avatar">
          <img src="<?= e($imgWeb) ?>" alt="<?= e($name) ?>" onerror="this.style.display='none'">
        </div>

        <h3 class="name"><?= e($name) ?></h3>
        <div class="specialty"><?= e($spec) ?></div>

        <div class="meta-row">
          <div class="meta"><svg class="meta-icon" viewBox="0 0 24 24"><path d="M12 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM4 20a8 8 0 0 1 16 0H4z"/></svg> <?= e($city) ?></div>
          <div class="meta"><svg class="meta-icon" viewBox="0 0 24 24"><path d="M6 2v6h12V2zM3 10h18v7a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-7z"/></svg> <?= e($yearsText) ?></div>
        </div>

        <div class="rating">★ <?= $rating ?> <span class="small">(<?= $reviews ?>)</span></div>
        <div class="license">License: <?= e($license) ?></div>
        <a class="btn profile-btn" href="profile.php?id=<?= $uid ?>">View Profile</a>
      </article>
<?php endforeach; ?>
    </section>
  </main>

  <!-- CTA band -->
  <section class="cta-band">
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

  <!-- FOOTER (copied from your original contractors.html) -->
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

    <!-- floating help/chat button (copied exactly) -->
    <div class="floating-wrapper" aria-live="polite">
      <a href="#" class="floating-help" id="floatingBtn" aria-label="Open chat">
        <!-- chat outline icon (white stroke) -->
        <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true" focusable="false">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </a>
    </div>
  </footer>

  <!-- SCRIPT: filtering & select behavior (kept original logic) -->
  <script>
    (function () {
      function normalize(val) { return (val || '').toString().trim().toLowerCase(); }

      const specialtiesDetails = document.getElementById('specialtiesSelect');
      const specialtiesLabel = document.getElementById('specialtiesLabel');
      const specialtiesOptions = specialtiesDetails.querySelectorAll('.select-menu .option');

      const citiesDetails = document.getElementById('citiesSelect');
      const citiesLabel = document.getElementById('citiesLabel');
      const citiesOptions = citiesDetails.querySelectorAll('.select-menu .option');

      const cards = document.querySelectorAll('#cardsGrid .card');
      const searchInput = document.getElementById('searchInput');

      function filterCards() {
        const specNorm = normalize(specialtiesLabel.textContent || '');
        const cityNorm = normalize(citiesLabel.textContent || '');
        const q = normalize(searchInput.value || '');

        cards.forEach(c => {
          const spec = normalize(c.dataset.specialty || '');
          const city = normalize(c.dataset.city || '');
          const name = normalize(c.querySelector('.name')?.textContent || '');

          let show = true;

          if (specNorm && specNorm !== 'all specialties' && specNorm !== 'all') {
            if (spec !== specNorm) show = false;
          }

          if (cityNorm && cityNorm !== 'all cities' && cityNorm !== 'all') {
            if (city !== cityNorm) show = false;
          }

          if (q && q.length > 0) {
            if (!(name.includes(q) || spec.includes(q) || city.includes(q))) show = false;
          }

          c.style.display = show ? '' : 'none';
        });
      }

      specialtiesOptions.forEach(option => {
        option.addEventListener('click', function (e) {
          const value = this.dataset.value || this.textContent.trim();
          specialtiesLabel.textContent = value;
          if (specialtiesDetails.hasAttribute('open')) specialtiesDetails.removeAttribute('open');
          filterCards();
        });
      });

      citiesOptions.forEach(option => {
        option.addEventListener('click', function (e) {
          const value = this.dataset.value || this.textContent.trim();
          citiesLabel.textContent = value;
          if (citiesDetails.hasAttribute('open')) citiesDetails.removeAttribute('open');
          filterCards();
        });
      });

      document.addEventListener('click', (e) => {
        if (!specialtiesDetails.contains(e.target)) specialtiesDetails.removeAttribute('open');
        if (!citiesDetails.contains(e.target)) citiesDetails.removeAttribute('open');
      });

      const binds = [
        { details: specialtiesDetails, summary: specialtiesDetails.querySelector('summary') },
        { details: citiesDetails, summary: citiesDetails.querySelector('summary') }
      ];
      binds.forEach(b => {
        if (!b || !b.details) return;
        b.details.addEventListener('toggle', () => {
          if (b.summary) b.summary.setAttribute('aria-expanded', b.details.hasAttribute('open'));
        });
      });

      searchInput.addEventListener('input', function () {
        filterCards();
      });

      specialtiesLabel.textContent = specialtiesLabel.textContent || 'All Specialties';
      citiesLabel.textContent = citiesLabel.textContent || 'All Cities';
      filterCards();
    })();
  </script>
</body>
</html>