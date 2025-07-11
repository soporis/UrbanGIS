<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$user = $auth->getCurrentUser();

// Filtres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Construction de la requête
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "e.name LIKE :search";
    $params[':search'] = "%$search%";
}

if ($type_filter) {
    $where_conditions[] = "e.type_id = :type_id";
    $params[':type_id'] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "e.status = :status";
    $params[':status'] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "SELECT e.*, et.name as type_name, et.color, p.name as project_name,
                 u.name as created_by_name
          FROM equipment e
          JOIN equipment_types et ON e.type_id = et.id
          JOIN projects p ON e.project_id = p.id
          JOIN users u ON e.created_by = u.id
          $where_clause
          ORDER BY e.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$equipment = $stmt->fetchAll();

// Charger les types pour le filtre
$query = "SELECT * FROM equipment_types ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$equipment_types = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaire des équipements - UrbanGIS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        
        <div class="container">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h1 class="text-2xl font-bold text-gray-800">Inventaire des équipements</h1>
                    <a href="equipment-form.php" class="btn btn-primary">
                        ➕ Ajouter un équipement
                    </a>
                </div>
                
                <!-- Filtres -->
                <form method="GET" class="grid grid-cols-1 grid-cols-4 gap-4 mb-6">
                    <div class="form-group mb-0">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group mb-0">
                        <select name="type" class="form-control form-select">
                            <option value="">Tous les types</option>
                            <?php foreach ($equipment_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" 
                                        <?php echo $type_filter == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-0">
                        <select name="status" class="form-control form-select">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="decommissioned" <?php echo $status_filter === 'decommissioned' ? 'selected' : ''; ?>>Décommissionné</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary w-full">🔍 Filtrer</button>
                    </div>
                </form>
            </div>
            
            <!-- Liste des équipements -->
            <div id="equipment-list">
                <?php if (empty($equipment)): ?>
                    <div class="card text-center">
                        <div class="text-6xl mb-4">📦</div>
                        <h3 class="text-lg font-semibold mb-2">Aucun équipement trouvé</h3>
                        <p class="text-gray-600 mb-4">Commencez par ajouter votre premier équipement</p>
                        <a href="equipment-form.php" class="btn btn-primary">Ajouter un équipement</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-4">
                        <?php foreach ($equipment as $item): ?>
                            <div class="card">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-xl"
                                             style="background-color: <?php echo $item['color']; ?>">
                                            📦
                                        </div>
                                        
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($item['name']); ?></h3>
                                                <span class="badge <?php 
                                                    switch($item['status']) {
                                                        case 'active': echo 'badge-success'; break;
                                                        case 'maintenance': echo 'badge-warning'; break;
                                                        case 'inactive': echo 'badge-danger'; break;
                                                        default: echo 'badge-info';
                                                    }
                                                ?>">
                                                    <?php 
                                                        switch($item['status']) {
                                                            case 'active': echo 'Actif'; break;
                                                            case 'maintenance': echo 'Maintenance'; break;
                                                            case 'inactive': echo 'Inactif'; break;
                                                            case 'decommissioned': echo 'Décommissionné'; break;
                                                            default: echo 'Inconnu';
                                                        }
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($item['type_name']); ?></p>
                                            <p class="text-gray-600 mb-2">Projet: <?php echo htmlspecialchars($item['project_name']); ?></p>
                                            
                                            <div class="text-sm text-gray-500 space-y-1">
                                                <p>📍 <?php echo number_format($item['latitude'], 6); ?>, <?php echo number_format($item['longitude'], 6); ?></p>
                                                <?php if ($item['accuracy']): ?>
                                                    <p>🎯 Précision: <?php echo $item['accuracy']; ?>m</p>
                                                <?php endif; ?>
                                                <p>👤 Créé par: <?php echo htmlspecialchars($item['created_by_name']); ?></p>
                                                <p>📅 <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></p>
                                            </div>
                                            
                                            <?php if ($item['attributes_json']): ?>
                                                <div class="mt-3 grid grid-cols-2 gap-2">
                                                    <?php 
                                                    $attributes = json_decode($item['attributes_json'], true);
                                                    if ($attributes) {
                                                        foreach ($attributes as $key => $value) {
                                                            if ($value) {
                                                                echo "<div class='text-sm'><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "</div>";
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <a href="equipment-form.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-secondary" title="Modifier">
                                            ✏️
                                        </a>
                                        <a href="intervention-form.php?equipment_id=<?php echo $item['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Nouvelle intervention">
                                            🔧
                                        </a>
                                        <button onclick="deleteEquipment('<?php echo $item['id']; ?>')" 
                                                class="btn btn-sm btn-danger" title="Supprimer">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>