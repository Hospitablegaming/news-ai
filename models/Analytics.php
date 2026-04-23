<?php
class Analytics {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function log($userId, $tokens) {
        $sql = 'INSERT INTO analytics (user_id, tokens_used) VALUES (:u, :t)';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':u' => $userId, ':t' => $tokens]);
    }

    public function stats() {
        $sql = 'SELECT COUNT(*) AS prompts, COALESCE(SUM(tokens_used), 0) AS tokens FROM analytics';
        $stmt = $this->conn->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserStats($userId) {
        $sql = 'SELECT COUNT(*) AS prompts, COALESCE(SUM(tokens_used), 0) AS tokens FROM analytics WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserTokenHistory($userId, $limit = 30) {
        $sql = 'SELECT tokens_used, created_at FROM analytics WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteUserData($userId) {
        $sql = 'DELETE FROM analytics WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':user_id' => $userId]);
    }
}
?>