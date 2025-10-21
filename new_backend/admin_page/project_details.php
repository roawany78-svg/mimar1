<?php
// project_details.php
// Updated: uses the same upload resolution logic as your working project_page.php
// -> Update DB credentials and (optionally) candidate upload folders below.

$dbHost = '127.0.0.1';
$dbName = 'dbo_schema';     // change if your DB name differs
$dbUser = 'root';
$dbPass = '';               // change to your DB password

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error: " . htmlspecialchars($e->getMessage());
    exit;
}

// Accept both 'project_id' and 'id' query parameter (project_page.php uses "id")
$project_id = 0;
if (isset($_GET['project_id'])) {
    $project_id = (int)$_GET['project_id'];
} elseif (isset($_GET['id'])) {
    $project_id = (int)$_GET['id'];
}
if ($project_id <= 0) {
    echo "Invalid project id.";
    exit;
}

$scriptDir = __DIR__;
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) : null;

// Candidate upload dirs (adjust if your uploads are in a different place)
$candidates = [
    $scriptDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
    $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
];
if ($docRoot) {
    $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
}
$candidates = array_unique($candidates);

// find first existing uploads directory if any
$foundUploadDir = null;
foreach ($candidates as $c) {
    if ($c && is_dir($c)) { $foundUploadDir = rtrim($c, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR; break; }
}

// Helper: try to resolve DB file_path into a usable URL for <img> or <a href>
$webPrefixes = ['/uploads/', 'uploads/', '/uploads/projects/', '/uploads/attachments/'];
function resolve_attachment_src($dbPath) {
    global $scriptDir, $docRoot, $foundUploadDir, $webPrefixes;
    $dbPath = (string) trim($dbPath);
    if ($dbPath === '') return '';
    // If already absolute URL, return it
    if (preg_match('#^https?://#i', $dbPath)) return $dbPath;

    // Normalize trimming leading slashes
    $dbTrim = ltrim($dbPath, "/\\");

    // Rapid candidate list to test for real filesystem files
    $candidatesForFile = [];
    if ($foundUploadDir) {
        $candidatesForFile[] = $foundUploadDir . $dbTrim;
        $candidatesForFile[] = $foundUploadDir . 'projects' . DIRECTORY_SEPARATOR . $dbTrim;
        $candidatesForFile[] = $foundUploadDir . 'contracts' . DIRECTORY_SEPARATOR . $dbTrim;
        $candidatesForFile[] = $foundUploadDir . 'contractors' . DIRECTORY_SEPARATOR . $dbTrim;
    }
    // also try relative to script dir and parent
    $candidatesForFile[] = $scriptDir . DIRECTORY_SEPARATOR . $dbTrim;
    $candidatesForFile[] = $scriptDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $dbTrim;
    if ($docRoot) $candidatesForFile[] = $docRoot . DIRECTORY_SEPARATOR . $dbTrim;

    foreach ($candidatesForFile as $cand) {
        if (!$cand) continue;
        $real = @realpath($cand);
        if ($real && file_exists($real)) {
            // convert realpath to web-relative if possible
            if ($docRoot) {
                $docRootReal = @realpath($docRoot);
                if ($docRootReal && strpos($real, $docRootReal) === 0) {
                    $rel = substr($real, strlen($docRootReal));
                    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
                    if ($rel === '' || $rel[0] !== '/') $rel = '/' . $rel;
                    return $rel;
                }
            }
            // fallback: if the found path is under an uploads folder, try to return /uploads/... + dbTrim
            foreach ($webPrefixes as $wp) {
                $candidateUrl = rtrim($wp, '/') . '/' . ltrim($dbTrim, '/');
                return $candidateUrl;
            }
            return $dbPath;
        }
    }

    // If not found on filesystem, maybe DB already stored a web path like 'uploads/xxx' -> return it
    foreach ($webPrefixes as $wp) {
        if (stripos($dbPath, $wp) === 0) return $dbPath;
    }

    // last resort: return raw dbPath (may work if it's a relative web path)
    return $dbPath;
}

// Fetch project + contractor basic info
$sql = "
SELECT p.*,
       u.user_id AS contractor_id,
       u.name AS contractor_name,
       cp.profile_image AS contractor_profile_image,
       cp.location AS contractor_location,
       cp.license_number
FROM project p
LEFT JOIN user u ON p.accepted_contractor_id = u.user_id
LEFT JOIN contractorprofile cp ON u.user_id = cp.contractor_id
WHERE p.project_id = :pid
LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([':pid' => $project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) {
    echo "Project not found.";
    exit;
}

// Fetch attachments for this project
$attStmt = $pdo->prepare("SELECT * FROM attachment WHERE project_id = :pid ORDER BY attachment_id ASC");
$attStmt->execute([':pid' => $project_id]);
$attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

$images = [];
$contractFile = null;
foreach ($attachments as $att) {
    $dbPath = trim($att['file_path'] ?? $att['file_name'] ?? '');
    $resolved = resolve_attachment_src($dbPath);
    // Heuristics: treat PDFs/contracts separate
    $lower = strtolower($dbPath . ' ' . ($att['file_type'] ?? ''));
    if (strpos($dbPath, '.pdf') !== false || strpos($lower, 'pdf') !== false || ($att['file_category'] ?? '') === 'contract') {
        $contractFile = [
            'db_path' => $dbPath,
            'src' => $resolved,
            'attachment_id' => $att['attachment_id'] ?? null
        ];
    } else {
        $images[] = [
            'db_path' => $dbPath,
            'src' => $resolved,
            'attachment_id' => $att['attachment_id'] ?? null
        ];
    }
}

// If DB row contains a project_contract field, prefer it if no attachment marked as contract
if (!$contractFile && !empty($project['project_contract'])) {
    $contractFile = ['db_path' => $project['project_contract'], 'src' => resolve_attachment_src($project['project_contract'])];
}

// Fetch project specifications
$specStmt = $pdo->prepare("SELECT specification FROM projectspecification WHERE project_id = :pid ORDER BY spec_id ASC");
$specStmt->execute([':pid' => $project_id]);
$specs = $specStmt->fetchAll(PDO::FETCH_COLUMN);

// Ratings
$avgRating = null;
$ratingCount = 0;
if (!empty($project['contractor_id'])) {
    $rStmt = $pdo->prepare("SELECT AVG(stars) AS avg_stars, COUNT(*) AS cnt FROM rating WHERE contractor_id = :cid");
    $rStmt->execute([':cid' => $project['contractor_id']]);
    $r = $rStmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        $avgRating = $r['avg_stars'] !== null ? round($r['avg_stars'], 1) : null;
        $ratingCount = (int)$r['cnt'];
    }
}

// Format dates/duration/budget
$start_date = !empty($project['start_date']) ? date('n/j/Y', strtotime($project['start_date'])) : '—';
$end_date   = !empty($project['end_date'])   ? date('n/j/Y', strtotime($project['end_date']))   : '—';
$durationText = '—';
if (!empty($project['start_date']) && !empty($project['end_date'])) {
    $startDt = new DateTime($project['start_date']);
    $endDt   = new DateTime($project['end_date']);
    $interval = $startDt->diff($endDt);
    if ($interval->y > 0) {
        $durationText = $interval->y . ' years ' . $interval->m . ' months';
    } else {
        $durationText = $interval->m . ' months';
    }
}
$budget = !empty($project['estimated_cost']) ? number_format((float)$project['estimated_cost'], 0, '.', ',') . ' SAR' : '—';
$area = !empty($project['area']) ? $project['area'] : '450 sqm';

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

// Placeholder
$placeholder = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="100%" height="100%" fill="#e6e6e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="24" fill="#7a7a7a">No project image</text></svg>');
$mainImageUrl = $placeholder;
if (!empty($images)) $mainImageUrl = $images[0]['src'] ?: $placeholder;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($project['title']); ?> — Project Details</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="project_details.css">
</head>
<body>
<div class="page-wrap">
    <div class="project-top">
        <div class="left-col">
            <div class="main-image">
                <img id="mainImage" src="<?php echo e($mainImageUrl); ?>" alt="Project image">
                <?php if(!empty($project['status'])): ?>
                    <span class="badge status"><?php echo e(ucfirst($project['status'])); ?></span>
                <?php endif; ?>
            </div>

            <?php if(count($images) > 0): ?>
                <div class="thumbs">
                    <?php foreach ($images as $img): ?>
                        <div class="thumb" data-src="<?php echo e($img['src']); ?>">
                            <img src="<?php echo e($img['src']); ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="right-col">
            <h1 class="project-title"><?php echo e($project['title']); ?></h1>
            <p class="project-desc"><?php echo nl2br(e($project['description'])); ?></p>

            <div class="meta-row">
                <div class="rating">
                    <?php if($avgRating !== null): ?>
                        <div class="stars">
                            <span class="star-val"><?php echo e($avgRating); ?></span>
                            <span class="star-count">(<?php echo e($ratingCount); ?> reviews)</span>
                        </div>
                    <?php else: ?>
                        <div class="stars"><span class="star-val">—</span></div>
                    <?php endif; ?>
                </div>
                <div class="location-area">
                    <span class="location"><?php echo e($project['location'] ?: ($project['contractor_location'] ?: '—')); ?></span>
                    <span class="dot">·</span>
                    <span class="area"><?php echo e($area); ?></span>
                </div>
            </div>

            <div class="contractor-card">
                <div class="profile-left">
                    <?php
                    $profImg = $project['contractor_profile_image'] ?: '';
                    $profImgUrl = $profImg ? resolve_attachment_src($profImg) : 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"><rect width="100%" height="100%" fill="#f3f3f3"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Arial" font-size="12" fill="#888">No Photo</text></svg>');
                    ?>
                    <img class="contractor-avatar" src="<?php echo e($profImgUrl); ?>" alt="Contractor avatar">
                </div>
                <div class="profile-right">
                    <div class="contractor-name"><?php echo e($project['contractor_name'] ?: '—'); ?></div>
                    <div class="contractor-sub"><?php echo e($project['license_number'] ? 'Licensed Contractor • '.e($project['license_number']) : 'Licensed Contractor'); ?></div>
                </div>
                <div class="profile-actions">
                    <?php if(!empty($project['contractor_id'])): ?>
                        <a class="btn-view" href="contractor_profile.php?contractor_id=<?php echo e($project['contractor_id']); ?>">View Profile</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="contract-download">
                <div class="contract-left">
                    <strong>Project Contract</strong>
                    <div class="contract-filename"><?php echo e($contractFile['db_path'] ?? ($project['project_contract'] ?? 'No contract uploaded')); ?></div>
                </div>
                <div class="contract-right">
                    <?php if(!empty($contractFile['src'])): ?>
                        <a class="btn-download" href="<?php echo e($contractFile['src']); ?>" download>Download</a>
                    <?php else: ?>
                        <button class="btn-download disabled" disabled>Download</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="project-bottom">
        <div class="card timeline">
            <h3>Project Timeline</h3>
            <table class="timeline-table">
                <tr><td>Start Date:</td><td><?php echo e($start_date); ?></td></tr>
                <tr><td>End Date:</td><td><?php echo e($end_date); ?></td></tr>
                <tr><td>Duration:</td><td><?php echo e($durationText); ?></td></tr>
                <tr><td>Budget:</td><td><?php echo e($budget); ?></td></tr>
            </table>
        </div>

        <div class="card specs">
            <h3>Project Specifications</h3>
            <?php if(!empty($specs)): ?>
                <ul class="spec-list">
                    <?php foreach ($specs as $s): ?>
                        <li><?php echo e(trim($s)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="muted">No specifications listed.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Thumbnail click swaps main image
document.addEventListener('DOMContentLoaded', function(){
    var thumbs = document.querySelectorAll('.thumb');
    var main = document.getElementById('mainImage');
    thumbs.forEach(function(t){
        t.addEventListener('click', function(){
            var src = t.getAttribute('data-src');
            if(src) main.src = src;
            thumbs.forEach(function(x){ x.classList.remove('active'); });
            t.classList.add('active');
        });
    });
});
</script>
</body>
</html>
