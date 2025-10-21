
<?php
// ad.php - Admin Dashboard
require_once __DIR__ . '/../config.php';

// Simple authentication like register.php - just get user_id from request
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? 0;

if ($user_id <= 0) {
    die("Missing user_id. Use ?user_id=... in URL");
}

try {
    $pdo = getPDO();
    
    // Get user data and verify admin role
    $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ? AND role = 'admin'");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        die("Admin user not found or insufficient permissions");
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submissions
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $posted_user_id = $_POST['user_id'] ?? 0;
    
    // Verify posted user_id matches URL user_id
    if ($posted_user_id != $user_id) {
        $errors[] = "User ID mismatch";
    } else {
        try {
            if ($action === 'update_profile') {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                
                $stmt = $pdo->prepare("UPDATE `User` SET name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $user_id]);
                $messages[] = "Profile updated successfully";
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            elseif ($action === 'update_user_status') {
                $target_user_id = (int)($_POST['target_user_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                
                if (in_array($status, ['active', 'inactive', 'suspended', 'pending'])) {
                    $stmt = $pdo->prepare("UPDATE `User` SET status = ? WHERE user_id = ?");
                    $stmt->execute([$status, $target_user_id]);
                    $messages[] = "User status updated successfully";
                } else {
                    $errors[] = "Invalid status value";
                }
            }
            
            elseif ($action === 'verify_contractor') {
                $contractor_id = (int)($_POST['contractor_id'] ?? 0);
                $action_type = $_POST['verify_action'] ?? '';
                
                // Note: We don't have a verification status field in ContractorProfile
                if ($action_type === 'approve') {
                    $messages[] = "Contractor approved (verification system not implemented)";
                } elseif ($action_type === 'reject') {
                    $messages[] = "Contractor rejected (verification system not implemented)";
                }
            }
            
            elseif ($action === 'update_notifications') {
                $email_notifs = isset($_POST['email_notifs']) ? 1 : 0;
                $push_notifs = isset($_POST['push_notifs']) ? 1 : 0;
                $sms_notifs = isset($_POST['sms_notifs']) ? 1 : 0;
                
                // Note: We don't have a notification settings table
                $messages[] = "Notification settings saved (storage not implemented)";
            }
            
            elseif ($action === 'update_settings') {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $timezone = trim($_POST['timezone'] ?? '');
                $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
                
                $stmt = $pdo->prepare("UPDATE `User` SET name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $user_id]);
                $messages[] = "Settings updated successfully";
                
                // Refresh admin data
                $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            elseif ($action === 'export_csv') {
                $from_date = $_POST['from_date'] ?? '';
                $to_date = $_POST['to_date'] ?? '';
                
                // Generate CSV data
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=admin_export_' . date('Y-m-d') . '.csv');
                
                $output = fopen('php://output', 'w');
                
                // Add BOM for UTF-8
                fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
                
                // Users data
                fputcsv($output, ['Users Report - Generated ' . date('Y-m-d H:i:s')]);
                fputcsv($output, ['From:', $from_date, 'To:', $to_date]);
                fputcsv($output, []); // Empty row
                
                $users_sql = "SELECT user_id, name, email, role, status, created_at FROM `User`";
                $users_params = [];
                
                if ($from_date && $to_date) {
                    $users_sql .= " WHERE created_at BETWEEN ? AND ?";
                    $users_params = [$from_date, $to_date];
                }
                
                $users_sql .= " ORDER BY created_at DESC";
                $users_stmt = $pdo->prepare($users_sql);
                $users_stmt->execute($users_params);
                $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                fputcsv($output, ['Users']);
                fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Created At']);
                
                foreach ($users as $user) {
                    fputcsv($output, [
                        $user['user_id'],
                        $user['name'],
                        $user['email'],
                        $user['role'],
                        $user['status'] ?? 'active',
                        $user['created_at']
                    ]);
                }
                
                fputcsv($output, []); // Empty row
                
                // Projects data
                $projects_sql = "SELECT project_id, title, status, estimated_cost, created_at FROM project";
                $projects_params = [];
                
                if ($from_date && $to_date) {
                    $projects_sql .= " WHERE created_at BETWEEN ? AND ?";
                    $projects_params = [$from_date, $to_date];
                }
                
                $projects_sql .= " ORDER BY created_at DESC";
                $projects_stmt = $pdo->prepare($projects_sql);
                $projects_stmt->execute($projects_params);
                $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                fputcsv($output, ['Projects']);
                fputcsv($output, ['ID', 'Title', 'Status', 'Estimated Cost', 'Created At']);
                
                foreach ($projects as $project) {
                    fputcsv($output, [
                        $project['project_id'],
                        $project['title'],
                        $project['status'],
                        $project['estimated_cost'],
                        $project['created_at']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Get statistics for KPIs
$stats = [];

// Active users (users created in last 30 days)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `User` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Verified contractors (all contractors for now)
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `User` WHERE role = 'contractor'");
$stmt->execute();
$stats['contractors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending verification (placeholder - no verification system)
$stats['pending_verification'] = 0;

// Open complaints (placeholder - no complaints system)
$stats['open_complaints'] = 0;

// Get users for management - FIXED: Use $_GET for filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$users_sql = "SELECT u.user_id, u.name, u.email, u.role, u.status, u.created_at
              FROM `User` u 
              WHERE 1=1";

$users_params = [];

if ($search) {
    $users_sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $users_params[] = $search_term;
    $users_params[] = $search_term;
}

// FIXED: Proper role filter condition
if ($role_filter && in_array($role_filter, ['client', 'contractor', 'admin'])) {
    $users_sql .= " AND u.role = ?";
    $users_params[] = $role_filter;
}

$users_sql .= " ORDER BY u.created_at DESC";
$users_stmt = $pdo->prepare($users_sql);
$users_stmt->execute($users_params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contractors for verification (all contractors)
$contractors_stmt = $pdo->prepare("
    SELECT u.user_id, u.name, u.email, cp.specialization, cp.license_number, cp.experience_years
    FROM `User` u 
    LEFT JOIN ContractorProfile cp ON u.user_id = cp.contractor_id 
    WHERE u.role = 'contractor'
    ORDER BY u.created_at DESC
");
$contractors_stmt->execute();
$contractors = $contractors_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get reports data
$reports = [];

// Users summary
$users_summary_stmt = $pdo->prepare("
    SELECT role, COUNT(*) as count 
    FROM `User` 
    GROUP BY role
");
$users_summary_stmt->execute();
$users_summary = $users_summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$reports['users'] = "";
foreach ($users_summary as $summary) {
    $reports['users'] .= "{$summary['role']}: {$summary['count']}\n";
}

// Projects summary
$projects_summary_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM project 
    GROUP BY status
");
$projects_summary_stmt->execute();
$projects_summary = $projects_summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$reports['projects'] = "";
foreach ($projects_summary as $summary) {
    $reports['projects'] .= "{$summary['status']}: {$summary['count']}\n";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title data-i18n="title">لوحة الأدمن — مِعمار</title>

  <!-- ربط التنسيقات -->
  <link rel="stylesheet" href="adst.css">
  <link rel="stylesheet" href="modal-styles.css">


  <!-- ربط سكربت الصفحة (يُحمَّل بعد DOM بسبب defer) -->
  <script src="lsad.js" defer></script>

  <style>
    .role-badge {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 500;
      display: inline-block;
    }

    .role-admin {
      background: #d1ecf1;
      color: #0c5460;
    }

    .role-contractor {
      background: #d4edda;
      color: #155724;
    }

    .role-client {
      background: #fff3cd;
      color: #856404;
    }

    .status {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: 500;
      display: inline-block;
    }

    .status.s-active {
      background: #d4edda;
      color: #155724;
    }

    .status.s-inactive {
      background: #e2e3e5;
      color: #383d41;
    }

    .status.s-suspended {
      background: #f8d7da;
      color: #721c24;
    }

    .status.s-pending {
      background: #fff3cd;
      color: #856404;
    }

    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn-small {
      padding: 4px 8px;
      font-size: 12px;
    }

    .controls {
      display: flex;
      gap: 12px;
      align-items: end;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .table-wrap {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 12px 8px;
      text-align: right;
      border-bottom: 1px solid #e2e8f0;
    }

    th {
      font-weight: 600;
      background-color: #f8fafc;
    }
  </style>
</head>
<body>
  <!-- زر تبديل اللغة: يغيّر AR/EN + اتجاه الصفحة -->
  <button id="langToggle" class="lang-toggle" data-i18n-attr="title:toggle_title,aria-label:toggle_title">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line>
      <path d="M12 2a15.3 15.3 0 0 1 0 20M12 2a15.3 15.3 0 0 0 0 20"></path>
    </svg>
    <span id="langLabel">EN</span>
  </button>

  <!-- الرأس: اسم الأدمن + زر يفتح اللوح الجانبي -->
  <header class="header">
    <div class="brand" aria-hidden="true">Mimar — Admin</div>
    <div class="admin">
      <div id="adminName"><?php echo htmlspecialchars($admin['name']); ?></div>
      <button class="avatar" id="openProfile" aria-label="Open profile"><?php echo strtoupper(substr($admin['name'], 0, 1)); ?></button>
    </div>
  </header>

  <!-- اللوح الجانبي (Drawer) لعرض/تعديل بيانات الأدمن بسرعة -->
  <div class="drawer" id="profileDrawer" aria-hidden="true">
    <div class="drawer-overlay" id="drawerOverlay"></div>

    <!-- ملاحظة: RTL يظهر من اليسار، وLTR من اليمين -->
    <aside class="drawer-panel" role="dialog" aria-labelledby="profileTitle">
      <div class="drawer-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div class="avatar" style="background:#005f46;color:#fff;width:44px;height:44px"><?php echo strtoupper(substr($admin['name'], 0, 1)); ?></div>
          <div>
            <h3 id="profileTitle" style="margin:0;font-size:18px" data-i18n="profile_title">الملف الشخصي</h3>
            <div class="muted" id="profileEmail"><?php echo htmlspecialchars($admin['email']); ?></div>
          </div>
        </div>
        <button id="closeDrawer" class="btn btn-outline small" aria-label="Close">×</button>
      </div>

      <div class="drawer-body">
        <!-- Messages and Errors -->
        <?php if (!empty($messages)): ?>
          <?php foreach ($messages as $message): ?>
            <div class="flash success"><?php echo htmlspecialchars($message); ?></div>
          <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
          <?php foreach ($errors as $error): ?>
            <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- كرت: بياناتي المختصرة -->
        <div class="card">
          <h3 style="margin-top:0" data-i18n="profile_info">معلوماتي</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div class="row">
              <div class="col">
                <label class="muted" data-i18n="name">الاسم</label>
                <input id="pName" name="name" class="input" type="text" value="<?php echo htmlspecialchars($admin['name']); ?>">
              </div>
              <div class="col">
                <label class="muted" data-i18n="email">البريد</label>
                <input id="pEmail" name="email" class="input" type="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
              </div>
            </div>

            <div class="row" style="margin-top:10px">
              <div class="col">
                <label class="muted" data-i18n="th_role">الدور</label>
                <input class="input" type="text" value="Admin" disabled>
              </div>
              <div class="col">
                <label class="muted" data-i18n="th_status">الحالة</label>
                <!-- شارة الحالة (بدلاً من input) -->
                <div id="pStatusChip" class="status s-active">Active</div>
              </div>
            </div>

            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
              <button class="btn" type="submit" data-i18n="save">حفظ</button>
              <button class="btn btn-outline" id="gotoSettings" data-i18n="settings_title">الإعدادات</button>
              <a href="logout.php" class="btn btn-danger" data-i18n="btn_logout">تسجيل الخروج</a>
            </div>
          </form>
        </div>

        <!-- كرت: إعدادات أمان سريعة -->
        <div class="card">
          <h3 style="margin-top:0" data-i18n="security">الأمان</h3>
          <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" id="p2fa"> <span data-i18n="enable_2fa">تفعيل 2FA</span>
          </label>
        </div>
      </div>
    </aside>
  </div>

  <!-- المحتوى الرئيسي -->
  <main class="wrap">
    <!-- مؤشرات سريعة (KPIs) -->
    <section class="kpis">
      <div class="kpi"><h4 data-i18n="kpi_users">مستخدمون نشطون</h4><div id="kpiUsers" class="num"><?php echo $stats['active_users']; ?></div></div>
      <div class="kpi"><h4 data-i18n="kpi_contractors">مقاولون موثّقون</h4><div id="kpiContractors" class="num"><?php echo $stats['contractors']; ?></div></div>
      <div class="kpi"><h4 data-i18n="kpi_pending">طلبات تحقق معلّقة</h4><div id="kpiPending" class="num"><?php echo $stats['pending_verification']; ?></div></div>
      <div class="kpi"><h4 data-i18n="kpi_complaints">شكاوى مفتوحة</h4><div id="kpiComplaints" class="num"><?php echo $stats['open_complaints']; ?></div></div>
    </section>

    <!-- تبويبات -->
    <nav class="tabs" role="tablist">
      <button class="tab" role="tab" aria-selected="true"  data-tab="users"       data-i18n="tab_users">المستخدمون</button>
      <button class="tab" role="tab" aria-selected="false" data-tab="verify"      data-i18n="tab_verify">التحقّق</button>
      <button class="tab" role="tab" aria-selected="false" data-tab="complaints"  data-i18n="tab_complaints">الشكاوى</button>
      <button class="tab" role="tab" aria-selected="false" data-tab="reports"     data-i18n="tab_reports">التقارير</button>
      <button class="tab" role="tab" aria-selected="false" data-tab="notifs"      data-i18n="tab_notifs">الإشعارات</button>
      <button class="tab" role="tab" aria-selected="false" data-tab="settings"    data-i18n="tab_settings">الإعدادات</button>
    </nav>

    <!-- لوحة إدارة المستخدمين -->
    <section id="panel-users" class="panel active">
      <div class="card">
        <h3 data-i18n="users_title">إدارة المستخدمين</h3>

        <!-- فلاتر بسيطة - FIXED: Form method is GET and maintains parameters -->
        <form method="GET" class="controls">
          <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
          <input id="userSearch" name="search" class="input" type="search" data-i18n-attr="placeholder:search_users_ph" placeholder="ابحث بالاسم أو البريد" value="<?php echo htmlspecialchars($search); ?>" />
          <select id="roleFilter" name="role" class="select">
            <option value="" data-i18n="any_role">أي دور</option>
            <option value="client" <?php echo $role_filter === 'client' ? 'selected' : ''; ?> data-i18n="role_client">عميل</option>
            <option value="contractor" <?php echo $role_filter === 'contractor' ? 'selected' : ''; ?> data-i18n="role_contractor">مقاول</option>
            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?> data-i18n="role_admin">أدمن</option>
          </select>
          <button type="submit" class="btn btn-outline" data-i18n="filter">تصفية</button>
          <a href="ad.php?user_id=<?php echo $user_id; ?>" class="btn btn-outline" data-i18n="reset">إعادة تعيين</a>
        </form>

        <!-- جدول المستخدمين -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th data-i18n="th_name">الاسم</th>
                <th data-i18n="th_email">البريد</th>
                <th data-i18n="th_role">الدور</th>
                <th data-i18n="th_status">الحالة</th>
                <th data-i18n="th_created">تاريخ التسجيل</th>
                <th data-i18n="th_actions">إجراءات</th>
              </tr>
            </thead>
            <tbody id="usersBody">
              <?php foreach ($users as $user): ?>
                <tr>
                  <td><?php echo htmlspecialchars($user['name']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                      <?php 
                      $role_labels = [
                        'admin' => 'أدمن',
                        'contractor' => 'مقاول', 
                        'client' => 'عميل'
                      ];
                      echo $role_labels[$user['role']] ?? $user['role'];
                      ?>
                    </span>
                  </td>
                  <td>
                    <!-- Display user status -->
                    <?php 
                    $status = $user['status'] ?? 'active';
                    $status_class = 'status s-active';
                    if ($status === 'inactive') {
                        $status_class = 'status s-inactive';
                    } elseif ($status === 'suspended') {
                        $status_class = 'status s-suspended';
                    } elseif ($status === 'pending') {
                        $status_class = 'status s-pending';
                    }
                    ?>
                    <div class="<?php echo $status_class; ?>">
                      <?php 
                      $status_labels = [
                        'active' => 'نشط',
                        'inactive' => 'غير نشط', 
                        'suspended' => 'موقوف',
                        'pending' => 'قيد الانتظار'
                      ];
                      echo $status_labels[$status] ?? $status;
                      ?>
                    </div>
                  </td>
                  <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>

			<td>
			  <div class="action-buttons">
				<button class="btn btn-small btn-outline" 
						onclick="openEditModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>', '<?php echo $user['status'] ?? 'active'; ?>')" 
						data-i18n="btn_edit">تعديل</button>
				<button class="btn btn-small btn-danger" 
						onclick="openDeleteModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" 
						data-i18n="btn_delete">حذف</button>
			  </div>
			</td>
              <?php endforeach; ?>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 20px;" class="muted">
                    لا توجد نتائج
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- طلبات التحقق من المقاولين -->
    <section id="panel-verify" class="panel">
      <div class="card">
        <h3 data-i18n="verify_title">طلبات التحقّق من المقاولين</h3>
        <div class="muted" data-i18n="verify_hint">راجعي المستندات ثم وافقي أو ارفضي.</div>
        <div id="verifyList" class="row" style="margin-top:12px">
          <?php foreach ($contractors as $contractor): ?>
            <div class="card" style="flex: 1; min-width: 300px;">
              <h4><?php echo htmlspecialchars($contractor['name']); ?></h4>
              <p class="muted"><?php echo htmlspecialchars($contractor['email']); ?></p>
              <p><strong>التخصص:</strong> <?php echo htmlspecialchars($contractor['specialization'] ?? 'N/A'); ?></p>
              <p><strong>رقم الترخيص:</strong> <?php echo htmlspecialchars($contractor['license_number'] ?? 'N/A'); ?></p>
              <p><strong>سنوات الخبرة:</strong> <?php echo htmlspecialchars($contractor['experience_years'] ?? 'N/A'); ?></p>
              
              <form method="POST" style="margin-top: 12px;">
                <input type="hidden" name="action" value="verify_contractor">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="contractor_id" value="<?php echo $contractor['user_id']; ?>">
                
                <div style="display: flex; gap: 8px;">
                  <button type="submit" name="verify_action" value="approve" class="btn btn-success">موافقة</button>
                  <button type="submit" name="verify_action" value="reject" class="btn btn-danger">رفض</button>
                </div>
              </form>
            </div>
          <?php endforeach; ?>
          
          <?php if (empty($contractors)): ?>
            <div class="muted" style="text-align: center; padding: 20px;">
              لا توجد طلبات تحقق حالياً
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- الشكاوى -->
    <section id="panel-complaints" class="panel">
      <div class="card">
        <h3 data-i18n="complaints_title">الشكاوى</h3>
        <div class="muted" style="text-align: center; padding: 40px;">
          نظام إدارة الشكاوى قيد التطوير
        </div>
      </div>
    </section>

    <!-- التقارير + تصدير CSV -->
    <section id="panel-reports" class="panel">
      <div class="card">
        <h3 data-i18n="reports_title">التقارير</h3>

        <form method="POST" class="controls">
          <input type="hidden" name="action" value="export_csv">
          <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
          <label class="muted" data-i18n="from">من</label>
          <input id="fromDate" name="from_date" type="date" class="input">
          <label class="muted" data-i18n="to">إلى</label>
          <input id="toDate" name="to_date" type="date" class="input">
          <button type="submit" id="exportCsv" class="btn btn-outline" data-i18n="export_csv">تصدير CSV</button>
        </form>

        <div class="row">
          <div class="col card">
            <h3 data-i18n="r_users">ملخّص المستخدمين</h3>
            <div id="rUsers" class="muted" style="white-space: pre-line;"><?php echo htmlspecialchars($reports['users']); ?></div>
          </div>
          <div class="col card">
            <h3 data-i18n="r_projects">ملخّص المشروعات</h3>
            <div id="rProjects" class="muted" style="white-space: pre-line;"><?php echo htmlspecialchars($reports['projects']); ?></div>
          </div>
        </div>
      </div>
    </section>

    <!-- إعدادات الإشعارات -->
    <section id="panel-notifs" class="panel">
      <div class="card">
        <h3 data-i18n="notifs_title">إعدادات الإشعارات</h3>
        <form method="POST">
          <input type="hidden" name="action" value="update_notifications">
          <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
          
          <div class="row">
            <div class="col">
              <label><input type="checkbox" id="nEmail" name="email_notifs" checked> <span data-i18n="n_email">تنبيهات البريد</span></label><br>
              <label><input type="checkbox" id="nPush" name="push_notifs"> <span data-i18n="n_push">تنبيهات فورية (Push)</span></label><br>
              <label><input type="checkbox" id="nSms" name="sms_notifs"> <span data-i18n="n_sms">رسائل SMS</span></label>
            </div>
          </div>
          <div style="margin-top:12px">
            <button class="btn" type="submit" data-i18n="save">حفظ</button>
          </div>
        </form>
      </div>
    </section>

    <!-- إعدادات عامة -->
    <section id="panel-settings" class="panel">
      <div class="card">
        <h3 data-i18n="settings_title">الإعدادات</h3>
        <form method="POST">
          <input type="hidden" name="action" value="update_settings">
          <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
          
          <div class="row">
            <div class="col">
              <label class="muted" data-i18n="name">الاسم</label>
              <input id="setName" name="name" class="input" type="text" value="<?php echo htmlspecialchars($admin['name']); ?>">
            </div>
            <div class="col">
              <label class="muted" data-i18n="email">البريد</label>
              <input id="setEmail" name="email" class="input" type="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
            </div>
          </div>
          <div class="row" style="margin-top:10px">
            <div class="col">
              <label class="muted" data-i18n="timezone">المنطقة الزمنية</label>
              <select id="setTz" name="timezone" class="select">
                <option>Asia/Riyadh</option>
                <option>UTC</option>
              </select>
            </div>
            <div class="col">
              <label class="muted" data-i18n="security">الأمان</label>
              <label style="display:block;margin-top:6px">
                <input type="checkbox" id="set2fa" name="enable_2fa"> <span data-i18n="enable_2fa">تفعيل 2FA</span>
              </label>
            </div>
          </div>
          <div style="margin-top:12px">
            <button class="btn" type="submit" data-i18n="save">حفظ</button>
          </div>
        </form>
      </div>
    </section>

<!-- Edit User Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="edit_user_title">تعديل المستخدم</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editUserForm">
            <div class="modal-body">
                <input type="hidden" id="edit_user_id" name="target_user_id">
                <input type="hidden" name="admin_user_id" value="<?php echo $user_id; ?>">
                
                <div class="form-group">
                    <label class="form-label" data-i18n="name">الاسم</label>
                    <input type="text" id="edit_name" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="email">البريد الإلكتروني</label>
                    <input type="email" id="edit_email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="th_role">الدور</label>
                    <select id="edit_role" name="role" class="form-select" required>
                        <option value="client" data-i18n="role_client">عميل</option>
                        <option value="contractor" data-i18n="role_contractor">مقاول</option>
                        <option value="admin" data-i18n="role_admin">أدمن</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" data-i18n="th_status">الحالة</label>
                    <select id="edit_status" name="status" class="form-select" required>
                        <option value="active" data-i18n="status_active">نشط</option>
                        <option value="inactive" data-i18n="status_inactive">غير نشط</option>
                        <option value="suspended" data-i18n="status_suspended">موقوف</option>
                        <option value="pending" data-i18n="status_pending">قيد الانتظار</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()" data-i18n="cancel">إلغاء</button>
                <button type="submit" class="btn" data-i18n="save_changes">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" data-i18n="delete_user_title">حذف المستخدم</h3>
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form id="deleteUserForm">
            <div class="modal-body">
                <input type="hidden" id="delete_user_id" name="target_user_id">
                <input type="hidden" name="admin_user_id" value="<?php echo $user_id; ?>">
                
                <div class="delete-warning">
                    <p data-i18n="delete_warning">⚠️ تحذير: هذا الإجراء لا يمكن التراجع عنه</p>
                </div>
                
                <div class="user-info">
                    <p><strong data-i18n="name">الاسم:</strong> <span id="delete_user_name"></span></p>
                    <p><strong data-i18n="user_id">رقم المستخدم:</strong> <span id="delete_user_id_display"></span></p>
                </div>
                
                <p data-i18n="delete_confirmation">هل أنت متأكد من أنك تريد حذف هذا المستخدم؟</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()" data-i18n="cancel">إلغاء</button>
                <button type="submit" class="btn btn-danger" data-i18n="confirm_delete">تأكيد الحذف</button>
            </div>
        </form>
    </div>
</div>
	
  </main>

<script>
// Language toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const langToggle = document.getElementById('langToggle');
    const langLabel = document.getElementById('langLabel');
    
    // Check for saved language preference or default to Arabic
    const currentLang = localStorage.getItem('admin-lang') || 'ar';
    applyLanguage(currentLang);

    langToggle.addEventListener('click', function() {
        const newLang = document.documentElement.lang === 'ar' ? 'en' : 'ar';
        applyLanguage(newLang);
        localStorage.setItem('admin-lang', newLang);
    });

    function applyLanguage(lang) {
        document.documentElement.lang = lang;
        document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        langLabel.textContent = lang === 'ar' ? 'EN' : 'AR';
        
        // Update all translatable elements
        updateTranslations(lang);
    }

    function updateTranslations(lang) {
        const translations = {
            ar: {
                title: 'لوحة الأدمن — مِعمار',
                toggle_title: 'تبديل اللغة',
                profile_title: 'الملف الشخصي',
                profile_info: 'معلوماتي',
                name: 'الاسم',
                email: 'البريد',
                th_role: 'الدور',
                th_status: 'الحالة',
                save: 'حفظ',
                settings_title: 'الإعدادات',
                btn_logout: 'تسجيل الخروج',
                security: 'الأمان',
                enable_2fa: 'تفعيل 2FA',
                kpi_users: 'مستخدمون نشطون',
                kpi_contractors: 'مقاولون موثّقون',
                kpi_pending: 'طلبات تحقق معلّقة',
                kpi_complaints: 'شكاوى مفتوحة',
                tab_users: 'المستخدمون',
                tab_verify: 'التحقّق',
                tab_complaints: 'الشكاوى',
                tab_reports: 'التقارير',
                tab_notifs: 'الإشعارات',
                tab_settings: 'الإعدادات',
                users_title: 'إدارة المستخدمين',
                search_users_ph: 'ابحث بالاسم أو البريد',
                any_role: 'أي دور',
                role_client: 'عميل',
                role_contractor: 'مقاول',
                role_admin: 'أدمن',
                th_name: 'الاسم',
                th_email: 'البريد',
                th_created: 'تاريخ التسجيل',
                th_actions: 'إجراءات',
                verify_title: 'طلبات التحقّق من المقاولين',
                verify_hint: 'راجعي المستندات ثم وافقي أو ارفضي.',
                complaints_title: 'الشكاوى',
                reports_title: 'التقارير',
                from: 'من',
                to: 'إلى',
                export_csv: 'تصدير CSV',
                r_users: 'ملخّص المستخدمين',
                r_projects: 'ملخّص المشروعات',
                notifs_title: 'إعدادات الإشعارات',
                n_email: 'تنبيهات البريد',
                n_push: 'تنبيهات فورية (Push)',
                n_sms: 'رسائل SMS',
                filter: 'تصفية',
                reset: 'إعادة تعيين',
                timezone: 'المنطقة الزمنية',
                btn_edit: 'تعديل',
                btn_delete: 'حذف',
                btn_activate: 'تفعيل', 
                btn_deactivate: 'إيقاف',
                btn_promote: 'ترقية',
                btn_demote: 'خفض',
                edit_user_title: 'تعديل المستخدم',
                delete_user_title: 'حذف المستخدم',
                delete_warning: '⚠️ تحذير: هذا الإجراء لا يمكن التراجع عنه',
                user_id: 'رقم المستخدم',
                delete_confirmation: 'هل أنت متأكد من أنك تريد حذف هذا المستخدم؟',
                cancel: 'إلغاء',
                save_changes: 'حفظ التغييرات',
                confirm_delete: 'تأكيد الحذف'
            },
            en: {
                title: 'Admin Dashboard — Mimar',
                toggle_title: 'Toggle language',
                profile_title: 'Profile',
                profile_info: 'My Information',
                name: 'Name',
                email: 'Email',
                th_role: 'Role',
                th_status: 'Status',
                save: 'Save',
                settings_title: 'Settings',
                btn_logout: 'Logout',
                security: 'Security',
                enable_2fa: 'Enable 2FA',
                kpi_users: 'Active Users',
                kpi_contractors: 'Verified Contractors',
                kpi_pending: 'Pending Verifications',
                kpi_complaints: 'Open Complaints',
                tab_users: 'Users',
                tab_verify: 'Verification',
                tab_complaints: 'Complaints',
                tab_reports: 'Reports',
                tab_notifs: 'Notifications',
                tab_settings: 'Settings',
                users_title: 'User Management',
                search_users_ph: 'Search by name or email',
                any_role: 'Any Role',
                role_client: 'Client',
                role_contractor: 'Contractor',
                role_admin: 'Admin',
                th_name: 'Name',
                th_email: 'Email',
                th_created: 'Registration Date',
                th_actions: 'Actions',
                verify_title: 'Contractor Verification Requests',
                verify_hint: 'Review documents then approve or reject.',
                complaints_title: 'Complaints',
                reports_title: 'Reports',
                from: 'From',
                to: 'To',
                export_csv: 'Export CSV',
                r_users: 'Users Summary',
                r_projects: 'Projects Summary',
                notifs_title: 'Notification Settings',
                n_email: 'Email Notifications',
                n_push: 'Push Notifications',
                n_sms: 'SMS Messages',
                filter: 'Filter',
                reset: 'Reset',
                timezone: 'Timezone',
                btn_edit: 'Edit',
                btn_delete: 'Delete',
                btn_activate: 'Activate',
                btn_deactivate: 'Deactivate', 
                btn_promote: 'Promote',
                btn_demote: 'Demote',
                edit_user_title: 'Edit User',
                delete_user_title: 'Delete User',
                delete_warning: '⚠️ Warning: This action cannot be undone',
                user_id: 'User ID',
                delete_confirmation: 'Are you sure you want to delete this user?',
                cancel: 'Cancel',
                save_changes: 'Save Changes',
                confirm_delete: 'Confirm Delete'
            }
        };

        const texts = translations[lang] || translations.ar;
        
        // Update all elements with data-i18n attribute
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (texts[key]) {
                el.textContent = texts[key];
            }
        });

        // Update all elements with data-i18n-attr attribute
        document.querySelectorAll('[data-i18n-attr]').forEach(el => {
            const attrPairs = el.getAttribute('data-i18n-attr').split(',');
            attrPairs.forEach(pair => {
                const [attr, key] = pair.split(':').map(s => s.trim());
                if (attr && key && texts[key]) {
                    el.setAttribute(attr, texts[key]);
                }
            });
        });
    }

    // Tab functionality
    const tabs = document.querySelectorAll('.tab');
    const panels = document.querySelectorAll('.panel');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.getAttribute('data-tab');
            
            // Update tabs
            tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
            tab.setAttribute('aria-selected', 'true');
            
            // Update panels
            panels.forEach(panel => {
                panel.classList.remove('active');
                if (panel.id === 'panel-' + targetTab) {
                    panel.classList.add('active');
                }
            });
        });
    });

    // Profile drawer functionality
    const openProfileBtn = document.getElementById('openProfile');
    const profileDrawer = document.getElementById('profileDrawer');
    const closeDrawerBtn = document.getElementById('closeDrawer');
    const drawerOverlay = document.getElementById('drawerOverlay');

    if (openProfileBtn) {
        openProfileBtn.addEventListener('click', () => {
            profileDrawer.setAttribute('aria-hidden', 'false');
        });
    }

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', () => {
            profileDrawer.setAttribute('aria-hidden', 'true');
        });
    }

    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', () => {
            profileDrawer.setAttribute('aria-hidden', 'true');
        });
    }

    // Settings navigation
    const gotoSettingsBtn = document.getElementById('gotoSettings');
    if (gotoSettingsBtn) {
        gotoSettingsBtn.addEventListener('click', () => {
            profileDrawer.setAttribute('aria-hidden', 'true');
            
            // Switch to settings tab
            tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
            document.querySelector('[data-tab="settings"]').setAttribute('aria-selected', 'true');
            
            panels.forEach(panel => {
                panel.classList.remove('active');
                if (panel.id === 'panel-settings') {
                    panel.classList.add('active');
                }
            });
        });
    }

    // Form Submissions
    const editForm = document.getElementById('editUserForm');
    const deleteForm = document.getElementById('deleteUserForm');

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateUser();
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            deleteUser();
        });
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        
        if (editModal.classList.contains('active') && 
            event.target === editModal) {
            closeEditModal();
        }
        
        if (deleteModal.classList.contains('active') && 
            event.target === deleteModal) {
            closeDeleteModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeEditModal();
            closeDeleteModal();
        }
    });
});

// Popup-style Modal Functions
function openEditModal(userId, name, email, role, status) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').classList.add('active');
}



function openDeleteModal(userId, userName) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_user_id_display').textContent = userId;
    document.getElementById('delete_user_name').textContent = userName;
    document.getElementById('deleteModal').classList.add('active');
}


function positionPopupNearElement(modal, triggerElement) {
    const rect = triggerElement.getBoundingClientRect();
    const modalElement = modal.querySelector('.modal');
    
    // Calculate position to appear near the button
    let top = rect.bottom + window.scrollY + 5;
    let left = rect.left + window.scrollX;
    
    // Adjust for RTL
    if (document.documentElement.dir === 'rtl') {
        left = rect.right + window.scrollX - modalElement.offsetWidth;
    }
    
    // Ensure it stays within viewport
    const viewport = {
        width: window.innerWidth,
        height: window.innerHeight
    };
    
    // Horizontal boundary check
    if (left + modalElement.offsetWidth > viewport.width) {
        left = viewport.width - modalElement.offsetWidth - 20;
    }
    if (left < 20) {
        left = 20;
    }
    
    // Vertical boundary check
    if (top + modalElement.offsetHeight > viewport.height + window.scrollY) {
        // Show above the button if not enough space below
        top = rect.top + window.scrollY - modalElement.offsetHeight - 5;
    }
    if (top < window.scrollY + 20) {
        top = window.scrollY + 20;
    }
    
    modalElement.style.top = top + 'px';
    modalElement.style.left = left + 'px';
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function updateUser() {
    const formData = new FormData(document.getElementById('editUserForm'));
    
    fetch('update.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم تحديث المستخدم بنجاح');
            closeEditModal();
            location.reload(); // Refresh to show changes
        } else {
            alert('خطأ: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تحديث المستخدم');
    });
}

function deleteUser() {
    if (!confirm('هل أنت متأكد من الحذف؟ هذا الإجراء لا يمكن التراجع عنه.')) {
        return;
    }
    
    const formData = new FormData(document.getElementById('deleteUserForm'));
    
    fetch('delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم حذف المستخدم بنجاح');
            closeDeleteModal();
            location.reload(); // Refresh to show changes
        } else {
            alert('خطأ: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء حذف المستخدم');
    });
}
</script>



</body>
</html>
[file content end]