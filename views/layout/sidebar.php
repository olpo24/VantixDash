<nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <span class="brand-text">VantixDash</span>
    </div>
    
    <ul class="sidebar-nav">
        <li class="nav-item">
            <a href="index.php?view=dashboard" class="nav-link <?php echo $safeView === 'dashboard' ? 'active' : ''; ?>">
                <i class="ph ph-layout"></i> <span>Dashboard</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="index.php?view=manage_sites" class="nav-link <?php echo $safeView === 'manage_sites' ? 'active' : ''; ?>">
                <i class="ph ph-browsers"></i> <span>Seiten</span>
            </a>
        </li>

        <li class="nav-item has-submenu">
            <a href="javascript:void(0)" class="nav-link submenu-toggle <?php echo str_starts_with($safeView, 'settings_') ? 'active' : ''; ?>">
                <i class="ph ph-gear"></i> <span>Einstellungen</span>
                <i class="ph ph-caret-down caret-icon"></i>
            </a>
            <ul class="submenu <?php echo str_starts_with($safeView, 'settings_') ? 'show' : ''; ?>">
                <li><a href="index.php?view=settings_general" class="<?php echo $safeView === 'settings_general' ? 'active' : ''; ?>">Allgemein</a></li>
                <li><a href="index.php?view=settings_smtp" class="<?php echo $safeView === 'settings_smtp' ? 'active' : ''; ?>">SMTP-Server</a></li>
                <li><a href="index.php?view=settings_profile" class="<?php echo $safeView === 'settings_profile' ? 'active' : ''; ?>">Sicherheit</a></li>
            </ul>
        </li>

        <li class="nav-item">
            <a href="index.php?view=logs" class="nav-link <?php echo $safeView === 'logs' ? 'active' : ''; ?>">
                <i class="ph ph-terminal-window"></i> <span>Logs</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="index.php?view=profile" class="nav-link <?php echo $safeView === 'profile' ? 'active' : ''; ?>">
                <i class="ph ph-user-circle"></i> <span>Profil</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="ph ph-sign-out"></i> Abmelden</a>
    </div>
</nav>
