<?php
// Activer l'affichage des erreurs (utile pour débogage, à désactiver en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Connexion à la base de données + définition de $db (PDO)
require_once 'config/database.php';

// Inclusion de la classe Auth qui utilise $db
require_once 'includes/auth.php';

// Initialisation du message d’erreur (si besoin)
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Récupération des données du formulaire
	$email = isset($_POST["email"]) ? $_POST["email"] : '';
	$password = isset($_POST["password"]) ? $_POST["password"] : '';


    // Création de l'objet Auth avec la connexion PDO
	$db = new Database();
	$conn = $db->getConnection();
    $auth = new Auth($conn);
    $user = $auth->login($email, $password);

    if ($user) {
        // Connexion réussie → démarrer la session
        #session_start();
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["user_name"] = $user["name"];
		$_SESSION['user_email'] = $user[""];
        header("Location: index.php");
        exit();
    } else {
        // Échec → message d'erreur
        $error = "Email ou mot de passe incorrect.";
    }
	// Si déjà connecté, rediriger
	if ($auth->isLoggedIn()) {
		header("Location: index.php");
		exit();
	}
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - UrbanGIS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🗺️</div>
                <h1 class="text-2xl font-bold text-gray-800">UrbanGIS</h1>
                <p class="text-gray-600">Gestion d'équipements urbains</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mot de passe</label>
                    <input type="password" name="password" class="form-control" value="" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-full">Se connecter</button>
            </form>
        </div>
    </div>
</body>
</html>