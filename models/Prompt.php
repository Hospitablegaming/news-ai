<?php
class Prompt {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function save($user, $prompt, $response, $public, $conversationId = null) {
        if ($conversationId === null) {
            $conversationId = 0;
        }

        $sql = 'INSERT INTO prompts(user_id,prompt,response,is_public,conversation_id) VALUES(:u,:p,:r,:pub,:cid)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':u' => $user,
            ':p' => $prompt,
            ':r' => $response,
            ':pub' => $public,
            ':cid' => $conversationId
        ]);

        $id = $this->conn->lastInsertId();
        if ($conversationId == 0) {
            $conversationId = $id;
            $sql = 'UPDATE prompts SET conversation_id = :cid WHERE id = :id';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':cid' => $conversationId, ':id' => $id]);
        }

        return $conversationId;
    }

    public function update($id, $user, $prompt, $response, $public) {
        $sql = 'UPDATE prompts SET prompt = :p, response = :r, is_public = :pub WHERE id = :id AND user_id = :u';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':p' => $prompt, ':r' => $response, ':pub' => $public, ':id' => $id, ':u' => $user]);
        return $stmt->rowCount();
    }

    public function getById($id) {
        $sql = 'SELECT * FROM prompts WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPublic() {
        $sql = 'SELECT prompts.*, users.username FROM prompts JOIN users ON users.id = prompts.user_id WHERE is_public = 1 ORDER BY created_at DESC';
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserPrompts($user) {
        $sql = 'SELECT * FROM prompts WHERE user_id = :u ORDER BY created_at DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':u' => $user]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserConversations($user) {
        $sql = 'SELECT p.* FROM prompts p JOIN (SELECT conversation_id, MAX(created_at) AS last_created FROM prompts WHERE user_id = :u GROUP BY conversation_id) t ON p.conversation_id = t.conversation_id AND p.created_at = t.last_created ORDER BY p.created_at DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':u' => $user]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConversation($conversationId) {
        $sql = 'SELECT * FROM prompts WHERE conversation_id = :cid ORDER BY created_at ASC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cid' => $conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentPrompts($limit = 20) {
        $sql = 'SELECT p.*, u.username FROM prompts p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT :limit';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteUserPrompts($userId) {
        $sql = 'DELETE FROM prompts WHERE user_id = :user_id';
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':user_id' => $userId]);
    }
}
?>