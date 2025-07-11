<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$user = $auth->getCurrentUser();

// Statistiques du tableau de bord
$stats = [
    'total_equipment' => 0,
    'active_equipment' => 0,
    'maintenance_equipment' => 0,
    'total_interventions' => 0
];

try {
    // Compter les équipements
    $query = "SELECT status, COUNT(*) as count FROM equipment GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $stats['total_equipment'] += $row['count'];
        if ($row['status'] === 'active') {
            $stats['active_equipment'] = $row['count'];
        } elseif ($row['status'] === 'maintenance') {
            $stats['maintenance_equipment'] = $row['count'];
        }
    }
    
    // Compter les interventions
    $query = "SELECT COUNT(*) as count FROM interventions";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_interventions'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    // En cas d'erreur, garder les valeurs par défaut
}
$map_equipment = [];
try {
    // On sélectionne les champs nécessaires pour chaque équipement ayant des coordonnées
    $query_map = "SELECT id, name, latitude, longitude, status, project_id FROM equipment WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    $stmt_map = $db->prepare($query_map);
    $stmt_map->execute();
    $map_equipment = $stmt_map->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // En cas d'erreur, $map_equipment restera un tableau vide, n'empêchant pas la page de s'afficher.
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - UrbanGIS</title>
    <link rel="stylesheet" href="assets/css/style.css">
	<!-- Leaflet CSS -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
	<!-- Leaflet JS -->
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container">
            <div class="card">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Tableau de bord</h1>
                <p class="text-gray-600">Vue d'ensemble de vos équipements urbains</p>
            </div>
            
            <!-- Statistiques -->
            <div class="grid grid-cols-1 grid-cols-4 gap-6 mb-6">
                <div class="card">
                    <div class="flex items-center">
                        <div class="text-3xl mr-4">📦</div>
                        <div>
                            <p class="text-sm text-gray-600">Équipements</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_equipment']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="text-3xl mr-4">✅</div>
                        <div>
                            <p class="text-sm text-gray-600">Actifs</p>
                            <p class="text-2xl font-bold text-success"><?php echo $stats['active_equipment']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="text-3xl mr-4">🔧</div>
                        <div>
                            <p class="text-sm text-gray-600">Maintenance</p>
                            <p class="text-2xl font-bold text-warning"><?php echo $stats['maintenance_equipment']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="flex items-center">
                        <div class="text-3xl mr-4">📋</div>
                        <div>
                            <p class="text-sm text-gray-600">Interventions</p>
                            <p class="text-2xl font-bold text-primary"><?php echo $stats['total_interventions']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="grid grid-cols-1 grid-cols-3 gap-6">
                <div class="card">
                    <h3 class="font-semibold text-lg mb-4">Actions rapides</h3>
                    <div class="space-y-2">
                        <a href="equipment-form.php" class="btn btn-primary w-full">
                            📍 Nouvel équipement
                        </a>
                        <a href="intervention-form.php" class="btn btn-secondary w-full">
                            🔧 Nouvelle intervention
                        </a>
                        <a href="equipment-list.php" class="btn btn-secondary w-full">
                            📋 Voir tous les équipements
                        </a>
                    </div>
                </div>
                
                <div class="card">
                    <h3 class="font-semibold text-lg mb-4">État GPS</h3>
                    <div id="gps-status" class="gps-status inactive">
                        <span class="gps-icon">❌</span> GPS inactif
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p>Latitude: <span id="current-lat">-</span></p>
                        <p>Longitude: <span id="current-lon">-</span></p>
                        <p>Précision: <span id="current-accuracy">-</span></p>
                    </div>
                </div>
                
                <div class="card">
                    <h3 class="font-semibold text-lg mb-4">Carte</h3>
                    <div id="map" class="map-container" style="height: 200px;">
                        Chargement de la carte...
                    </div>
                </div>
            </div>
        </div>
    </div>
	<script>
        const equipmentData = <?php echo json_encode($map_equipment); ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>