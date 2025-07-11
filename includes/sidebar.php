<div id="sidebar" class="sidebar">
    <div class="text-center mb-6">
        <div class="text-3xl mb-2">🗺️</div>
        <h2 class="text-xl font-bold text-primary">UrbanGIS</h2>
    </div>
    
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="index.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🏠</span>
                Tableau de bord
            </a>
        </li>
        <li class="sidebar-item">
            <a href="equipment-list.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'equipment-list.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📦</span>
                Inventaire
            </a>
        </li>
        <li class="sidebar-item">
            <a href="equipment-form.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'equipment-form.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">➕</span>
                Ajouter équipement
            </a>
        </li>
        <li class="sidebar-item">
            <a href="interventions.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'interventions.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🔧</span>
                GMAO
            </a>
        </li>
        <li class="sidebar-item">
            <a href="intervention-form.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'intervention-form.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📋</span>
                Nouvelle intervention
            </a>
        </li>
        <?php if ($auth->canAccess('manager')): ?>
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">⚙️</span>
                Paramètres
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="mt-auto p-4 border-t">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center text-white font-bold">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div>
                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-sm text-gray-600"><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>
</div>