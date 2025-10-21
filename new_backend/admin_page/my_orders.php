<?php
// my_orders.php
require_once __DIR__ . '/../config.php';

// Get user_id from URL or form data
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? 1;

try {
    $pdo = getPDO();
    
    // Get client profile data
    $client_stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.email, u.role, u.created_at,
               cp.location, cp.profile_image, cp.member_since
        FROM `User` u 
        LEFT JOIN ClientProfile cp ON u.user_id = cp.client_id 
        WHERE u.user_id = ? AND u.role = 'client'
    ");
    $client_stmt->execute([$user_id]);
    $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        die("Client not found");
    }
    
    // Get all orders (projects) for this client
    $orders_stmt = $pdo->prepare("
        SELECT p.project_id, p.order_id, p.title, p.status, p.created_at, 
               p.estimated_cost, p.start_date, p.end_date, p.project_contract,
               u.name as contractor_name, u.user_id as contractor_id
        FROM Project p
        LEFT JOIN `User` u ON p.accepted_contractor_id = u.user_id
        WHERE p.client_id = ?
        ORDER BY p.created_at DESC
    ");
    $orders_stmt->execute([$user_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total estimated value
    $total_estimated_value = 0;
    foreach ($orders as $order) {
        $total_estimated_value += $order['estimated_cost'] ?? 0;
    }
    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - <?php echo htmlspecialchars($client['name']); ?></title>
    <link rel="stylesheet" href="client_and_orders_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
 <div class="container">
        <!-- Include Unified Sidebar -->
        <?php include 'sidebar.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header Section -->
            <div class="header-section">
                <h1>Order Cart</h1>
                <p>Manage your pending project requests and track your orders with contracts.</p>
            </div>

            <!-- Orders Section -->
            <div class="orders-section">
                <div class="section-header">
                    <h2>Your Orders (<?php echo count($orders); ?>)</h2>
                </div>
                
                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Orders Yet</h3>
                        <p>You haven't placed any orders yet. Start by creating your first project.</p>
                        <button class="btn-primary">Create New Order</button>
                    </div>
                <?php else: ?>
                    <div class="orders-table-container">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Contractor</th>
                                    <th>Project Title</th>
                                    <th>Date</th>
                                    <th>Stand</th>
                                    <th>Followed Chat</th>
                                    <th>Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td class="order-id"><?php echo htmlspecialchars($order['order_id'] ?? 'N/A'); ?></td>
                                        <td class="contractor">
                                            <?php if ($order['contractor_name']): ?>
                                                <div class="contractor-info">
                                                    <div class="contractor-avatar">
                                                        <?php echo strtoupper(substr($order['contractor_name'], 0, 1)); ?>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($order['contractor_name']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="no-contractor">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="project-title"><?php echo htmlspecialchars($order['title']); ?></td>
                                        <td class="order-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('m/d/Y', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td class="order-stand">
                                            <div class="status-indicators">
                                                <span class="status-dot active"></span>
                                                <span class="status-dot active"></span>
                                                <span class="status-dot"></span>
                                            </div>
                                        </td>
                                        <td class="followed-chat">
                                            <div class="chat-time">
                                                <i class="far fa-clock"></i>
                                                <?php 
                                                    // Generate random time for demo
                                                    $hours = rand(10, 23);
                                                    $minutes = rand(10, 59);
                                                    $seconds = rand(10, 59);
                                                    echo sprintf('%02d:%02d:%02d AM', $hours, $minutes, $seconds);
                                                ?>
                                            </div>
                                        </td>
                                        <td class="order-actions">
                                            <div class="actions-container">
                                                <button class="chat-btn" title="Chat">
                                                    <i class="fas fa-comment-dots"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="dropdown-toggle">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a href="#" class="dropdown-item">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                        <a href="#" class="dropdown-item">
                                                            <i class="fas fa-edit"></i> Edit Order
                                                        </a>
                                                        <a href="#" class="dropdown-item">
                                                            <i class="fas fa-file-contract"></i> View Contract
                                                        </a>
                                                        <a href="#" class="dropdown-item delete">
                                                            <i class="fas fa-trash"></i> Delete Order
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary Section -->
            <div class="summary-section">
                <div class="summary-card">
                    <h3>Order Summary</h3>
                    <div class="total-value">
                        <strong>Total Estimated Value:</strong>
                        <div class="value-amount"><?php echo number_format($total_estimated_value, 0); ?> SAR</div>
                    </div>
                    <p class="summary-note">
                        Total costs vary, regulated as contractors' expectations and pricing specifications.
                    </p>
                    
                    <div class="help-section">
                        <h4>Need Help?</h4>
                        <p>Have questions about your orders or need a customer with the banking process!</p>
                        <div class="help-buttons">
                            <button class="btn-secondary">
                                <i class="fas fa-headset"></i> Contact Support
                            </button>
                            <button class="btn-outline">
                                <i class="fas fa-question-circle"></i> Your FAQ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdownMenu = this.nextElementSibling;
                    const isOpen = dropdownMenu.style.display === 'block';
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        menu.style.display = 'none';
                    });
                    
                    // Toggle current dropdown
                    dropdownMenu.style.display = isOpen ? 'none' : 'block';
                });
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            });
            
            // Chat button functionality
            const chatButtons = document.querySelectorAll('.chat-btn');
            chatButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.closest('tr').querySelector('.order-id').textContent;
                    alert(`Opening chat for order: ${orderId}`);
                    // In a real application, this would open a chat interface
                });
            });
        });
    </script>
</body>
</html>