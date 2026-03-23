<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Compliance') ?> - Easy Home Finance</title>
    <link rel="stylesheet" href="<?= $basePath ?? '' ?>/assets/css/app.css?v=<?= (int)($assetVersion ?? 1) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php
    $navRole = (string)($user['role_slug'] ?? '');
    $navAdmin = ($navRole === 'admin');
    $navMaker = ($navRole === 'maker');
    $navCanCreate = $navAdmin || $navMaker;
    ?>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?= $basePath ?? '' ?>/dashboard" class="logo">easy</a>
                <button type="button" class="sidebar-toggle" aria-label="Toggle sidebar"><i class="fas fa-chevron-left"></i></button>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">NAVIGATION</span>
                    <a href="<?= $basePath ?? '' ?>/dashboard" class="nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/compliance" class="nav-item <?= ($currentPage ?? '') === 'compliance-items' ? 'active' : '' ?>">
                        <i class="fas fa-list-alt"></i>
                        <span>Compliance Items</span>
                    </a>
                    <?php if ($navCanCreate): ?>
                    <a href="<?= $basePath ?? '' ?>/compliances/create" class="nav-item <?= ($currentPage ?? '') === 'compliances-create' ? 'active' : '' ?>">
                        <i class="fas fa-plus"></i>
                        <span>Create New</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= $basePath ?? '' ?>/financial-ratios" class="nav-item <?= ($currentPage ?? '') === 'financial-ratios' ? 'active' : '' ?>">
                        <i class="fas fa-calculator"></i>
                        <span>Financial Ratios</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/reports" class="nav-item <?= ($currentPage ?? '') === 'reports' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/circular-intelligence" class="nav-item <?= ($currentPage ?? '') === 'circular' ? 'active' : '' ?>">
                        <i class="fas fa-brain"></i>
                        <span>Circular Intelligence</span>
                    </a>
                    <?php if ($navAdmin): ?>
                    <a href="<?= $basePath ?? '' ?>/doa" class="nav-item <?= ($currentPage ?? '') === 'doa' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>Delegation of Authority (DOA)</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($navAdmin): ?>
                    <a href="<?= $basePath ?? '' ?>/authority-matrix" class="nav-item <?= ($currentPage ?? '') === 'authority-matrix' ? 'active' : '' ?>">
                        <i class="fas fa-th"></i>
                        <span>Authority Matrix</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/bulk-upload" class="nav-item <?= ($currentPage ?? '') === 'bulk-upload' ? 'active' : '' ?>">
                        <i class="fas fa-upload"></i>
                        <span>Bulk Upload</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($user['id'])): ?>
                <div class="nav-section">
                    <span class="nav-section-title">SYSTEM</span>
                    <a href="<?= $basePath ?? '' ?>/organization" class="nav-item <?= ($currentPage ?? '') === 'organization' ? 'active' : '' ?>">
                        <i class="fas fa-building"></i>
                        <span>Organization</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/roles-permissions" class="nav-item <?= ($currentPage ?? '') === 'roles' ? 'active' : '' ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span>Roles & Permissions</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/settings" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="<?= $basePath ?? '' ?>/billing" class="nav-item <?= ($currentPage ?? '') === 'billing' ? 'active' : '' ?>">
                        <i class="fas fa-credit-card"></i>
                        <span>Billing</span>
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-section">
                    <a href="<?= $basePath ?? '' ?>/logout" class="nav-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
                    <div>
                        <div class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></div>
                        <div class="user-role"><?= htmlspecialchars(ucfirst($user['role_slug'] ?? 'User')) ?></div>
                    </div>
                </div>
            </div>
        </aside>
        <div class="main-wrapper">
            <header class="top-header">
                <form action="<?= $basePath ?? '' ?>/compliance" method="get" class="header-search" role="search">
                    <i class="fas fa-search"></i>
                    <input type="search" name="search" placeholder="Search compliances..." class="search-input" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </form>
                <div class="header-actions">
                    <div class="header-dropdown-wrap">
                        <button type="button" class="icon-btn" id="btn-notifications" title="Notifications" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($notificationCount) && (int)$notificationCount > 0): ?>
                            <span class="badge"><?= (int)$notificationCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="panel-notifications" class="header-dropdown-panel" aria-hidden="true">
                            <div class="panel-title">Notifications</div>
                            <?php foreach ($notifications ?? [] as $n): ?>
                            <a href="<?= $basePath ?? '' ?>/compliance/view/<?= (int)$n['id'] ?>" class="panel-item">
                                <span class="panel-item-icon <?= $n['type'] === 'overdue' ? 'text-danger' : 'text-warning' ?>"><i class="fas fa-<?= $n['type'] === 'overdue' ? 'clock' : 'exclamation-triangle' ?>"></i></span>
                                <span><?= htmlspecialchars($n['compliance_code']) ?>: <?= htmlspecialchars(mb_substr($n['title'], 0, 35)) ?><?= mb_strlen($n['title']) > 35 ? '…' : '' ?></span>
                            </a>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                            <div class="panel-item text-muted">No new notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="header-dropdown-wrap">
                        <button type="button" class="user-menu-trigger" id="btn-user-menu" aria-haspopup="true" aria-expanded="false">
                            <div class="user-avatar small"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
                            <div class="user-details">
                                <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
                                <span class="user-role"><?= htmlspecialchars(ucfirst($user['role_slug'] ?? 'User')) ?></span>
                            </div>
                            <i class="fas fa-chevron-down dropdown-chevron"></i>
                        </button>
                        <div id="panel-user-menu" class="header-dropdown-panel header-dropdown-panel-user" aria-hidden="true">
                            <a href="<?= $basePath ?? '' ?>/organization" class="panel-item"><i class="fas fa-user"></i> View Profile</a>
                            <a href="<?= $basePath ?? '' ?>/settings<?= $navAdmin ? '' : '?tab=security' ?>" class="panel-item"><i class="fas fa-key"></i> Change Password</a>
                            <a href="<?= $basePath ?? '' ?>/logout" class="panel-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            <main class="content-area">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
    <script src="<?= $basePath ?? '' ?>/assets/js/app.js?v=<?= (int)($assetVersion ?? 1) ?>"></script>
</body>
</html>
