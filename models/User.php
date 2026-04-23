<?php
class User {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($username, $firstName, $lastName, $email, $password, $subscriptionId = 1, $roleId = 1) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $sql = 'INSERT INTO users (username, first_name, last_name, email, password_hash, subscription_id, role_id) VALUES (:u, :f, :l, :e, :p, :sub, :r)';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':u' => $username,
            ':f' => $firstName,
            ':l' => $lastName,
            ':e' => $email,
            ':p' => $hash,
            ':sub' => $subscriptionId,
            ':r' => $roleId
        ]);
    }

    public function login($email, $password) {
        $sql = 'SELECT * FROM users WHERE email = :e LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($user && password_verify($password, $user['password_hash'])) ? $user : false;
    }

    public function getById($id) {
        $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByEmail($email) {
        $sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUsernameOrEmail($username, $email, $excludeId = null) {
        $sql = 'SELECT * FROM users WHERE (username = :username OR email = :email)';
        $params = [':username' => $username, ':email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($userId, $username, $firstName, $lastName, $email, $profilePic = null) {
        $sql = 'UPDATE users SET username = :username, first_name = :first_name, last_name = :last_name, email = :email';
        $params = [':username' => $username, ':first_name' => $firstName, ':last_name' => $lastName, ':email' => $email, ':id' => $userId];

        if ($profilePic !== null && $profilePic !== '') {
            $sql .= ', profile_pic = :profile_pic';
            $params[':profile_pic'] = $profilePic;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function updatePassword($userId, $hashedPassword) {
        $sql = 'UPDATE users SET password_hash = :password WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);
    }

    public function updateSubscription($userId, $subscriptionId) {
        $sql = 'UPDATE users SET subscription_id = :subscription_id WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':subscription_id' => $subscriptionId, ':id' => $userId]);
    }

    public function banUser($userId) {
        $roleId = $this->getRoleIdByName('banned');
        return $this->updateRole($userId, $roleId);
    }

    public function unbanUser($userId) {
        $roleId = $this->getRoleIdByName('member');
        return $this->updateRole($userId, $roleId);
    }

    public function isBanned($userId) {
        return $this->getUserRole($userId) === 'banned';
    }

    public function getMonthlyUsage($userId) {
        $sql = 'SELECT COUNT(*) AS count FROM prompts WHERE user_id = :user_id AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    public function getAllUsers() {
        $sql = 'SELECT * FROM users ORDER BY created_at DESC';
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete($userId) {
        $sql = 'DELETE FROM users WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $userId]);
    }

    public function getUserRole($userId) {
        $sql = 'SELECT r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['role_name'] ?? 'member';
    }

    public function isAdmin($userId) {
        $role = $this->getUserRole($userId);
        return $role === 'admin';
    }

    public function updateRole($userId, $roleId) {
        $sql = 'UPDATE users SET role_id = :role_id WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':role_id' => $roleId, ':id' => $userId]);
    }

    public function getRoleIdByName($roleName) {
        $sql = 'SELECT id FROM roles WHERE role_name = :role_name';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':role_name' => $roleName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            return $result['id'];
        }

        $insertSql = 'INSERT INTO roles (role_name, description) VALUES (:role_name, :description)';
        $insertStmt = $this->conn->prepare($insertSql);
        $insertStmt->execute([':role_name' => $roleName, ':description' => ucfirst($roleName) . ' role']);
        return (int) $this->conn->lastInsertId();
    }
}
?>