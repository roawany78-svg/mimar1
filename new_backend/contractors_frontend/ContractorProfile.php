<?php
require_once __DIR__ . '/../config.php';


$userId = $_GET['user_id'] ?? 1; // 

$pdo = getPDO();

function fetch_existing(PDO $pdo, int $userId) {
    $out = ['user' => null, 'profile' => null, 'services' => [], 'certs' => [], 'projects' => [], 'assets' => []];

    $stmt = $pdo->prepare('SELECT user_id, name, email, phone FROM `User` WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $out['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM ContractorProfile WHERE contractor_id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $out['profile'] = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT service_name, details FROM ContractorService WHERE contractor_id = :id ORDER BY service_id ASC');
    $stmt->execute([':id' => $userId]);
    $out['services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT cert_name, issuer, issue_date, credential_url FROM ContractorCertification WHERE contractor_id = :id ORDER BY cert_id ASC');
    $stmt->execute([':id' => $userId]);
    $out['certs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT title, description, year_completed FROM ContractorProject WHERE contractor_id = :id ORDER BY portfolio_id ASC');
    $stmt->execute([':id' => $userId]);
    $out['projects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT asset_id, asset_type, file_path, caption FROM ContractorAsset WHERE contractor_id = :id ORDER BY uploaded_at ASC');
    $stmt->execute([':id' => $userId]);
    $out['assets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $out;
}

$existing = fetch_existing($pdo, $userId);
$errors = [];
$success = false;
$responsePayload = [];

function ensure_upload_dir(string $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception("Failed to create upload directory: {$dir}");
        }
    }
    @chmod($dir, 0755);

    $ht = "Options -Indexes\n<FilesMatch \"\\.(php|php5|phtml)$\">\n  Order allow,deny\n  Deny from all\n</FilesMatch>\n";
    $htpath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htpath)) {
        @file_put_contents($htpath, $ht);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // collect fields
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $specialization = isset($_POST['specialization']) ? trim($_POST['specialization']) : null;
    $license_number = isset($_POST['license_number']) ? trim($_POST['license_number']) : null;
    $location = isset($_POST['location']) ? trim($_POST['location']) : null;
    $about = isset($_POST['about']) ? trim($_POST['about']) : null;
    $experience_years = isset($_POST['experience_years']) ? (int)$_POST['experience_years'] : null;

    $services = isset($_POST['services']) && is_array($_POST['services']) ? array_values(array_filter(array_map('trim', $_POST['services']))) : [];
    $certs = isset($_POST['certs']) && is_array($_POST['certs']) ? array_values(array_filter(array_map('trim', $_POST['certs']))) : [];

    $project_titles = isset($_POST['project_title']) && is_array($_POST['project_title']) ? $_POST['project_title'] : [];
    $project_descs = isset($_POST['project_desc']) && is_array($_POST['project_desc']) ? $_POST['project_desc'] : [];
    $project_years = isset($_POST['project_year']) && is_array($_POST['project_year']) ? $_POST['project_year'] : [];

    // Basic validation only
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '') $errors[] = 'Email is required.';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // update User
            $upd = $pdo->prepare('UPDATE `User` SET name = :name, phone = :phone, email = :email WHERE user_id = :id');
            $upd->execute([':name' => $name, ':phone' => $phone, ':email' => $email, ':id' => $userId]);

            // handle image
            $existing_image = $existing['profile']['profile_image'] ?? null;
            $profile_image_path = $existing_image;

            // define upload directory and ensure it exists (auto-create)
            $uploadDir = __DIR__ . '/uploads/contractors';
            ensure_upload_dir($uploadDir);

            if (!empty($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['profile_image'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $maxBytes = 4 * 1024 * 1024;
                    if ($file['size'] <= $maxBytes) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->file($file['tmp_name']);
                        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (isset($allowed[$mime])) {
                            $ext = $allowed[$mime];
                            $fileName = sprintf('%d_profile_%s.%s', $userId, bin2hex(random_bytes(6)), $ext);
                            $dest = $uploadDir . '/' . $fileName;

                            if (move_uploaded_file($file['tmp_name'], $dest)) {
                                $profile_image_path = '/uploads/contractors/' . $fileName;
                            }
                        }
                    }
                }
            }

            // insert or update ContractorProfile
            $insProfile = $pdo->prepare(
                'INSERT INTO ContractorProfile (contractor_id, specialization, license_number, location, about, experience_years, profile_image, created_at)
                 VALUES (:id, :spec, :lic, :loc, :about, :exp, :img, NOW())
                 ON DUPLICATE KEY UPDATE
                   specialization = VALUES(specialization),
                   license_number = VALUES(license_number),
                   location = VALUES(location),
                   about = VALUES(about),
                   experience_years = VALUES(experience_years),
                   profile_image = VALUES(profile_image)'
            );
            $insProfile->execute([
                ':id' => $userId,
                ':spec' => $specialization,
                ':lic' => $license_number,
                ':loc' => $location,
                ':about' => $about,
                ':exp' => $experience_years,
                ':img' => $profile_image_path
            ]);

            // replace multivalue lists
            $delS = $pdo->prepare('DELETE FROM ContractorService WHERE contractor_id = :id');
            $delS->execute([':id' => $userId]);
            if (!empty($services)) {
                $insS = $pdo->prepare('INSERT INTO ContractorService (contractor_id, service_name, details) VALUES (:id, :name, :details)');
                foreach ($services as $s) {
                    if ($s === '') continue;
                    $insS->execute([':id' => $userId, ':name' => mb_substr($s, 0, 200), ':details' => null]);
                }
            }

            $delC = $pdo->prepare('DELETE FROM ContractorCertification WHERE contractor_id = :id');
            $delC->execute([':id' => $userId]);
            if (!empty($certs)) {
                $insC = $pdo->prepare('INSERT INTO ContractorCertification (contractor_id, cert_name) VALUES (:id, :name)');
                foreach ($certs as $c) {
                    if ($c === '') continue;
                    $insC->execute([':id' => $userId, ':name' => mb_substr($c, 0, 300)]);
                }
            }

            $delP = $pdo->prepare('DELETE FROM ContractorProject WHERE contractor_id = :id');
            $delP->execute([':id' => $userId]);
            $insP = $pdo->prepare('INSERT INTO ContractorProject (contractor_id, title, description, year_completed) VALUES (:id, :title, :desc, :year)');
            $countProjects = max(count($project_titles), count($project_descs), count($project_years));
            for ($i = 0; $i < $countProjects; $i++) {
                $t = trim($project_titles[$i] ?? '');
                if ($t === '') continue;
                $d = trim($project_descs[$i] ?? '');
                $y = isset($project_years[$i]) ? (int)$project_years[$i] : null;
                $insP->execute([':id' => $userId, ':title' => mb_substr($t, 0, 300), ':desc' => $d, ':year' => $y]);
            }

            $pdo->commit();
            $success = true;

            // refresh existing data for output
            $existing = fetch_existing($pdo, $userId);
            $responsePayload = [
                'success' => true,
                'message' => 'Profile saved successfully.',
                'profile_image' => $existing['profile']['profile_image'] ?? null
            ];

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response($responsePayload);
            }

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Failed to save profile: ' . $ex->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                json_response(['success' => false, 'errors' => $errors]);
            }
        }
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response(['success' => false, 'errors' => $errors]);
        }
    }
}

// prepare data for rendering
$user = $existing['user'] ?? [];
$profile = $existing['profile'] ?? [];
$services_list = $existing['services'];
$certs_list = $existing['certs'];
$projects_list = $existing['projects'];
$assets_list = $existing['assets'];

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Contractor Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:18px}
    label{display:block;margin:8px 0 4px}
    input[type=text], input[type=number], input[type=email], textarea { width:100%; max-width:900px; padding:8px; box-sizing:border-box; }
    .multilist { margin-bottom:12px; }
    .multilist .row { display:flex; gap:8px; margin-bottom:6px; align-items:center; }
    .multilist input{ flex:1 }
    .btn { padding:8px 12px; cursor:pointer; }
    .danger { background:#fdd; border:1px solid #f99; }
    .success { background:#dfd; border:1px solid #9c9; padding:8px; margin-bottom:10px; }
    .errors { background:#ffdede; border:1px solid #f99; padding:8px; margin-bottom:10px; color:#800; }
    .small { font-size:0.9rem; color:#444; }
  </style>
</head>
<body>
  <h1>Complete Contractor Profile</h1>
  <p>User ID: <?= $userId ?></p>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <strong>Errors:</strong>
      <ul><?php foreach ($errors as $e): ?><li><?=htmlspecialchars($e)?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div id="serverSuccess" class="success"><?=htmlspecialchars($responsePayload['message'] ?? 'Profile saved successfully.')?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="contractorForm">
    <label for="name">Name</label>
    <input id="name" name="name" type="text" required value="<?=htmlspecialchars($user['name'] ?? '')?>">

    <label for="phone">Phone</label>
    <input id="phone" name="phone" type="text" value="<?=htmlspecialchars($user['phone'] ?? '')?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" required value="<?=htmlspecialchars($user['email'] ?? '')?>">

    <label for="specialization">Specialization</label>
    <input id="specialization" name="specialization" type="text" value="<?=htmlspecialchars($profile['specialization'] ?? '')?>">

    <label for="license_number">License Number</label>
    <input id="license_number" name="license_number" type="text" value="<?=htmlspecialchars($profile['license_number'] ?? '')?>">

    <label for="location">Location</label>
    <input id="location" name="location" type="text" value="<?=htmlspecialchars($profile['location'] ?? '')?>">

    <label for="about">About</label>
    <textarea id="about" name="about" rows="4"><?=htmlspecialchars($profile['about'] ?? '')?></textarea>

    <label for="experience_years">Years of Experience</label>
    <input id="experience_years" name="experience_years" type="number" min="0" max="120" value="<?=htmlspecialchars($profile['experience_years'] ?? '')?>">

    <label>Profile Image (jpg/png/webp, &lt;=4MB)</label>
    <?php if (!empty($profile['profile_image'])): ?>
      <div id="currentImage">Current: <a id="profileImageLink" href="<?=htmlspecialchars($profile['profile_image'])?>" target="_blank">view</a></div>
    <?php else: ?>
      <div id="currentImage" class="small">No profile image uploaded yet.</div>
    <?php endif; ?>
    <input type="file" name="profile_image" accept="image/*">

    <hr>

    <!-- Services -->
    <div class="multilist" id="servicesList">
      <label>Services Offered</label>
      <div id="servicesContainer">
        <?php if (!empty($services_list)): ?>
          <?php foreach ($services_list as $s): ?>
            <div class="row"><input type="text" name="services[]" value="<?=htmlspecialchars($s['service_name'] ?? '')?>"><button type="button" class="btn removeService">Remove</button></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="row"><input type="text" name="services[]" placeholder="Service name"><button type="button" class="btn removeService">Remove</button></div>
        <?php endif; ?>
      </div>
      <button type="button" id="addService" class="btn">Add service</button>
    </div>

    <!-- Certifications -->
    <div class="multilist" id="certsList">
      <label>Certifications</label>
      <div id="certsContainer">
        <?php if (!empty($certs_list)): ?>
          <?php foreach ($certs_list as $c): ?>
            <div class="row"><input type="text" name="certs[]" value="<?=htmlspecialchars($c['cert_name'] ?? '')?>"><button type="button" class="btn removeCert">Remove</button></div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="row"><input type="text" name="certs[]" placeholder="Certification name"><button type="button" class="btn removeCert">Remove</button></div>
        <?php endif; ?>
      </div>
      <button type="button" id="addCert" class="btn">Add certification</button>
    </div>

    <!-- Projects -->
    <div class="multilist" id="projectsList">
      <label>Projects (title, description, year)</label>
      <div id="projectsContainer">
        <?php if (!empty($projects_list)): ?>
          <?php foreach ($projects_list as $p): ?>
            <div class="row projectRow">
              <input type="text" name="project_title[]" placeholder="Project title" value="<?=htmlspecialchars($p['title'] ?? '')?>">
              <input type="text" name="project_desc[]" placeholder="Short description" value="<?=htmlspecialchars($p['description'] ?? '')?>">
              <input type="number" name="project_year[]" placeholder="Year" style="width:90px" value="<?=htmlspecialchars($p['year_completed'] ?? '')?>">
              <button type="button" class="btn removeProject">Remove</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="row projectRow">
            <input type="text" name="project_title[]" placeholder="Project title">
            <input type="text" name="project_desc[]" placeholder="Short description">
            <input type="number" name="project_year[]" placeholder="Year" style="width:90px">
            <button type="button" class="btn removeProject">Remove</button>
          </div>
        <?php endif; ?>
      </div>
      <button type="button" id="addProject" class="btn">Add project</button>
    </div>

    <div style="margin-top:18px;">
      <button type="submit" class="btn" id="saveBtn">Save Profile</button>
      <a href="contractors.php" class="btn">Go to Dashboard</a>
    </div>
  </form>

  <div id="ajaxMessage" style="margin-top:10px;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // helpers
  function createRow(html, rowClass = 'row') {
    const row = document.createElement('div');
    row.className = rowClass;
    row.innerHTML = html;
    return row;
  }
  function showMessage(html) {
    const m = document.getElementById('ajaxMessage');
    if (m) m.innerHTML = html;
  }

  // ---- Services ----
  const addServiceBtn = document.getElementById('addService');
  const servicesContainer = document.getElementById('servicesContainer');
  if (addServiceBtn && servicesContainer) {
    addServiceBtn.addEventListener('click', function () {
      const html = '<input type="text" name="services[]" placeholder="Service name" required> ' +
                   '<button type="button" class="btn removeService">Remove</button>';
      servicesContainer.appendChild(createRow(html));
    });

    servicesContainer.addEventListener('click', function (e) {
      if (e.target && e.target.classList.contains('removeService')) {
        const r = e.target.closest('.row');
        if (r) r.remove();
      }
    });
  }

  // ---- Certifications ----
  const addCertBtn = document.getElementById('addCert');
  const certsContainer = document.getElementById('certsContainer');
  if (addCertBtn && certsContainer) {
    addCertBtn.addEventListener('click', function () {
      const html = '<input type="text" name="certs[]" placeholder="Certification name" required> ' +
                   '<button type="button" class="btn removeCert">Remove</button>';
      certsContainer.appendChild(createRow(html));
    });

    certsContainer.addEventListener('click', function (e) {
      if (e.target && e.target.classList.contains('removeCert')) {
        const r = e.target.closest('.row');
        if (r) r.remove();
      }
    });
  }

  // ---- Projects ----
  const addProjectBtn = document.getElementById('addProject');
  const projectsContainer = document.getElementById('projectsContainer');
  if (addProjectBtn && projectsContainer) {
    addProjectBtn.addEventListener('click', function () {
      const html = ''
        + '<input type="text" name="project_title[]" placeholder="Project title" required> '
        + '<input type="text" name="project_desc[]" placeholder="Short description" required> '
        + '<input type="number" name="project_year[]" placeholder="Year" style="width:110px" min="1900" max="2100" required> '
        + '<button type="button" class="btn removeProject">Remove</button>';
      projectsContainer.appendChild(createRow(html, 'row projectRow'));
    });

    projectsContainer.addEventListener('click', function (e) {
      if (e.target && e.target.classList.contains('removeProject')) {
        const r = e.target.closest('.row');
        if (r) r.remove();
      }
    });
  }

  // ---- Form submit via fetch (robust JSON handling) ----
  const contractorForm = document.getElementById('contractorForm');
  if (contractorForm) {
    contractorForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      const saveBtn = document.getElementById('saveBtn');
      if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
      }
      showMessage('');

      try {
        const formData = new FormData(contractorForm);

        const resp = await fetch(window.location.href, {
          method: 'POST',
          headers: {
            // do NOT set Content-Type for FormData
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: formData
        });

        const text = await resp.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (err) {
          // server returned non-JSON (probably an HTML error) — include snippet for debugging
          const snippet = text.length > 1000 ? text.slice(0, 1000) + '…' : text;
          throw new Error('Server returned non-JSON response: ' + snippet);
        }

        // restore button
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Profile'; }

        if (data && data.success) {
          showMessage('<div class="success">' + (data.message || 'Profile saved.') + '</div>');
          // Redirect to private_profile.php with user_id after showing success message
          try {
            const uid = <?php echo json_encode($userId); ?>;
            // short delay to ensure user sees the message
            setTimeout(function(){ window.location.href = 'private_profile.php?user_id=' + encodeURIComponent(uid); }, 600);
          } catch (e) { /* ignore redirect errors */ }
        } else {
          const errs = (data && data.errors) ? data.errors : ['Failed to save.'];
          let html = '<div class="errors"><strong>Errors:</strong><ul>';
          errs.forEach(function (er) { html += '<li>' + er + '</li>'; });
          html += '</ul></div>';
          showMessage(html);
        }
      } catch (err) {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Profile'; }
        showMessage('<div class="errors">Save failed: ' + (err.message || 'network error') + '</div>');
        console.error('Save error:', err);
      }
    });
  }
});
</script>

</body>
</html>