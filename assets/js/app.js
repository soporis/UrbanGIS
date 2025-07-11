/**
 * UrbanGIS - JavaScript principal
 */

// Variables globales
let currentPosition = null;
let watchId = null;
let map = null;

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialiser la géolocalisation
    if (navigator.geolocation) {
        startGPSTracking();
    }
    
    // Initialiser les événements
    initializeEvents();
    
    // Initialiser la carte si présente
    if (document.getElementById('map')) {
        initializeMap();
    }
}

// Gestion de la géolocalisation
function startGPSTracking() {
    const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    };
    
    watchId = navigator.geolocation.watchPosition(
        updatePosition,
        handleGPSError,
        options
    );
}

let userMarker = null;

function updatePosition(position) {
    currentPosition = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy,
        timestamp: new Date()
    };

    updateGPSStatus(true);
    updatePositionDisplay();

    // Si la carte est initialisée, mettre à jour la position
    if (map) {
        const latlng = [currentPosition.latitude, currentPosition.longitude];
        map.setView(latlng, 18);

        // Supprimer l'ancien marqueur s'il existe
        if (userMarker) {
            userMarker.setLatLng(latlng);
        } else {
            userMarker = L.marker(latlng).addTo(map).bindPopup('Vous êtes ici');
        }
    }
}

function handleGPSError(error) {
    console.error('Erreur GPS:', error);
    updateGPSStatus(false);
}

function updateGPSStatus(active) {
    const statusElement = document.getElementById('gps-status');
    if (statusElement) {
        statusElement.className = active ? 'gps-status active' : 'gps-status inactive';
        statusElement.innerHTML = active 
            ? '<span class="gps-icon">📍</span> GPS actif - Précision: ' + (currentPosition ? currentPosition.accuracy.toFixed(1) + 'm' : 'N/A')
            : '<span class="gps-icon">❌</span> GPS inactif';
    }
}

function updatePositionDisplay() {
}

// Gestion des événements
function initializeEvents() {
    // Menu mobile
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }
    
    // Formulaire d'équipement
    const equipmentForm = document.getElementById('equipment-form');
    if (equipmentForm) {
        equipmentForm.addEventListener('submit', handleEquipmentSubmit);
    }
    
    // Sélection de type d'équipement
    const typeSelect = document.getElementById('equipment-type');
    if (typeSelect) {
        typeSelect.addEventListener('change', loadDynamicFields);
    }
    
    // Upload de photos
    const photoInput = document.getElementById('photo-input');
    if (photoInput) {
        photoInput.addEventListener('change', handlePhotoUpload);
    }
    
    // Boutons de suppression de photos
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('photo-remove')) {
            removePhoto(e.target.dataset.index);
        }
    });
}

// Gestion du formulaire d'équipement
function handleEquipmentSubmit(e) {
    e.preventDefault();
    
    if (!currentPosition) {
        alert('Position GPS requise pour enregistrer un équipement');
        return;
    }
    
    const formData = new FormData(e.target);
    formData.append('latitude', currentPosition.latitude);
    formData.append('longitude', currentPosition.longitude);
    formData.append('accuracy', currentPosition.accuracy);
    
    // Ajouter les attributs dynamiques
    const dynamicFields = document.querySelectorAll('.dynamic-field');
    const attributes = {};
    
    dynamicFields.forEach(field => {
        if (field.type === 'checkbox') {
            attributes[field.name] = field.checked;
        } else {
            attributes[field.name] = field.value;
        }
    });
    
    formData.append('attributes', JSON.stringify(attributes));
    
    // Envoyer les données
    fetch('api/equipment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Équipement enregistré avec succès');
            e.target.reset();
            loadEquipmentList();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'enregistrement');
    });
}

// Chargement des champs dynamiques
function loadDynamicFields() {
    const typeSelect = document.getElementById('equipment-type');
    const container = document.getElementById('dynamic-fields');
    
    if (!typeSelect || !container) return;
    
    const typeId = typeSelect.value;
    if (!typeId) {
        container.innerHTML = '';
        return;
    }
    
    fetch(`api/equipment-types.php?id=${typeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                generateDynamicFields(data.type.schema, container);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

function generateDynamicFields(schema, container) {
    container.innerHTML = '';
    
    if (!schema) return;
    
    const fields = JSON.parse(schema);
    
    fields.forEach(field => {
        const fieldHtml = createFieldHTML(field);
        container.insertAdjacentHTML('beforeend', fieldHtml);
    });
}

function createFieldHTML(field) {
    const required = field.required ? 'required' : '';
    const requiredMark = field.required ? '<span class="text-danger">*</span>' : '';
    
    let inputHtml = '';
    
    switch (field.type) {
        case 'text':
            inputHtml = `<input type="text" name="${field.name}" class="form-control dynamic-field" ${required}>`;
            break;
        case 'number':
            inputHtml = `<input type="number" name="${field.name}" class="form-control dynamic-field" ${required}>`;
            break;
        case 'date':
            inputHtml = `<input type="date" name="${field.name}" class="form-control dynamic-field" ${required}>`;
            break;
        case 'select':
            let options = '<option value="">Sélectionnez...</option>';
            if (field.options) {
                field.options.forEach(option => {
                    options += `<option value="${option}">${option}</option>`;
                });
            }
            inputHtml = `<select name="${field.name}" class="form-control form-select dynamic-field" ${required}>${options}</select>`;
            break;
        case 'checkbox':
            inputHtml = `<div class="form-check"><input type="checkbox" name="${field.name}" class="form-check-input dynamic-field" value="1"><label class="form-check-label">${field.label}</label></div>`;
            return `<div class="form-group">${inputHtml}</div>`;
        case 'textarea':
            inputHtml = `<textarea name="${field.name}" class="form-control dynamic-field" rows="3" ${required}></textarea>`;
            break;
    }
    
    return `
        <div class="form-group">
            <label class="form-label">${field.label} ${requiredMark}</label>
            ${inputHtml}
        </div>
    `;
}

// Gestion des photos
function handlePhotoUpload(e) {
    const files = e.target.files;
    const gallery = document.getElementById('photo-gallery');
    
    if (!gallery) return;
    
    Array.from(files).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                addPhotoToGallery(e.target.result, file.name, index);
            };
            reader.readAsDataURL(file);
        }
    });
}

function addPhotoToGallery(src, name, index) {
    const gallery = document.getElementById('photo-gallery');
    if (!gallery) return;
    
    const photoHtml = `
        <div class="photo-item">
            <img src="${src}" alt="${name}">
            <button type="button" class="photo-remove" data-index="${index}">×</button>
            <input type="hidden" name="photos[]" value="${src}">
        </div>
    `;
    
    gallery.insertAdjacentHTML('beforeend', photoHtml);
}

function removePhoto(index) {
    const photoItem = document.querySelector(`[data-index="${index}"]`).closest('.photo-item');
    if (photoItem) {
        photoItem.remove();
    }
}

// Chargement de la liste des équipements
function loadEquipmentList() {
    const container = document.getElementById('equipment-list');
    if (!container) return;
    
    fetch('api/equipment.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEquipmentList(data.equipment, container);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

function displayEquipmentList(equipment, container) {
    if (equipment.length === 0) {
        container.innerHTML = '<p class="text-center text-gray-500">Aucun équipement trouvé</p>';
        return;
    }
    
    let html = '<div class="grid grid-cols-1 gap-4">';
    
    equipment.forEach(item => {
        const statusClass = getStatusClass(item.status);
        const statusLabel = getStatusLabel(item.status);
        
        html += `
            <div class="card">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-semibold text-lg">${item.name}</h3>
                        <p class="text-gray-600">${item.type_name}</p>
                        <p class="text-sm text-gray-500">
                            📍 ${parseFloat(item.latitude).toFixed(6)}, ${parseFloat(item.longitude).toFixed(6)}
                        </p>
                        ${item.accuracy ? `<p class="text-sm text-gray-500">Précision: ${item.accuracy}m</p>` : ''}
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="badge ${statusClass}">${statusLabel}</span>
                        <button onclick="editEquipment('${item.id}')" class="btn btn-sm btn-secondary">Modifier</button>
                        <button onclick="deleteEquipment('${item.id}')" class="btn btn-sm btn-danger">Supprimer</button>
                    </div>
                </div>
                ${item.attributes ? displayAttributes(JSON.parse(item.attributes)) : ''}
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function displayAttributes(attributes) {
    let html = '<div class="mt-4 grid grid-cols-2 gap-2">';
    
    Object.entries(attributes).forEach(([key, value]) => {
        html += `<div class="text-sm"><strong>${key}:</strong> ${value}</div>`;
    });
    
    html += '</div>';
    return html;
}

function getStatusClass(status) {
    switch (status) {
        case 'active': return 'badge-success';
        case 'maintenance': return 'badge-warning';
        case 'inactive': return 'badge-danger';
        default: return 'badge-info';
    }
}

function getStatusLabel(status) {
    switch (status) {
        case 'active': return 'Actif';
        case 'maintenance': return 'Maintenance';
        case 'inactive': return 'Inactif';
        case 'decommissioned': return 'Décommissionné';
        default: return 'Inconnu';
    }
}

// Fonctions d'édition et suppression
function editEquipment(id) {
    window.location.href = `equipment-form.php?id=${id}`;
}

function deleteEquipment(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet équipement ?')) {
        fetch(`api/equipment.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Équipement supprimé');
                loadEquipmentList();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de la suppression');
        });
    }
}

// Initialisation de la carte (simulation)
function initializeMap() {
    const mapContainer = document.getElementById('map');
    if (!mapContainer) return;

    // Appliquer une hauteur si ce n’est pas fait en CSS
    mapContainer.style.height = '400px';

    // Initialiser la carte
    map = L.map('map').setView([48.8566, 2.3522], 13); // Par défaut : Paris

    // Ajouter les tuiles OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Si position GPS déjà dispo, centrer la carte dessus
    if (currentPosition) {
        const userLatLng = [currentPosition.latitude, currentPosition.longitude];
        map.setView(userLatLng, 18);

        // Ajouter un marqueur
        L.marker(userLatLng)
            .addTo(map)
            .bindPopup('Vous êtes ici')
            .openPopup();
    }
}

function centerOnUser() {
    if (currentPosition && map) {
        map.setView([currentPosition.latitude, currentPosition.longitude], 18);
        if (userMarker) {
            userMarker.openPopup();
        }
    } else {
        alert('Position GPS non disponible');
    }
}

// Utilitaires
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('fr-FR');
}

// Nettoyage lors de la fermeture
window.addEventListener('beforeunload', function() {
    if (watchId) {
        navigator.geolocation.clearWatch(watchId);
    }
});