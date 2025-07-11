<?php
/**
 * Système d'authentification
 */

session_start();

class Auth {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function login($email, $password) {
        try {
            $query = "SELECT id, email, password_hash, name, role FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                return $user;
            }
        } catch (PDOException $e) {
            // Pour du debug uniquement
            echo "Erreur : " . $e->getMessage();
        }

        return false;
    }
    
    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }
    
    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
    
    public function canAccess($requiredRole) {
        $roles = ['operator' => 1, 'manager' => 2, 'administrator' => 3];
		$userLevel = isset($roles[$_SESSION['user_role']]) ? $roles[$_SESSION['user_role']] : 0;
		$requiredLevel = isset($roles[$requiredRole]) ? $roles[$requiredRole] : 0;
        
        return $userLevel >= $requiredLevel;
    }
}
?>