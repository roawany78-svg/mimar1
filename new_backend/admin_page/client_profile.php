<?php
// client_profile.php
require_once __DIR__ . '/../config.php';

// Get user_id from URL or form data
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? 1; // Default to 1 for demo

try {
    $pdo = getPDO();
    
    // Get client profile data
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.email, u.role, u.created_at,
               cp.location, cp.profile_image, cp.member_since
        FROM `User` u 
        LEFT JOIN ClientProfile cp ON u.user_id = cp.client_id 
        WHERE u.user_id = ? AND u.role = 'client'
    ");
    $stmt->execute([$user_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        die("Client not found");
    }
    
    // Get recent orders (projects)
    $orders_stmt = $pdo->prepare("
        SELECT p.project_id, p.order_id, p.title, p.status, p.created_at, 
               p.estimated_cost, p.start_date, p.end_date,
               u.name as contractor_name
        FROM Project p
        LEFT JOIN `User` u ON p.accepted_contractor_id = u.user_id
        WHERE p.client_id = ?
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $orders_stmt->execute([$user_id]);
    $recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get saved contractors
    $saved_stmt = $pdo->prepare("
        SELECT u.user_id, u.name, cp.specialization, 
               AVG(r.stars) as rating,
               COUNT(r.rating_id) as review_count
        FROM SavedContractor sc
        JOIN `User` u ON sc.contractor_id = u.user_id
        LEFT JOIN ContractorProfile cp ON u.user_id = cp.contractor_id
        LEFT JOIN Rating r ON u.user_id = r.contractor_id
        WHERE sc.client_id = ?
        GROUP BY u.user_id, u.name, cp.specialization
        LIMIT 4
    ");
    $saved_stmt->execute([$user_id]);
    $saved_contractors = $saved_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get contractors for dropdown
    $contractors_stmt = $pdo->prepare("
        SELECT u.user_id, u.name, cp.specialization 
        FROM `User` u 
        LEFT JOIN ContractorProfile cp ON u.user_id = cp.contractor_id 
        WHERE u.role = 'contractor'
    ");
    $contractors_stmt->execute();
    $contractors = $contractors_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_contractor'])) {
        $contractor_id = $_POST['contractor_id'];
        try {
            $save_stmt = $pdo->prepare("
                INSERT IGNORE INTO SavedContractor (client_id, contractor_id) 
                VALUES (?, ?)
            ");
            $save_stmt->execute([$user_id, $contractor_id]);
            header("Location: client_profile.php?user_id=$user_id&saved=1");
            exit;
        } catch (Exception $e) {
            $save_error = "Failed to save contractor: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_review'])) {
        $contractor_id = $_POST['contractor_id'];
        $stars = $_POST['stars'];
        $comment = $_POST['comment'];
        
        try {
            $review_stmt = $pdo->prepare("
                INSERT INTO Rating (stars, comment, rated_by, contractor_id) 
                VALUES (?, ?, ?, ?)
            ");
            $review_stmt->execute([$stars, $comment, $user_id, $contractor_id]);
            header("Location: client_profile.php?user_id=$user_id&reviewed=1");
            exit;
        } catch (Exception $e) {
            $review_error = "Failed to add review: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo htmlspecialchars($client['name']); ?></title>
    <link rel="stylesheet" href="client_profile.css">
</head>
<body>
 <div class="container">
        <!-- Include Unified Sidebar -->
        <?php include 'sidebar.php'; ?>
        <!-- Main Content -->

        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>My Profile</h1>
                <p>Manage your account and track your projects</p>
            </div>

            <!-- Success Messages -->
            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">Contractor saved successfully!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reviewed'])): ?>
                <div class="alert alert-success">Review added successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['order_added'])): ?>
                <div class="alert alert-success">Order created successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['order_error'])): ?>
                <div class="alert alert-danger">Failed to create order. Please try again.</div>
            <?php endif; ?>

            <!-- Recent Orders Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Recent Orders</h2>
                    <a href="#" class="view-all">View All Orders</a>
                </div>
                
                <div class="orders-grid">
                    <?php if (empty($recent_orders)): ?>
                        <p>No orders found.</p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-card">
                                <div class="order-info">
                                    <div class="order-title"><?php echo htmlspecialchars($order['title']); ?></div>
                                    <?php if ($order['contractor_name']): ?>
                                        <div class="order-contractor">by <?php echo htmlspecialchars($order['contractor_name']); ?></div>
                                    <?php endif; ?>
                                    <div class="order-date"><?php echo date('m/d/Y', strtotime($order['created_at'])); ?></div>
                                </div>
                                <div class="order-meta">
                                    <div class="order-status status-<?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </div>
                                    <div class="order-id"><?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Saved Contractors Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Saved Contractors</h2>
                    <a href="#" class="view-all">Browse More Contractors</a>
                </div>
                
                <div class="contractors-grid">
                    <?php if (empty($saved_contractors)): ?>
                        <p>No saved contractors.</p>
                    <?php else: ?>
                        <?php foreach ($saved_contractors as $contractor): ?>
                            <div class="contractor-card">
                                <div class="contractor-name"><?php echo htmlspecialchars($contractor['name']); ?></div>
                                <div class="contractor-specialization"><?php echo htmlspecialchars($contractor['specialization'] ?? 'General Contractor'); ?></div>
                                <div class="contractor-rating">
                                    ⭐ <?php echo number_format($contractor['rating'] ?? 0, 1); ?> 
                                    (<?php echo $contractor['review_count'] ?? 0; ?> reviews)
                                </div>
                                <button class="view-profile-btn">View Profile</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Quick Actions</h2>
                </div>
                
                <div class="actions-grid">
                    <div class="action-card" onclick="window.location.href='/new_backend/contractors_frontend/contractors.php'">
                        <div class="action-icon">🔍</div>
                        <div class="action-title">Find Contractors</div>
                    </div>
                    <div class="action-card" onclick="window.location.href='project_page.php'">
                        <div class="action-icon">📁</div>
                        <div class="action-title">Browse Projects</div>
                    </div>
                        <div class="action-card" onclick="window.location.href='my_orders.php?user_id=<?php echo $user_id; ?>'">
                        <div class="action-icon">📋</div>
                        <div class="action-title">My Orders</div>
                    </div>
                    <div class="action-card" onclick="window.location.href='support.php'">
                        <div class="action-icon">💬</div>
                        <div class="action-title">Support</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editProfileModal')">&times;</span>
            <h3>Edit Profile</h3>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($client['location'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Add Order Modal -->
    <div id="addOrderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addOrderModal')">&times;</span>
            <h3>Add New Order</h3>
            <form method="POST" action="add_order_handler.php" enctype="multipart/form-data">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="add_order" value="1">
                
                <div class="form-group">
                    <label>Order ID</label>
                    <input type="text" name="order_id" placeholder="e.g., ORD-001" required>
                </div>
                
                <div class="form-group">
                    <label>Project Title</label>
                    <input type="text" name="title" placeholder="e.g., Modern Villa Construction" required>
                </div>
                
                <div class="form-group">
                    <label>Project Description</label>
                    <textarea name="description" rows="3" placeholder="Describe your project..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Project Location</label>
                    <input type="text" name="location" placeholder="e.g., Riyadh, Saudi Arabia" required>
                </div>
                
                <div class="form-group">
                    <label>Select Contractor</label>
                    <select name="accepted_contractor_id" required>
                        <option value="">Select Contractor</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <?php $specialization = $contractor['specialization'] ? " - " . $contractor['specialization'] : ""; ?>
                            <option value="<?php echo $contractor['user_id']; ?>">
                                <?php echo htmlspecialchars($contractor['name'] . $specialization); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Estimated Cost (SAR)</label>
                    <input type="number" name="estimated_cost" step="0.01" placeholder="e.g., 850000.00" required>
                </div>
                
                <div class="form-group">
                    <label>Project Status</label>
                    <select name="status" required>
                        <option value="open">Open</option>
                        <option value="in progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>
                </div>
                
<div class="form-group">
    <label>Project Contract (PDF)</label>
    <input type="file" name="project_contract" accept=".pdf" required>
    <small>Upload the project contract in PDF format (max 10MB)</small>
</div>
                
                <div class="form-group">
                    <label>Project Specifications (one per line)</label>
                    <textarea name="specifications" rows="4" placeholder="Enter project specifications, one per line:&#10;• Reinforced concrete foundation&#10;• Steel frame construction&#10;• Premium ceramic tiles"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Attach Files (Optional)</label>
                    <input type="file" name="attachments[]" multiple>
                    <small>You can attach project images, plans, or documents</small>
                </div>
                
                <button type="submit" class="btn">Create Order</button>
            </form>
        </div>
    </div>

    <!-- Save Contractor Modal -->
    <div id="saveContractorModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('saveContractorModal')">&times;</span>
            <h3>Add Contractor to Saved</h3>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="save_contractor" value="1">
                <div class="form-group">
                    <label>Select Contractor</label>
                    <select name="contractor_id" required>
                        <option value="">Choose Contractor</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?php echo $contractor['user_id']; ?>">
                                <?php echo htmlspecialchars($contractor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Save Contractor</button>
            </form>
        </div>
    </div>

    <!-- Add Review Modal -->
    <div id="addReviewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addReviewModal')">&times;</span>
            <h3>Add Review for Contractor</h3>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="add_review" value="1">
                <div class="form-group">
                    <label>Select Contractor</label>
                    <select name="contractor_id" required>
                        <option value="">Choose Contractor</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?php echo $contractor['user_id']; ?>">
                                <?php echo htmlspecialchars($contractor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rating</label>
                    <div class="stars" id="ratingStars">
                        <span class="star" data-rating="1">★</span>
                        <span class="star" data-rating="2">★</span>
                        <span class="star" data-rating="3">★</span>
                        <span class="star" data-rating="4">★</span>
                        <span class="star" data-rating="5">★</span>
                    </div>
                    <input type="hidden" name="stars" id="selectedRating" required>
                </div>
                <div class="form-group">
                    <label>Comment</label>
                    <textarea name="comment" rows="4" placeholder="Share your experience..." required></textarea>
                </div>
                <button type="submit" class="btn">Submit Review</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
// Auto-open modal based on URL parameter
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        
        switch(action) {
            case 'edit_profile':
                openModal('editProfileModal');
                break;
            case 'add_order':
                openModal('addOrderModal');
                break;
            case 'save_contractor':
                openModal('saveContractorModal');
                break;
            case 'add_review':
                openModal('addReviewModal');
                break;
        }
        
        // Remove the action parameter from URL without reloading
        if (action) {
            const url = new URL(window.location);
            url.searchParams.delete('action');
            window.history.replaceState({}, '', url);
        }
    });
        // Star rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    document.getElementById('selectedRating').value = rating;
                    
                    stars.forEach(s => {
                        if (s.getAttribute('data-rating') <= rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>