<?php

require_once 'config.php';

class Auth {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function register($username, $name, $email, $password, $user_type = 'user') {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, name, email, password, user_type)
            VALUES (:username, :name, :email, :password, :user_type)
        ");

        $stmt->execute([
            ':username'  => $username,
            ':name'      => $name,
            ':email'     => $email,
            ':password'  => $hashedPassword,
            ':user_type' => $user_type
        ]);

        return true;
    } catch (PDOException $e) {
        return false;
    }
}


    public function login($username, $password) {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

       
        $this->createWishlistIfNotExists($user['username']);

        return true;
    }
    return false;
}


    public function logout() {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    public function loginWithUsername($username, $password, $user_type = null) {
    
    if ($user_type !== null) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM users
            WHERE username = :username AND user_type = :user_type
        ");
        $stmt->execute([
            ':username'  => $username,
            ':user_type' => $user_type
        ]);
    } else {
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM users
            WHERE username = :username
        ");
        $stmt->execute([':username' => $username]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username']  = $user['username'];
        $_SESSION['name']      = $user['name'];
        $_SESSION['user_type'] = $user['user_type']; 

        return true;
    }

    return false;
}

public function createWishlistIfNotExists($username) {
    $stmt = $this->pdo->prepare("SELECT * FROM wishlists WHERE username = ?");
    $stmt->execute([$username]);
    $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wishlist) {
        $stmt = $this->pdo->prepare("INSERT INTO wishlists (username, visibility) VALUES (?, 0)");
        $stmt->execute([$username]);
    }
}


}

$auth = new Auth($pdo);
?>