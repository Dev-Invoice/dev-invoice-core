<?php
// Get the current page to highlight the active menu item
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>

<aside class="sidebar-content d-flex flex-column p-0 min-vh-100">
    <div class="sidebar-head">
        <h1 class="site-name"><?php echo SITE_NAME; ?></h1>
        <p class="text-sm text-muted-foreground">Manage Your Business</p>
    </div>
    
    <ul class="nav nav-pills flex-column mb-sm-auto mb-0 align-items-center align-items-sm-start w-100 flex-grow-1" id="menu">
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard" class="nav-link px-0 align-middle d-flex flex-row <?php echo ($currentPage == 'index.php' && $currentDir == 'dashboard') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-gauge"></i> <span class="ms-1 d-none d-sm-inline">Dashboard</span>
            </a>
        </li>
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard/clients" class="nav-link px-0 align-middle <?php echo ($currentDir == 'clients') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-users"></i> <span class="ms-1 d-none d-sm-inline">Clients</span>
            </a>
        </li>
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="nav-link px-0 align-middle <?php echo ($currentDir == 'tasks') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-list-check"></i> <span class="ms-1 d-none d-sm-inline">Tasks</span>
            </a>
        </li>
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="nav-link px-0 align-middle <?php echo ($currentDir == 'invoices') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-receipt"></i> <span class="ms-1 d-none d-sm-inline">Invoices</span>
            </a>
        </li>
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard/profile.php" class="nav-link px-0 align-middle <?php echo ($currentPage == 'profile.php') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-user"></i> <span class="ms-1 d-none d-sm-inline">Profile</span>
            </a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="nav-item w-100">
            <a href="<?php echo SITE_URL; ?>/dashboard/users.php" class="nav-link px-0 align-middle <?php echo ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="fs-4 fa-solid fa-user-group"></i> <span class="ms-1 d-none d-sm-inline">Users</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <div class="dropdown pb-4">
        <div class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fs-4 fa-solid fa-gear"></i> <span class="ms-1 d-none d-sm-inline">Settings</span>
        </div>
        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard/profile.php">Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/auth/logout.php">Sign out</a></li>
        </ul>
    </div>
</aside>
