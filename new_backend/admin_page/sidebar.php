<?php
// sidebar.php - Shared sidebar for client pages
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="profile-header">
        <div class="profile-image">
            <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
        </div>
        <h2 class="profile-name"><?php echo htmlspecialchars($client['name']); ?></h2>
        <div class="profile-role">Client</div>
        <div class="profile-email"><?php echo htmlspecialchars($client['email']); ?></div>
        <div class="profile-location"><?php echo htmlspecialchars($client['location'] ?? 'Location not set'); ?></div>
        <div class="member-since">Member since <?php echo date('m/d/Y', strtotime($client['member_since'] ?? $client['created_at'])); ?></div>
    </div>
    
    <div class="sidebar-nav">

        <a href="client_profile.php?user_id=<?php echo $user_id; ?>&action=edit_profile" class="nav-item">
            <span>✏️</span> Edit Profile
        </a>
        <a href="#" class="nav-item">
            <span>⚙️</span> Account Settings
        </a>
        <a href="client_profile.php?user_id=<?php echo $user_id; ?>&action=add_order" class="nav-item">
            <span>➕</span> Add New Order
        </a>
        <a href="my_orders.php?user_id=<?php echo $user_id; ?>" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'my_orders.php' ? 'active' : ''; ?>">
            <span><i class="fas fa-clipboard-list"></i></span> My Orders
        </a>

        <a href="client_profile.php?user_id=<?php echo $user_id; ?>&action=save_contractor" class="nav-item">
            <span>⭐</span> Add Contractor to Saved
        </a>
        <a href="client_profile.php?user_id=<?php echo $user_id; ?>&action=add_review" class="nav-item">
            <span>💬</span> Add Review for Contractor
        </a>
    </div>
</div>