<nav class="navbar">
    <div class="container flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <button id="menu-toggle" class="btn btn-secondary block md:hidden">☰</button>
            <h1 class="text-xl font-semibold text-gray-800">
                <?php
                $page_titles = [
                    'index.php' => 'Tableau de bord',
                    'equipment-list.php' => 'Inventaire des équipements',
                    'equipment-form.php' => 'Formulaire équipement',
                    'interventions.php' => 'GMAO - Interventions',
                    'intervention-form.php' => 'Nouvelle intervention',
                    'settings.php' => 'Paramètres'
                ];
                
                $current_page = basename($_SERVER['PHP_SELF']);
				echo isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'UrbanGIS';
                ?>
            </h1>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2 text-sm text-gray-600">
                <span id="connection-status">🟢 En ligne</span>
            </div>
            
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($user['name']); ?></span>
                <a href="logout.php" class="btn btn-secondary btn-sm" title="Déconnexion">
                    🚪 Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>