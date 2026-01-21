<?php
use VantixDash\Config\SettingsService;

if (!isset($settingsService)) {
    $settingsService = new SettingsService($configService);
}

$currentVersion = $settingsService->getVersion();
?>
<header class="topbar">
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="ph ph-list"></i>
    </button>
    <div class="topbar-info">
        <span>VantixDash v<?php echo htmlspecialchars($currentVersion); ?></span>
    </div>
</header>
