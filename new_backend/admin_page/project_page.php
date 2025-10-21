<?php
// project_page.php
// Updated to reliably find and show project images (searches uploads/ subfolders).

require_once __DIR__ . '/../config.php';

/////////////////////
// Helper functions
/////////////////////

$scriptDir = __DIR__;
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) : null;

// Candidate upload dirs
$candidates = [
    $scriptDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
    $scriptDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR,
];
if ($docRoot) {
    $candidates[] = $docRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
}
$candidates = array_unique($candidates);

// find first existing uploads directory
$foundUploadDir = null;
foreach ($candidates as $c) {
    if (is_dir($c)) {
        $foundUploadDir = rtrim($c, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        break;
    }
}

function pathInsideDocRoot($path, $docRoot) {
    if (!$docRoot) return false;
    $rp = @realpath($path);
    $rd = @realpath($docRoot);
    if (!$rp || !$rd) return false;
    $rpN = str_replace(['\\','/'], '/', $rp);
    $rdN = str_replace(['\\','/'], '/', $rd);
    return strpos($rpN, $rdN) === 0;
}

function getImageWebSrc($fullPath, $docRoot) {
    if (!file_exists($fullPath)) return null;
    if (pathInsideDocRoot($fullPath, $docRoot)) {
        $rp = realpath($fullPath);
        $rd = realpath($docRoot);
        $relative = substr(str_replace(['\\','/'], '/', $rp), strlen(str_replace(['\\','/'], '/', $rd)));
        if ($relative === false || $relative === '') $relative = '/' . basename($fullPath);
        if ($relative[0] !== '/') $relative = '/' . $relative;
        // return relative path (browser will resolve to same host)
        return $relative;
    } else {
        // fallback to data URI
        $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
        $data = @file_get_contents($fullPath);
        if ($data === false) return null;
        $b64 = base64_encode($data);
        return "data:$mime;base64,$b64";
    }
}

function searchFileInDir($dir, $filename) {
    if (!is_dir($dir)) return null;
    $filename = ltrim($filename, "/\\");
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $fileinfo) {
            if (!$fileinfo->isFile()) continue;
            // case-insensitive match of filename
            if (strcasecmp($fileinfo->getFilename(), $filename) === 0) {
                return $fileinfo->getRealPath();
            }
        }
    } catch (Exception $e) {
        return null;
    }
    return null;
}

/////////////////////
// Database queries
/////////////////////

try {
    $pdo = getPDO();

    // --- UPDATED SQL: select all required project fields (including status, estimated_cost, client_id, project_contract, accepted_contractor_id)
    $sql = "
        SELECT
            p.project_id,
            p.order_id,
            p.title,
            p.description,
            p.location,
            p.status,
            p.estimated_cost,
            p.created_at,
            p.start_date,
            p.end_date,
            p.project_contract,
            p.client_id,
            p.accepted_contractor_id,
            u.name AS contractor_name,
            u.user_id AS contractor_id,
            AVG(r.stars) AS avg_rating,
            COUNT(r.rating_id) AS rating_count
        FROM project p
        LEFT JOIN user u ON p.accepted_contractor_id = u.user_id
        LEFT JOIN rating r ON u.user_id = r.contractor_id
        GROUP BY p.project_id
        ORDER BY p.created_at DESC
    ";
    // --- end updated SQL

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare groupedProjects structure
    $groupedProjects = [];
    $projectIds = [];
    foreach ($projects as $p) {
        $pid = $p['project_id'];
        $groupedProjects[$pid] = $p;
        $groupedProjects[$pid]['images'] = []; // will hold arrays with keys: file_path, full_path, src
        $projectIds[] = $pid;
    }

    // If there are projects, fetch attachments in one query
    if (!empty($projectIds)) {
        // build placeholders
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $attSql = "SELECT attachment_id, project_id, file_path, file_name FROM attachment WHERE project_id IN ($placeholders) AND file_category = 'attachment' ORDER BY attachment_id";
        $attStmt = $pdo->prepare($attSql);
        $attStmt->execute($projectIds);
        $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

        // For each attachment resolve its full path and web src
        foreach ($attachments as $att) {
            $pid = $att['project_id'];
            $dbPath = trim($att['file_path'] ?? $att['file_name'] ?? '');
            $dbPathTrim = ltrim($dbPath, "/\\");
            $foundFullPath = null;

            // Quick candidate attempts
            $candidatesForFile = [];
            if ($foundUploadDir) {
                $candidatesForFile[] = $foundUploadDir . $dbPathTrim;
                // also try common subfolders
                $candidatesForFile[] = $foundUploadDir . 'projects' . DIRECTORY_SEPARATOR . $dbPathTrim;
                $candidatesForFile[] = $foundUploadDir . 'contracts' . DIRECTORY_SEPARATOR . $dbPathTrim;
                $candidatesForFile[] = $foundUploadDir . 'contractors' . DIRECTORY_SEPARATOR . $dbPathTrim;
            }
            $candidatesForFile[] = $scriptDir . DIRECTORY_SEPARATOR . $dbPathTrim;
            $candidatesForFile[] = $scriptDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $dbPathTrim;
            if ($docRoot) $candidatesForFile[] = $docRoot . DIRECTORY_SEPARATOR . $dbPathTrim;

            foreach ($candidatesForFile as $cand) {
                if (!$cand) continue;
                $real = @realpath($cand);
                if ($real && file_exists($real)) { $foundFullPath = $real; break; }
                if (file_exists($cand)) { $foundFullPath = $cand; break; }
            }

            // If not found, recursively search inside detected uploads dir
            if (!$foundFullPath && $foundUploadDir && $dbPathTrim !== '') {
                $search = searchFileInDir($foundUploadDir, $dbPathTrim);
                if ($search) $foundFullPath = $search;
            }

            // Build web src if found
            $src = null;
            if ($foundFullPath) {
                $src = getImageWebSrc($foundFullPath, $docRoot);
            } else {
                // if DB already contains a web path (e.g. 'uploads/projects/xxx'), try to use it as relative URL
                if ($dbPathTrim !== '') {
                    // try leading slash
                    $relTry = '/' . ltrim(str_replace('\\', '/', $dbPathTrim), '/');
                    $src = $relTry;
                }
            }

            // Add to grouped projects if project exists
            if (isset($groupedProjects[$pid])) {
                $groupedProjects[$pid]['images'][] = [
                    'db_path' => $dbPath,
                    'full_path' => $foundFullPath,
                    'src' => $src,
                    'attachment_id' => $att['attachment_id']
                ];
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $groupedProjects = [];
}

?>
<!-- Keep the rest of your HTML/rendering exactly the same as before.
     The rendering code will now find $project['status'] in each $project and display it. -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Gallery</title>
    <link rel="stylesheet" href="project_page.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Small inline styles for safe thumbnail sizing */
        .project-image img { max-width: 320px; max-height: 200px; object-fit: cover; display:block; }
        .no-image { display:flex; align-items:center; justify-content:center; height:200px; background:#f4f4f4; color:#999; }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Project Gallery</h1>
            <p>Explore amazing construction projects completed by our verified contractors across Saudi Arabia.</p>
            
            <div class="search-filter">
                <div class="search-box">
                    <input type="text" placeholder="Search projects or contractors...">
                </div>
                <div class="filter-dropdown">
                    <select>
                        <option>All Projects</option>
                        <option>Residential</option>
                        <option>Commercial</option>
                        <option>Renovation</option>
                    </select>
                </div>
            </div>
        </header>

        <hr class="divider">

        <div class="projects-grid">
            <?php if (count($groupedProjects) > 0): ?>
                <?php foreach ($groupedProjects as $project): 
                    // Prepare safe values and formatting
                    $pid = (int)($project['project_id'] ?? 0);
                    $title = htmlspecialchars($project['title'] ?? 'Untitled Project');
                    $description = htmlspecialchars($project['description'] ?? '');
                    $location = htmlspecialchars($project['location'] ?? '');
                    $start_date = !empty($project['start_date']) ? date('n/j/Y', strtotime($project['start_date'])) : '';
                    $end_date   = !empty($project['end_date'])   ? date('n/j/Y', strtotime($project['end_date']))   : '';
                    // show status from DB (no hardcoded 'Completed')
                    $statusText = htmlspecialchars($project['status'] ?? 'Unknown');
                    // create simple slug class (safe CSS class)
                    $statusSlug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($project['status'] ?? 'unknown'));
                    $orderId = htmlspecialchars($project['order_id'] ?? '');
                    $estimatedCostRaw = $project['estimated_cost'] ?? null;
                    $estimatedCost = ($estimatedCostRaw !== null && $estimatedCostRaw !== '') ? number_format((float)$estimatedCostRaw, 2) : '';
                    $projectContract = htmlspecialchars($project['project_contract'] ?? '');
                    $clientId = htmlspecialchars($project['client_id'] ?? '');
                    $acceptedContractorId = htmlspecialchars($project['accepted_contractor_id'] ?? '');
                    $createdAt = !empty($project['created_at']) ? date('n/j/Y', strtotime($project['created_at'])) : '';
                    $avgRating = isset($project['avg_rating']) && $project['avg_rating'] !== null ? (float)$project['avg_rating'] : null;
                    $ratingCount = isset($project['rating_count']) ? (int)$project['rating_count'] : 0;
                ?>
                    <div class="project-card"
                         data-order-id="<?php echo $orderId; ?>"
                         data-client-id="<?php echo $clientId; ?>"
                         data-contractor-id="<?php echo $acceptedContractorId; ?>"
                         data-estimated-cost="<?php echo $estimatedCost; ?>"
                         data-project-id="<?php echo $pid; ?>">
                        <!-- Project Image -->
                        <div class="project-image">
                            <?php
                            $imageDisplayed = false;
                            if (!empty($project['images'])) {
                                // show first image with valid src
                                foreach ($project['images'] as $img) {
                                    if (!empty($img['src'])) {
                                        // choose this one
                                        $safeSrc = htmlspecialchars($img['src']);
                                        $alt = $title . ' - image';
                                        // link to the image file (keeps your existing behavior)
                                        echo "<a href=\"{$safeSrc}\" target=\"_blank\" rel=\"noopener noreferrer\"><img src=\"{$safeSrc}\" alt=\"".htmlspecialchars($alt)."\"></a>";
                                        $imageDisplayed = true;
                                        break;
                                    }
                                }
                            }
                            if (!$imageDisplayed): ?>
                                <div class="no-image">
                                    <i class="fas fa-image" style="font-size:36px;margin-right:8px;"></i>
                                    <span>No Image Available</span>
                                </div>
                            <?php endif; ?>

                            <!-- dynamic status badge (now shows DB value, e.g. Completed, In Progress, etc.) -->
                            <div class="project-status <?php echo 'status-' . $statusSlug; ?>">
                                <?php echo $statusText; ?>
                            </div>
                        </div>
                        
                        <div class="project-content">
                            <div class="project-header">
                                <h2 class="project-title"><?php echo $title; ?></h2>

                                <?php if ($avgRating !== null && $ratingCount > 0): ?>
                                    <div class="project-rating" title="<?php echo number_format($avgRating,1) . ' (' . $ratingCount . ' reviews)'; ?>">
                                        <span class="stars" aria-hidden="true">
                                            <?php
                                            $rounded = (int)round($avgRating);
                                            for ($i = 1; $i <= 5; $i++):
                                                if ($i <= $rounded):
                                                    echo '<i class="fas fa-star"></i>';
                                                else:
                                                    echo '<i class="far fa-star"></i>';
                                                endif;
                                            endfor;
                                            ?>
                                        </span>
                                        <span class="rating-value"><?php echo htmlspecialchars(number_format($avgRating,1)); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($orderId !== ''): ?>
                                <div class="project-order" style="font-size:12px;color:#9aa3ad;margin-top:-4px;">Order: <?php echo $orderId; ?></div>
                            <?php endif; ?>

                            <p class="contractor-name">by <?php echo htmlspecialchars($project['contractor_name'] ?? 'Unknown Contractor'); ?></p>
                            
                            <p class="project-description"><?php echo $description; ?></p>
                            
                            <?php if (!empty($location) || !empty($projectContract) || !empty($estimatedCost)): ?>
                                <div class="project-location" style="margin-top:6px;">
                                    <?php if (!empty($location)): ?>
                                        <i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>
                                        <span><?php echo $location; ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($estimatedCost)): ?>
                                        <span style="margin-left:12px;color:#6b7785;font-size:13px;">Estimated: SAR <?php echo $estimatedCost; ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($projectContract)): ?>
                                        <span style="margin-left:12px;color:#6b7785;font-size:13px;">Contract: <?php echo $projectContract; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="project-dates" style="margin-top:12px;color:#6b7785;font-size:13px;">
                                <?php 
                                if (!empty($start_date) && !empty($end_date)) {
                                    echo $start_date . ' - ' . $end_date;
                                } elseif (!empty($start_date)) {
                                    echo $start_date;
                                } else {
                                    echo 'Date not specified';
                                }
                                ?>
                            </div>
                            
                            <!-- View Details links to details page for this project -->
                            <a class="view-details-btn" href="project_details.php?id=<?php echo $pid; ?>" rel="noopener noreferrer">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-projects">
                    <p>No projects found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
