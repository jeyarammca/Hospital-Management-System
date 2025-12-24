<?php
// Determine active page for highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar d-flex flex-column">
    <div class="sidebar-header">
        Hospital PMS
    </div>
    <ul class="nav flex-column mt-3">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="op_entry.php" class="nav-link <?php echo $current_page == 'op_entry.php' ? 'active' : ''; ?>">
                <i class="bi bi-pencil-square"></i> New OP Entry
            </a>
        </li>
        <li class="nav-item">
            <a href="patients.php" class="nav-link <?php echo $current_page == 'patients.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Patience Records
            </a>
        </li>
        <li class="nav-item">
            <a href="doctors.php" class="nav-link <?php echo $current_page == 'doctors.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge"></i> Doctors
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
            <a href="admin_settings.php" class="nav-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> Admin Settings
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item mt-auto">
            <a href="logout.php" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </li>
    </ul>
</div>
