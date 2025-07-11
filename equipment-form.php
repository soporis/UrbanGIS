<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->requireLogin();
$user = $auth->getCurrentUser();

$equipment = null;
$equipment_id = isset($_GET['id']) ? $_GET['id'] : null;

// Si on modifie un équipement, charger ses données
if ($equipment_id) {
    try {
        $query = "SELECT e.*, et.schema_json, et.name as type_name 
                  FROM equipment e 
                  JOIN equipment_types et ON e.type_id = et.id 
                  WHERE e.id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $equipment_id);
        $stmt->execute();
        $equipment = $stmt->fetch();
        
        if (!$equipment) {
            header('Location: equipment-list.php?error=not_found');
            exit();
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: equipment-list.php?error=db_error');
        exit();
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $name = $_POST['name'] ?? '';
    $project_id = $_POST['project_id'] ?? null;
    $type_id = $_POST['type_id'] ?? null;
    $status = $_POST['status'] ?? 'active';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $accuracy = $_POST['accuracy_raw'] ?? null;

    // Gestion des champs dynamiques
    $equipment_type_schema = null;
    if ($type_id) {
        $query = "SELECT schema_json FROM equipment_types WHERE id = :type_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':type_id', $type_id);
        $stmt->execute();
        $type_data = $stmt->fetch();
        if ($type_data && $type_data['schema_json']) {
            $equipment_type_schema = json_decode($type_data['schema_json'], true);
        }
    }

    $attributes = [];
    if ($equipment_type_schema) {
        foreach ($equipment_type_schema as $field) {
            if (isset($_POST[$field['name']])) {
                if ($field['type'] === 'checkbox') {
                    $attributes[$field['name']] = ($_POST[$field['name']] === '1');
                } else {
                    $attributes[$field['name']] = $_POST[$field['name']];
                }
            } else if ($field['type'] === 'checkbox') {
                $attributes[$field['name']] = false;
            }
        }
    }
    $attributes_json = json_encode($attributes);

    try {
        if ($id) {
            // UPDATE équipement existant
            $query = "UPDATE equipment SET 
                        name = :name, 
                        project_id = :project_id, 
                        type_id = :type_id, 
                        status = :status, 
                        latitude = :latitude, 
                        longitude = :longitude, 
                        accuracy = :accuracy,
                        attributes_json = :attributes_json
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
        } else {
            // INSERT nouvel équipement
            $query = "INSERT INTO equipment (name, project_id, type_id, status, latitude, longitude, accuracy, attributes_json, created_by) 
                      VALUES (:name, :project_id, :type_id, :status, :latitude, :longitude, :accuracy, :attributes_json, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':created_by', $user['id']);
        }

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':project_id', $project_id);
        $stmt->bindParam(':type_id', $type_id);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':accuracy', $accuracy);
        $stmt->bindParam(':attributes_json', $attributes_json);
        
        $stmt->execute();

        $equipment_id = $id ?? $db->lastInsertId();

        // Gestion des photos
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir = 'uploads/equipment/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            foreach ($_FILES['photos']['name'] as $key => $photo_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['photos']['tmp_name'][$key];
                    $file_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
                    $new_file_name = uniqid('photo_') . '.' . $file_ext;
                    $target_file = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $photo_query = "INSERT INTO equipment_photos (equipment_id, filename, original_name, file_size) 
                                       VALUES (:equipment_id, :filename, :original_name, :file_size)";
                        $photo_stmt = $db->prepare($photo_query);
                        $photo_stmt->bindParam(':equipment_id', $equipment_id);
                        $photo_stmt->bindParam(':filename', $new_file_name);
                        $photo_stmt->bindParam(':original_name', $photo_name);
                        $photo_stmt->bindParam(':file_size', $_FILES['photos']['size'][$key]);
                        $photo_stmt->execute();
                    }
                }
            }
        }

        header('Location: equipment-list.php?success=true');
        exit();

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "Erreur lors de l'enregistrement";
    }
}

// Charger les types d'équipements
$query = "SELECT * FROM equipment_types ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$equipment_types = $stmt->fetchAll();

// Charger les projets
$query = "SELECT * FROM projects ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $equipment ? 'Modifier' : 'Ajouter'; ?> un équipement - UrbanGIS</title>
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
                <h1 class="text-2xl font-bold text-gray-800 mb-4">
                    <?php echo $equipment ? 'Modifier' : 'Ajouter'; ?> un équipement
                </h1>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <!-- État GPS -->
                <div id="gps-status" class="gps-status inactive mb-4">
                    <span class="gps-icon">❌</span> GPS inactif
                </div>
                
                <form id="equipment-form" method="POST" enctype="multipart/form-data">
                    <?php if ($equipment): ?>
                        <input type="hidden" name="id" value="<?php echo $equipment['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Nom de l'équipement <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($equipment ? $equipment['name'] : ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Projet <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-control form-select" required>
                                <option value="">Sélectionnez un projet</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" 
                                            <?php echo ($equipment && $equipment['project_id'] == $project['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Type d'équipement <span class="text-danger">*</span></label>
                            <select id="equipment-type" name="type_id" class="form-control form-select" required>
                                <option value="">Sélectionnez un type</option>
                                <?php foreach ($equipment_types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo ($equipment && $equipment['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Statut</label>
                            <select name="status" class="form-control form-select">
                                <option value="active" <?php echo (!$equipment || $equipment['status'] === 'active') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo ($equipment && $equipment['status'] === 'inactive') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="maintenance" <?php echo ($equipment && $equipment['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="decommissioned" <?php echo ($equipment && $equipment['status'] === 'decommissioned') ? 'selected' : ''; ?>>Décommissionné</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Position GPS -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="form-group">
                            <label class="form-label">Latitude</label>
                            <input type="text" id="current-lat" name="latitude" class="form-control" 
                                   value="<?php echo $equipment ? $equipment['latitude'] : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Longitude</label>
                            <input type="text" id="current-lon" name="longitude" class="form-control" 
                                   value="<?php echo $equipment ? $equipment['longitude'] : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Précision</label>
                            <input type="text" id="current-accuracy" name="accuracy" class="form-control" 
                                   value="<?php echo ($equipment && $equipment['accuracy']) ? $equipment['accuracy'] . ' m' : ''; ?>" readonly>
                            <input type="hidden" id="accuracy-raw" name="accuracy_raw" 
                                   value="<?php echo $equipment ? $equipment['accuracy'] : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Champs dynamiques -->
                    <div id="dynamic-fields"></div>
                    
                    <!-- Photos -->
                    <div class="form-group">
                        <label class="form-label">Photos</label>
                        <div class="flex items-center space-x-4">
                            <input type="file" id="photo-input" name="photos[]" class="hidden" 
                                   accept="image/*" multiple>
                            <button type="button" onclick="document.getElementById('photo-input').click();" class="btn btn-secondary">
                                Choisir des fichiers
                            </button>
                            <button type="button" id="take-photo-btn" class="btn btn-primary">
                                Prendre une photo
                            </button>
                        </div>
                        <div id="photo-gallery" class="photo-gallery mt-4"></div>
                    </div>

                    <!-- Modale caméra -->
                    <div id="camera-modal" class="modal-overlay hidden">
                        <div class="modal-content">
                            <video id="camera-stream" autoplay playsinline></video>
                            <canvas id="camera-canvas" class="hidden"></canvas>
                            <div class="modal-actions">
                                <button type="button" id="capture-btn" class="btn btn-success">Capturer</button>
                                <button type="button" id="cancel-camera-btn" class="btn btn-danger">Annuler</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <a href="equipment-list.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $equipment ? 'Modifier' : 'Enregistrer'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Fonction pour générer les champs dynamiques avec valeurs
        function generateDynamicFieldsWithValues(schema, attributes) {
            const container = document.getElementById('dynamic-fields');
            if (!container) return;
            
            container.innerHTML = '';
            
            schema.forEach(field => {
                const value = attributes[field.name] || '';
                const fieldHtml = createFieldHTMLWithValue(field, value);
                container.insertAdjacentHTML('beforeend', fieldHtml);
            });
        }
        
        function createFieldHTMLWithValue(field, value) {
            const required = field.required ? 'required' : '';
            const requiredMark = field.required ? '<span class="text-danger">*</span>' : '';
            
            let inputHtml = '';
            
            switch (field.type) {
                case 'text':
                    inputHtml = `<input type="text" name="${field.name}" class="form-control dynamic-field" value="${value}" ${required}>`;
                    break;
                case 'number':
                    inputHtml = `<input type="number" name="${field.name}" class="form-control dynamic-field" value="${value}" ${required}>`;
                    break;
                case 'date':
                    inputHtml = `<input type="date" name="${field.name}" class="form-control dynamic-field" value="${value}" ${required}>`;
                    break;
                case 'select':
                    let options = '<option value="">Sélectionnez...</option>';
                    if (field.options) {
                        field.options.forEach(option => {
                            const selected = option === value ? 'selected' : '';
                            options += `<option value="${option}" ${selected}>${option}</option>`;
                        });
                    }
                    inputHtml = `<select name="${field.name}" class="form-control form-select dynamic-field" ${required}>${options}</select>`;
                    break;
                case 'checkbox':
                    const checked = value ? 'checked' : '';
                    inputHtml = `<div class="form-check"><input type="checkbox" name="${field.name}" class="form-check-input dynamic-field" value="1" ${checked}><label class="form-check-label">${field.label}</label></div>`;
                    return `<div class="form-group">${inputHtml}</div>`;
                case 'textarea':
                    inputHtml = `<textarea name="${field.name}" class="form-control dynamic-field" rows="3" ${required}>${value}</textarea>`;
                    break;
            }
            
            return `
                <div class="form-group">
                    <label class="form-label">${field.label} ${requiredMark}</label>
                    ${inputHtml}
                </div>
            `;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Charger les champs dynamiques si on modifie un équipement
            <?php if ($equipment && $equipment['schema_json']): ?>
                const schema = <?php echo $equipment['schema_json']; ?>;
                const attributes = <?php echo $equipment['attributes_json'] ?: '{}'; ?>;
                generateDynamicFieldsWithValues(schema, attributes);
            <?php endif; ?>

            // Gestion de la caméra
            const takePhotoButton = document.getElementById('take-photo-btn');
            const photoInput = document.getElementById('photo-input');
            const photoGallery = document.getElementById('photo-gallery');
            const cameraModal = document.getElementById('camera-modal');
            const video = document.getElementById('camera-stream');
            const canvas = document.getElementById('camera-canvas');
            const captureButton = document.getElementById('capture-btn');
            const cancelCameraButton = document.getElementById('cancel-camera-btn');
            
            let stream;

            // Ouvrir la caméra
            takePhotoButton.addEventListener('click', async () => {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    alert("L'API de la caméra n'est pas supportée sur ce navigateur.");
                    return;
                }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ 
                        video: { facingMode: 'environment' }
                    });
                    video.srcObject = stream;
                    cameraModal.classList.remove('hidden');
                } catch (err) {
                    console.error("Erreur d'accès à la caméra:", err);
                    alert("Impossible d'accéder à la caméra.");
                }
            });
            
            // Fermer la caméra
            function stopCamera() {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                cameraModal.classList.add('hidden');
            }

            cancelCameraButton.addEventListener('click', stopCamera);
            
            // Capturer la photo
            captureButton.addEventListener('click', () => {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);
                
                canvas.toBlob(blob => {
                    const fileName = `capture_${Date.now()}.jpg`;
                    const file = new File([blob], fileName, { type: 'image/jpeg' });
                    
                    const dataTransfer = new DataTransfer();
                    
                    if (photoInput.files.length > 0) {
                        Array.from(photoInput.files).forEach(existingFile => dataTransfer.items.add(existingFile));
                    }
                    
                    dataTransfer.items.add(file);
                    photoInput.files = dataTransfer.files;
                    
                    updatePhotoGallery();
                    stopCamera();
                }, 'image/jpeg');
            });

            // Mettre à jour la galerie de photos
            photoInput.addEventListener('change', updatePhotoGallery);

            function updatePhotoGallery() {
                photoGallery.innerHTML = '';
                
                for (const file of photoInput.files) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.maxWidth = '150px';
                        img.style.maxHeight = '150px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '4px';
                        img.style.margin = '5px';
                        photoGallery.appendChild(img);
                    }
                    
                    reader.readAsDataURL(file);
                }
            }

            // Géolocalisation GPS (seulement pour nouveaux équipements)
            <?php if (!$equipment): ?>
            const latInput = document.getElementById('current-lat');
            const lonInput = document.getElementById('current-lon');
            const accuracyInput = document.getElementById('current-accuracy');
            const accuracyRawInput = document.getElementById('accuracy-raw');
            const gpsStatus = document.getElementById('gps-status');

            if ('geolocation' in navigator) {
                gpsStatus.className = 'gps-status loading mb-4';
                gpsStatus.innerHTML = '<span class="gps-icon">⏳</span> Recherche du signal GPS...';

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        const acc = position.coords.accuracy;

                        latInput.value = lat.toFixed(7);
                        lonInput.value = lon.toFixed(7);
                        accuracyInput.value = acc.toFixed(2) + ' m';
                        accuracyRawInput.value = acc;

                        gpsStatus.className = 'gps-status active mb-4';
                        gpsStatus.innerHTML = '<span class="gps-icon">✅</span> GPS Actif et position acquise';
                    },
                    (error) => {
                        let errorMessage;
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage = "Permission de géolocalisation refusée.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage = "Position non disponible.";
                                break;
                            case error.TIMEOUT:
                                errorMessage = "La demande de position a expiré.";
                                break;
                            default:
                                errorMessage = "Une erreur inconnue est survenue.";
                                break;
                        }
                        gpsStatus.className = 'gps-status inactive mb-4';
                        gpsStatus.innerHTML = `<span class="gps-icon">❌</span> Erreur GPS: ${errorMessage}`;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                gpsStatus.innerHTML = '<span class="gps-icon">❌</span> La géolocalisation n\'est pas supportée.';
            }
            <?php else: ?>
            // Pour les équipements existants, afficher le statut GPS comme inactif
            const gpsStatus = document.getElementById('gps-status');
            gpsStatus.className = 'gps-status inactive mb-4';
            gpsStatus.innerHTML = '<span class="gps-icon">📍</span> Position enregistrée';
            <?php endif; ?>
        });
    </script>
</body>
</html>