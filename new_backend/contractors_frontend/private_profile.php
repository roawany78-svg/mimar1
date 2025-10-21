<?php 
if (!defined('SILENT_INCLUDE')) define('SILENT_INCLUDE', true);
ob_start();
require_once __DIR__ . '/ContractorProfile.php';
ob_end_clean();

// now call getPDO() as before (ensure the function exists)
if (!function_exists('getPDO')) {
    echo "<p style='color:crimson;padding:18px'>Error: getPDO() not defined in ContractorProfile.php</p>";
    exit;
}
$pdo = getPDO();

// ---------- helpers ----------
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function safeFilename($name){
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/[^A-Za-z0-9_\-]/','_', $base);
    $base = substr($base,0,80);
    return $base . ($ext ? '.' . strtolower($ext) : '');
}

// Resolve a stored DB value into a web-accessible path (best-effort).
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
    // If DB stores "/uploads/..." but files are under a subfolder like "/new_backend/uploads/..."
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


// Resolve a server filesystem path for deletion/checking (best-effort)
function resolveServerPathForStored(string $stored): string {
    $stored = trim($stored);
    if ($stored === '') return '';
    if (preg_match('#^https?://#i', $stored)) return ''; // remote URL -> no local file
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');

    if ($stored[0] === '/') {
        if ($docRoot !== '') {
            return $docRoot . $stored;
        }
        return __DIR__ . DIRECTORY_SEPARATOR . ltrim($stored, '/\\');
    }

    // Try script-relative first
    $cand1 = __DIR__ . DIRECTORY_SEPARATOR . ltrim($stored, '/\\');
    if (is_file($cand1)) return $cand1;

    // Then docroot-based
    if ($docRoot !== '') {
        $cand2 = $docRoot . '/' . ltrim($stored, '/\\');
        if (is_file($cand2)) return $cand2;
    }

    // Fallback to script-relative path (may not exist)
    return $cand1;
}

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
$ALLOWED = ['image/jpeg','image/png','image/webp','image/gif'];
$MAX_BYTES = 4 * 1024 * 1024; // 4MB

// ---------- get user id ----------
$userId = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : 0;
if ($userId <= 0) {
    echo "<p style='font-family:Inter,system-ui,sans-serif;padding:28px'>Missing or invalid <code>user_id</code>. Use <code>?user_id=...</code></p>";
    exit;
}

// ---------- simple message store ----------
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    // basic check: posted user_id must match url
    $postedId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($postedId !== $userId) {
        $errors[] = 'Form user_id does not match page user_id.';
    } else {
        try {
            if ($action === 'save_personal') {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $specialization = trim($_POST['specialization'] ?? '');
                $license_number = trim($_POST['license_number'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $about = trim($_POST['about'] ?? '');
                $experience_years = ($_POST['experience_years'] !== '') ? (int)$_POST['experience_years'] : null;

                // optional profile image upload
                $profile_image_path = null;
                if (!empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $f = $_FILES['profile_image'];
                    if ($f['size'] > $MAX_BYTES) throw new Exception('Profile image too large (max 4MB).');
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $f['tmp_name']);
                    finfo_close($finfo);
                    if (!in_array($mime, $ALLOWED, true)) throw new Exception('Unsupported image type.');
                    $destName = $userId . '_profile_' . time() . '_' . safeFilename($f['name']);
                    $dest = $uploadDir . '/' . $destName;
                    if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Failed to move uploaded file.');
                    // store path relative to web root or script (prefer 'uploads/...' relative)
                    $profile_image_path = 'uploads/' . $destName;
                }

                // update User table
                $pdo->prepare("UPDATE `User` SET name = :name, email = :email, phone = :phone WHERE user_id = :id")
                    ->execute([':name'=>$name,':email'=>$email,':phone'=>$phone,':id'=>$userId]);

                // upsert ContractorProfile
                $chk = $pdo->prepare("SELECT contractor_id FROM ContractorProfile WHERE contractor_id = :id LIMIT 1");
                $chk->execute([':id'=>$userId]);
                if ($chk->fetch()) {
                    $sql = "UPDATE ContractorProfile SET specialization = :spec, license_number = :lic, location = :loc, about = :about, experience_years = :exp";
                    if ($profile_image_path) $sql .= ", profile_image = :pimg";
                    $sql .= " WHERE contractor_id = :id";
                    $params = [':spec'=>$specialization,':lic'=>$license_number,':loc'=>$location,':about'=>$about,':exp'=>$experience_years,':id'=>$userId];
                    if ($profile_image_path) $params[':pimg'] = $profile_image_path;
                    $pdo->prepare($sql)->execute($params);
                } else {
                    $baseCols = "contractor_id, specialization, license_number, location, about, experience_years";
                    $baseVals = ":id, :spec, :lic, :loc, :about, :exp";
                    if ($profile_image_path) {
                        $baseCols .= ", profile_image";
                        $baseVals .= ", :pimg";
                    }
                    $pdo->prepare("INSERT INTO ContractorProfile ({$baseCols}) VALUES ({$baseVals})")
                        ->execute(array_merge(
                            [':id'=>$userId,':spec'=>$specialization,':lic'=>$license_number,':loc'=>$location,':about'=>$about,':exp'=>$experience_years],
                            $profile_image_path ? [':pimg'=>$profile_image_path] : []
                        ));
                }
                $messages[] = "Personal information saved.";
            }

            elseif ($action === 'save_services') {
                $items = isset($_POST['services']) && is_array($_POST['services']) ? array_map('trim', $_POST['services']) : [];
                $pdo->prepare("DELETE FROM ContractorService WHERE contractor_id = :id")->execute([':id'=>$userId]);
                $ins = $pdo->prepare("INSERT INTO ContractorService (contractor_id, service_name, details) VALUES (:id, :name, NULL)");
                foreach ($items as $s) {
                    if ($s === '') continue;
                    $ins->execute([':id'=>$userId,':name'=>mb_substr($s,0,200)]);
                }
                $messages[] = "Services updated.";
            }

            elseif ($action === 'save_certs') {
                $items = isset($_POST['certs']) && is_array($_POST['certs']) ? array_map('trim', $_POST['certs']) : [];
                $pdo->prepare("DELETE FROM ContractorCertification WHERE contractor_id = :id")->execute([':id'=>$userId]);
                $ins = $pdo->prepare("INSERT INTO ContractorCertification (contractor_id, cert_name) VALUES (:id, :name)");
                foreach ($items as $c) {
                    if ($c === '') continue;
                    $ins->execute([':id'=>$userId,':name'=>mb_substr($c,0,300)]);
                }
                $messages[] = "Certifications updated.";
            }

            elseif ($action === 'save_projects') {
                $titles = $_POST['project_title'] ?? [];
                $descs  = $_POST['project_desc'] ?? [];
                $years  = $_POST['project_year'] ?? [];
                $pdo->prepare("DELETE FROM ContractorProject WHERE contractor_id = :id")->execute([':id'=>$userId]);
                $ins = $pdo->prepare("INSERT INTO ContractorProject (contractor_id, title, description, year_completed) VALUES (:id, :title, :desc, :year)");
                for ($i=0;$i<count($titles);$i++){
                    $t = trim($titles[$i] ?? '');
                    if ($t === '') continue;
                    $d = trim($descs[$i] ?? '');
                    $y = trim($years[$i] ?? '') ?: null;
                    $ins->execute([':id'=>$userId,':title'=>mb_substr($t,0,300),':desc'=>mb_substr($d,0,2000),':year'=>$y]);
                }
                $messages[] = "Projects updated.";
            }

            elseif ($action === 'upload_asset') {
                if (empty($_FILES['asset_file']['name']) || $_FILES['asset_file']['error'] !== UPLOAD_ERR_OK) throw new Exception('No file chosen or upload error.');
                $f = $_FILES['asset_file'];
                if ($f['size'] > $MAX_BYTES) throw new Exception('File too large.');
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $ALLOWED, true)) throw new Exception('Unsupported file type.');
                $destName = $userId . '_asset_' . time() . '_' . safeFilename($f['name']);
                $dest = $uploadDir . '/' . $destName;
                if (!move_uploaded_file($f['tmp_name'], $dest)) throw new Exception('Failed saving file.');
                $path = 'uploads/' . $destName;
                $atype = in_array($_POST['asset_type'] ?? '', ['portfolio','profile','cert']) ? $_POST['asset_type'] : 'portfolio';
                $caption = trim($_POST['asset_caption'] ?? '');
                $pdo->prepare("INSERT INTO ContractorAsset (contractor_id, asset_type, file_path, caption) VALUES (:cid,:atype,:fp,:cap)")
                    ->execute([':cid'=>$userId,':atype'=>$atype,':fp'=>$path,':cap'=>mb_substr($caption,0,300)]);
                $messages[] = "Image uploaded.";
            }

            elseif ($action === 'delete_asset') {
                $aid = (int)($_POST['asset_id'] ?? 0);
                if ($aid <= 0) throw new Exception('Invalid asset id.');
                $stmt = $pdo->prepare("SELECT file_path FROM ContractorAsset WHERE asset_id = :aid AND contractor_id = :cid");
                $stmt->execute([':aid'=>$aid,':cid'=>$userId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['file_path'])) {
                    $fp = resolveServerPathForStored($row['file_path']);
                    if ($fp && is_file($fp)) @unlink($fp);
                }
                $pdo->prepare("DELETE FROM ContractorAsset WHERE asset_id = :aid AND contractor_id = :cid")->execute([':aid'=>$aid,':cid'=>$userId]);
                $messages[] = "Image removed.";
            }

            elseif ($action === 'delete_account') {
                $confirm = trim($_POST['confirm_text'] ?? '');
                if ($confirm !== 'DELETE') throw new Exception('Type DELETE (all-caps) to confirm.');
                // delete related assets files
                $ast = $pdo->prepare("SELECT file_path FROM ContractorAsset WHERE contractor_id = :uid");
                $ast->execute([':uid'=>$userId]);
                while ($a = $ast->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($a['file_path'])) {
                        $fp = resolveServerPathForStored($a['file_path']);
                        if ($fp && is_file($fp)) @unlink($fp);
                    }
                }
                // delete profile image
                $pr = $pdo->prepare("SELECT profile_image FROM ContractorProfile WHERE contractor_id = :uid");
                $pr->execute([':uid'=>$userId]);
                if ($r = $pr->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($r['profile_image'])) {
                        $fp = resolveServerPathForStored($r['profile_image']);
                        if ($fp && is_file($fp)) @unlink($fp);
                    }
                }
                // delete user (assumes cascade FK in schema)
                $pdo->prepare("DELETE FROM `User` WHERE user_id = :id")->execute([':id'=>$userId]);
                $messages[] = "Account deleted. ";
            } else {
                throw new Exception('Unknown action.');
            }
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }
}

// ---------- fetch fresh data ----------
$sth = $pdo->prepare("SELECT u.user_id, u.name, u.email, u.phone, p.* FROM `User` u LEFT JOIN ContractorProfile p ON p.contractor_id = u.user_id WHERE u.user_id = :id LIMIT 1");
$sth->execute([':id'=>$userId]);
$me = $sth->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    echo "<p style='font-family:Inter,system-ui,sans-serif;padding:28px'>User not found (user_id=" . e($userId) . ").</p>";
    exit;
}

$services = $pdo->prepare("SELECT * FROM ContractorService WHERE contractor_id = :id ORDER BY service_id ASC");
$services->execute([':id'=>$userId]); $services = $services->fetchAll(PDO::FETCH_ASSOC);

$certs = $pdo->prepare("SELECT * FROM ContractorCertification WHERE contractor_id = :id ORDER BY cert_id ASC");
$certs->execute([':id'=>$userId]); $certs = $certs->fetchAll(PDO::FETCH_ASSOC);

$projects = $pdo->prepare("SELECT * FROM ContractorProject WHERE contractor_id = :id ORDER BY portfolio_id ASC");
$projects->execute([':id'=>$userId]); $projects = $projects->fetchAll(PDO::FETCH_ASSOC);

$assets = $pdo->prepare("SELECT * FROM ContractorAsset WHERE contractor_id = :id ORDER BY uploaded_at DESC");
$assets->execute([':id'=>$userId]); $assets = $assets->fetchAll(PDO::FETCH_ASSOC);

// ---------- convenience: resolved profile image web path ----------
$profileImageWeb = '';
if (!empty($me['profile_image'])) {
    $profileImageWeb = resolveImageWebPath($me['profile_image']);
}
// Provide a default fallback file (place your default avatar at this path)
$defaultAvatarPath = '/assets/default-avatar.png';
if ($profileImageWeb === '') $profileImageWeb = $defaultAvatarPath;

// ---------- render page ----------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Dashboard — <?= e($me['name'] ?? 'Contractor') ?></title>
<link rel="stylesheet" href="styles_private.css">
<style>
  /* small inline tweaks used by this template */
  .layout { max-width:1200px;margin:28px auto;display:flex;gap:28px;padding:0 18px;align-items:flex-start; }
  .sidebar { width:320px; position:sticky; top:18px; align-self:flex-start; }
  .profile-photo { width:110px;height:110px;border-radius:999px;object-fit:cover;border:4px solid #f3fafb;box-shadow:0 6px 18px rgba(12,40,50,0.06); }
  .navlink { display:block;padding:10px 12px;border-radius:8px;color:#0b2b34;text-decoration:none;margin-bottom:8px; }
  .navlink.active, .navlink:hover { background:linear-gradient(90deg,#eef9ff,#f2fbff);color:var(--accent); box-shadow:0 6px 16px rgba(11,132,255,0.06); }
  .content { flex:1; min-width:0; }
  section.manage-section { margin-bottom:18px; }
  .section-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px; }
  .section-title { font-size:18px;font-weight:700;color:#0b2b34; }
  .muted { color:#6b7280;font-size:13px; }
  .form-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  @media (max-width:980px){ .layout{flex-direction:column} .sidebar{position:static;width:100%} .form-grid{grid-template-columns:1fr} }
  .card { background:#fff; border-radius:12px; padding:18px; box-shadow:0 8px 28px rgba(9,30,36,0.04); }
  .btn { background:#0b69ff;color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer; }
  .btn.save{ background:linear-gradient(90deg,#0b69ff,#2a9df4); }
  .btn.danger{ background:#b91c1c; }
  .btn-icon{ background:#0b69ff;color:#fff;border:none;padding:6px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center; }
  .btn-icon.add{ background:#06a762; }
  .btn-icon.remove{ background:#fff;border:1px solid #eee;color:#dc4b4b;padding:6px; }
  .btn.small{ padding:6px 8px;font-size:13px; }
</style>
</head>
<body>
  <div style="background:linear-gradient(180deg,#f7fcff,#fbfdff);padding:18px 0;border-bottom:1px solid rgba(12,40,50,0.03)">
    <div class="layout" style="max-width:1200px;margin:0 auto;">
      <div style="display:flex;align-items:center;gap:12px;">
        <a href="/new_backend/contractors_frontend/contractors.php" style="text-decoration:none;color:var(--accent);font-weight:700;">← Back to Contractors</a>
        <div style="color:#6b7280;font-size:14px;"></div>
      </div>
      <div style="margin-left:auto;color:#6b7280;font-size:13px;">Contractor ID: <strong><?= e($userId) ?></strong></div>
    </div>
  </div>

  <div class="layout">
    <!-- LEFT SIDEBAR -->
    <aside class="sidebar">
      <div class="card">
        <?php if (!empty($profileImageWeb)): ?>
          <img src="<?= e($profileImageWeb) ?>" alt="Profile" class="profile-photo" onerror="this.style.display='none'">
        <?php else: ?>
          <img src="m.jpg" alt="Profile" class="profile-photo" onerror="this.style.display='none'">
        <?php endif; ?>

        <div style="margin-top:12px;">
          <div style="font-weight:700;font-size:18px;color:#0b2b34"><?= e($me['name'] ?? '') ?></div>
          <div class="muted" style="margin-top:4px;"><?= e($me['specialization'] ?? 'Contractor') ?></div>
        </div>

        <div style="margin-top:14px;">
          <?php if (!empty($me['email'])): ?><div class="muted">✉ <a href="mailto:<?= e($me['email']) ?>" style="color:var(--accent);text-decoration:none"><?= e($me['email']) ?></a></div><?php endif; ?>
          <?php if (!empty($me['phone'])): ?><div class="muted">☎ <?= e($me['phone']) ?></div><?php endif; ?>
          <?php if (!empty($me['license_number'])): ?><div class="muted">🔒 <?= e($me['license_number']) ?></div><?php endif; ?>
        </div>

        <nav style="margin-top:16px;">
          <a class="navlink" href="#personal" data-target="personal">Personal Info</a>
          <a class="navlink" href="#services" data-target="services">Services</a>
          <a class="navlink" href="#certs" data-target="certs">Certifications</a>
          <a class="navlink" href="#projects" data-target="projects">Projects</a>
          <a class="navlink" href="#assets" data-target="assets">Assets</a>
          <a class="navlink" href="#danger" data-target="danger" style="color:#b91c1c">Danger Zone</a>
        </nav>
      </div>

      <div style="height:18px"></div>

      <div class="card" style="margin-top:12px;">
        <div style="font-weight:700;margin-bottom:8px">Quick Actions</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="#personal" class="btn small" style="background:var(--accent)">Edit Personal</a>
          <a href="#services" class="btn small" style="background:#06a762">Manage Services</a>
        </div>
      </div>
    </aside>

    <!-- RIGHT CONTENT -->
    <main class="content">
      <?php foreach ($messages as $m): ?>
        <div class="flash success" style="margin-bottom:12px;padding:12px;border-radius:10px;background:#ecfdf5;color:var(--success);"><?= e($m) ?></div>
      <?php endforeach; ?>
      <?php foreach ($errors as $er): ?>
        <div class="flash error" style="margin-bottom:12px;padding:12px;border-radius:10px;background:#fff5f5;color:var(--danger);"><?= e($er) ?></div>
      <?php endforeach; ?>

<!-- Personal Info -->
<section id="personal" class="manage-section card">
  <div class="section-header">
    <div class="section-title">Personal Information</div>
    <div class="muted">Update your public profile details</div>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_personal">
    <input type="hidden" name="user_id" value="<?= e($userId) ?>">

    <div class="form-grid-personal">
      <!-- Name -->
      <div class="form-row">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= e($me['name'] ?? '') ?>" placeholder="Full name">
      </div>

      <!-- Email -->
      <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($me['email'] ?? '') ?>" placeholder="Email">
      </div>

      <!-- Phone -->
      <div class="form-row">
        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="+966 ...">
      </div>

      <!-- Location -->
      <div class="form-row">
        <label for="location">Location (City)</label>
        <input type="text" id="location" name="location" value="<?= e($me['location'] ?? '') ?>" placeholder="Riyadh">
      </div>

      <!-- Specialization -->
      <div class="form-row">
        <label for="specialization">Specialization</label>
        <input type="text" id="specialization" name="specialization" value="<?= e($me['specialization'] ?? '') ?>" placeholder="e.g., Residential, Interior">
      </div>

      <!-- License Number -->
      <div class="form-row">
        <label for="license_number">License Number</label>
        <input type="text" id="license_number" name="license_number" value="<?= e($me['license_number'] ?? '') ?>" placeholder="License #">
      </div>

      <!-- Experience -->
      <div class="form-row">
        <label for="experience_years">Experience (years)</label>
        <input type="number" id="experience_years" name="experience_years" min="0" max="80" value="<?= e($me['experience_years'] ?? '') ?>">
      </div>

      <!-- Profile Image -->
      <div class="form-row">
        <label for="profile_image">Profile Image</label>
        <div style="flex:1">
          <input type="file" id="profile_image" name="profile_image" accept="image/*">
          <div class="muted" style="margin-top:4px">jpg/png/webp, max 4MB</div>
          <?php if (!empty($me['profile_image'])): 
                $viewPath = resolveImageWebPath($me['profile_image']);
          ?>
            <div style="margin-top:8px"><a href="<?= e($viewPath) ?>" target="_blank" class="muted">View current image</a></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- About/Bio -->
      <div class="form-row-full">
        <label for="about">About / Bio</label>
        <textarea id="about" name="about" rows="6" placeholder="Describe your company, experience, specialties"><?= e($me['about'] ?? '') ?></textarea>
      </div>
    </div>

    <div style="margin-top:12px;text-align:right;">
      <button class="btn save" type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right:4px" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h11l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Save Personal Info
      </button>
    </div>
  </form>
</section>

      <!-- Services -->
      <section id="services" class="manage-section card">
        <div class="section-header">
          <div class="section-title">Services</div>
          <div class="muted">Add or remove the services you provide</div>
        </div>

        <form method="post" id="servicesForm">
          <input type="hidden" name="action" value="save_services">
          <input type="hidden" name="user_id" value="<?= e($userId) ?>">
          <div id="servicesList">
            <?php if (!empty($services)): foreach ($services as $s): ?>
              <div class="row" style="display:flex;gap:8px;margin-bottom:8px">
                <input name="services[]" value="<?= e($s['service_name']) ?>" placeholder="Service name">
                <button type="button" class="btn-icon remove" aria-label="Remove service" title="Remove service">
                  <!-- trash SVG -->
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            <?php endforeach; else: ?>
              <div class="row" style="display:flex;gap:8px;margin-bottom:8px">
                <input name="services[]" placeholder="Service name">
                <button type="button" class="btn-icon remove" aria-label="Remove service" title="Remove service">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
            <button type="button" id="addServiceBtn" class="btn-icon add" aria-label="Add service" title="Add service">
              <!-- plus SVG -->
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="submit" class="btn save">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right:4px" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h11l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Save Services
            </button>
          </div>
        </form>
      </section>

      <!-- Certs -->
      <section id="certs" class="manage-section card">
        <div class="section-header">
          <div class="section-title">Certifications</div>
          <div class="muted">List any official certifications or credentials</div>
        </div>

        <form method="post" id="certsForm">
          <input type="hidden" name="action" value="save_certs">
          <input type="hidden" name="user_id" value="<?= e($userId) ?>">
          <div id="certsList">
            <?php if (!empty($certs)): foreach ($certs as $c): ?>
              <div class="row" style="display:flex;gap:8px;margin-bottom:8px">
                <input name="certs[]" value="<?= e($c['cert_name']) ?>" placeholder="Certification name">
                <button type="button" class="btn-icon remove" aria-label="Remove cert" title="Remove certification">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            <?php endforeach; else: ?>
              <div class="row" style="display:flex;gap:8px;margin-bottom:8px">
                <input name="certs[]" placeholder="Certification name">
                <button type="button" class="btn-icon remove" aria-label="Remove cert" title="Remove certification">
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
            <button type="button" id="addCertBtn" class="btn-icon add" aria-label="Add certification" title="Add cert">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="submit" class="btn save">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right:4px" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h11l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Save Certifications
            </button>
          </div>
        </form>
      </section>

      <!-- Projects -->
      <section id="projects" class="manage-section card">
        <div class="section-header">
          <div class="section-title">Projects / Portfolio</div>
          <div class="muted">Showcase your completed projects</div>
        </div>

        <form method="post" id="projectsForm">
          <input type="hidden" name="action" value="save_projects">
          <input type="hidden" name="user_id" value="<?= e($userId) ?>">

          <div id="projectsList">
            <?php if (!empty($projects)): foreach ($projects as $p): ?>
              <div class="row grid" style="margin-bottom:8px">
                <input name="project_title[]" value="<?= e($p['title']) ?>" placeholder="Project title">
                <input name="project_year[]" value="<?= e($p['year_completed']) ?>" placeholder="Year">
                <input name="project_desc[]" value="<?= e($p['description']) ?>" placeholder="Short description">
                <div style="grid-column:1/4;text-align:right;margin-top:6px">
                  <button type="button" class="btn-icon remove" aria-label="Remove project" title="Remove project">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </div>
            <?php endforeach; else: ?>
              <div class="row grid" style="margin-bottom:8px">
                <input name="project_title[]" placeholder="Project title">
                <input name="project_year[]" placeholder="Year">
                <input name="project_desc[]" placeholder="Short description">
              </div>
            <?php endif; ?>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
            <button type="button" id="addProjectBtn" class="btn-icon add" aria-label="Add project" title="Add project">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="submit" class="btn save">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right:4px" xmlns="http://www.w3.org/2000/svg"><path d="M5 3h11l4 4v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Save Projects
            </button>
          </div>
        </form>
      </section>

      <!-- Assets -->
      <section id="assets" class="manage-section card">
        <div class="section-header">
          <div class="section-title">Images & Assets</div>
          <div class="muted">Upload portfolio images, certificates or profile pictures</div>
        </div>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_asset">
          <input type="hidden" name="user_id" value="<?= e($userId) ?>">

          <div class="form-grid" style="align-items:end">
            <div>
              <label>File (jpg/png/webp) — max 4MB</label>
              <input type="file" name="asset_file" accept="image/*">
            </div>
            <div>
              <label>Type</label>
              <select name="asset_type">
                <option value="portfolio">Portfolio</option>
                <option value="profile">Profile</option>
                <option value="cert">Certification</option>
              </select>
            </div>
            <div style="grid-column:1/3">
              <label>Caption (optional)</label>
              <input name="asset_caption" placeholder="Short caption">
            </div>
          </div>

          <div style="margin-top:10px;text-align:right">
            <button class="btn save" type="submit">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="margin-right:6px" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Upload
            </button>
          </div>
        </form>

        <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:12px;">
          <?php if (!empty($assets)): foreach ($assets as $a): 
                $assetWeb = resolveImageWebPath($a['file_path'] ?? '');
          ?>
            <div style="width:170px;background:#fff;border-radius:8px;padding:8px;border:1px solid #eef6f9">
              <img src="<?= e($assetWeb) ?>" alt="<?= e($a['caption']) ?>" style="width:100%;height:100px;object-fit:cover;border-radius:6px" onerror="this.style.display='none'">
              <div style="margin-top:6px;font-weight:600;font-size:13px"><?= e($a['asset_type']) ?></div>
              <div class="muted" style="font-size:13px"><?= e($a['caption']) ?></div>
              <form method="post" style="margin-top:8px;display:inline-block" onsubmit="return confirm('Delete this image?');">
                <input type="hidden" name="action" value="delete_asset">
                <input type="hidden" name="user_id" value="<?= e($userId) ?>">
                <input type="hidden" name="asset_id" value="<?= (int)$a['asset_id'] ?>">
                <button class="btn-icon danger" type="submit" aria-label="Delete image" title="Delete image">
                  <!-- trash icon (white) -->
                  <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </form>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">No images uploaded yet.</div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Danger Zone -->
      <section id="danger" class="manage-section card" style="border-left:4px solid rgba(239,68,68,0.12)">
        <div class="section-header">
          <div class="section-title" style="color:#b91c1c">Danger Zone</div>
          <div class="muted">Permanent actions</div>
        </div>

        <div class="muted" style="margin-bottom:12px">Deleting your account will remove the user and related contractor data. This is irreversible.</div>
        <form method="post" onsubmit="return confirm('Delete account permanently?');">
          <input type="hidden" name="action" value="delete_account">
          <input type="hidden" name="user_id" value="<?= e($userId) ?>">
          <label>Type <strong>DELETE</strong> to confirm:</label>
          <input name="confirm_text" placeholder="Type DELETE">
          <div style="margin-top:10px;text-align:right;">
            <button class="btn danger" type="submit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="margin-right:6px" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Delete Account
            </button>
          </div>
        </form>
      </section>
    </main>
  </div>

  <footer style="max-width:1200px;margin:24px auto;text-align:center;color:#6b7280;font-size:13px">
    © <?= date('Y') ?> Mimar — Private contractor dashboard (prototype)
  </footer>

<script>
/* Sidebar nav highlight on scroll + anchor smooth scroll */
(function(){
  const links = document.querySelectorAll('.navlink');
  const sections = Array.from(links).map(l => document.getElementById(l.getAttribute('href').slice(1)));
  function setActive() {
    const top = window.scrollY + 120;
    let activeIndex = -1;
    for (let i=0;i<sections.length;i++){
      const s = sections[i];
      if (!s) continue;
      const offset = s.offsetTop;
      if (offset <= top) activeIndex = i;
    }
    links.forEach((l,i)=> l.classList.toggle('active', i===activeIndex));
  }
  window.addEventListener('scroll', setActive);
  setActive();

  links.forEach(l=>{
    l.addEventListener('click', (e)=>{
      e.preventDefault();
      const id = l.getAttribute('href');
      const el = document.querySelector(id);
      if (!el) return;
      window.scrollTo({top: el.offsetTop - 90, behavior:'smooth'});
    });
  });

  // add/remove dynamic rows (services/certs/projects)
  function attachRemovers(root){
    root.querySelectorAll('.remove').forEach(b=>{
      b.onclick = ()=>{
        const row = b.closest('.row');
        if (row) row.remove();
      };
      // keyboard friendly: Enter/Space triggers click
      b.addEventListener('keydown', (ev)=>{
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); b.click(); }
      });
    });
  }
  attachRemovers(document);

  document.getElementById('addServiceBtn').addEventListener('click', ()=>{
    const container = document.getElementById('servicesList');
    const div = document.createElement('div');
    div.className = 'row';
    div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px';
    div.innerHTML = '<input name="services[]" placeholder="Service name"><button type="button" class="btn-icon remove" aria-label="Remove service" title="Remove service"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
    container.appendChild(div);
    attachRemovers(div);
  });

  document.getElementById('addCertBtn').addEventListener('click', ()=>{
    const container = document.getElementById('certsList');
    const div = document.createElement('div');
    div.className = 'row';
    div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px';
    div.innerHTML = '<input name="certs[]" placeholder="Certification name"><button type="button" class="btn-icon remove" aria-label="Remove cert" title="Remove certification"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
    container.appendChild(div);
    attachRemovers(div);
  });

  document.getElementById('addProjectBtn').addEventListener('click', ()=>{
    const container = document.getElementById('projectsList');
    const div = document.createElement('div');
    div.className = 'row grid';
    div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 2fr;gap:8px;margin-bottom:8px';
    div.innerHTML = '<input name="project_title[]" placeholder="Project title"><input name="project_year[]" placeholder="Year"><input name="project_desc[]" placeholder="Short description"><div style="grid-column:1/4;text-align:right;margin-top:6px"><button type="button" class="btn-icon remove" aria-label="Remove project" title="Remove project"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2" stroke="#dc4b4b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>';
    container.appendChild(div);
    attachRemovers(div);
  });

})();
</script>
</body>
</html>
