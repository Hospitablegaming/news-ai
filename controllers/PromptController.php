<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not authenticated');
}

require_once '../config/database.php';
require_once '../models/Prompt.php';
require_once '../models/User.php';
require_once '../models/Subscription.php';
require_once '../api/openai.php';
require_once '../api/newsapi.php';
require_once '../models/Analytics.php';

try {
    $action = $_GET['action'] ?? 'analyze';
    
    $db = new Database();
    $conn = $db->connect();
    $model = new Prompt($conn);
    
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $deleteType = $_GET['type'] ?? 'auto';
        
        if (!$id) {
            die('Invalid ID');
        }

        // If type is explicitly conversation, delete all prompts in that conversation
        if ($deleteType === 'conversation') {
            // Check if this conversation_id exists for this user
            $sql = 'SELECT COUNT(*) as count FROM prompts WHERE conversation_id = :id AND user_id = :user_id';
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                die('Conversation not found');
            }

            // Delete all prompts in this conversation for this user
            $sql = 'DELETE FROM prompts WHERE conversation_id = :id AND user_id = :user_id';
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);
            
            echo 'success';
            exit();
        }

        // If type is explicitly prompt, delete just that prompt
        if ($deleteType === 'prompt') {
            $sql = 'SELECT user_id FROM prompts WHERE id = :id LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);
            $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prompt) {
                die('Prompt not found');
            }

            if ($prompt['user_id'] != $_SESSION['user_id']) {
                die('Unauthorized');
            }

            $sql = 'DELETE FROM prompts WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            echo 'success';
            exit();
        }

        // First, check if this is a prompt ID
        $sql = 'SELECT id, user_id, conversation_id FROM prompts WHERE id = :id LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prompt) {
            // Found a prompt with this ID
            if ($prompt['user_id'] != $_SESSION['user_id']) {
                die('Unauthorized');
            }

            // Delete just this prompt
            $sql = 'DELETE FROM prompts WHERE id = :id';
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            echo 'success';
            exit();
        }

        // Not a prompt ID, try as conversation_id
        $sql = 'SELECT user_id FROM prompts WHERE conversation_id = :id LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prompt) {
            die('Not found');
        }

        if ($prompt['user_id'] != $_SESSION['user_id']) {
            die('Unauthorized');
        }

        $sql = 'DELETE FROM prompts WHERE conversation_id = :id AND user_id = :user_id';
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id, ':user_id' => $_SESSION['user_id']]);

        echo 'success';
        exit();
    }

    if ($action === 'update') {
        $promptId = intval($_GET['id'] ?? 0);
        if (!$promptId || empty($_GET['prompt'])) {
            header('Location: ../views/edit_chat.php?id=' . $promptId . '&error=Invalid request - missing prompt or ID');
            exit();
        }

        $existing = $model->getById($promptId);
        if (!$existing || $existing['user_id'] != $_SESSION['user_id']) {
            header('Location: ../views/dashboard.php?error=Unauthorized access');
            exit();
        }

        $updatedPrompt = trim($_GET['prompt']);
        $public = isset($_GET['public']) ? 1 : 0;

        // Generate new response from the edited prompt
        $newResponse = analyseArticle($updatedPrompt);
        if (!$newResponse) {
            $newResponse = "Unable to generate response";
        }

        // Clean the response thoroughly - remove PHP tags, dangerous HTML tags, but allow safe links
        $updatedResponse = preg_replace('/<\?php.*?\?>/s', '', $newResponse); // Remove PHP tags

        // Allow only safe <a> tags, remove all other HTML tags
        $updatedResponse = strip_tags($updatedResponse, '<a>');

        // Now validate and clean the <a> tags to ensure they're safe
        $updatedResponse = preg_replace_callback(
            '/<a\s+([^>]*?)>(.*?)<\/a>/is',
            function($matches) {
                $attributes = $matches[1];
                $content = $matches[2];

                // Parse href attribute
                $href = '';
                if (preg_match('/href="([^"]*)"/i', $attributes, $hrefMatch)) {
                    $href = $hrefMatch[1];
                } elseif (preg_match("/href='([^']*)'/i", $attributes, $hrefMatch)) {
                    $href = $hrefMatch[1];
                }

                // Validate href - must be http or https URL
                if (empty($href) || !filter_var($href, FILTER_VALIDATE_URL) ||
                    !(stripos($href, 'http://') === 0 || stripos($href, 'https://') === 0)) {
                    // Invalid link, return just the content
                    return $content;
                }

                // Return safe link
                return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $content . '</a>';
            },
            $updatedResponse
        );

        // Convert [source: URL] inline citations to markdown links
        $updatedResponse = preg_replace_callback(
            '/\[source:\s*(https?:\/\/[^\s\]]+)\]/i',
            function($matches) {
                $url = $matches[1];
                // Extract domain for link text
                $urlParts = parse_url($url);
                $domain = $urlParts['host'] ?? 'source';
                $domain = preg_replace('/^www\./', '', $domain);
                return '[' . $domain . '](' . $url . ')';
            },
            $updatedResponse
        );

        $updatedResponse = trim($updatedResponse); // Trim whitespace
        $updatedResponse = preg_replace('/\n{3,}/', "\n\n", $updatedResponse); // Normalize line breaks

        // Track token usage for analytics (update action)
        $inputTokens = (int) (strlen($updatedPrompt) / 4); // Rough estimate: 1 token ≈ 4 characters
        $outputTokens = (int) (strlen($updatedResponse) / 4);
        $totalTokens = $inputTokens + $outputTokens;

        $analyticsModel = new Analytics($conn);
        $analyticsModel->log($_SESSION['user_id'], $totalTokens);

        $result = $model->update($promptId, $_SESSION['user_id'], $updatedPrompt, $updatedResponse, $public);
        if ($result > 0) {
            header('Location: ../views/dashboard.php?updated=1');
        } else {
            header('Location: ../views/edit_chat.php?id=' . $promptId . '&error=Failed to update chat');
        }
        exit();
    }

    if ($action === 'analyze') {
        if (empty($_GET['prompt'])) {
            die('Prompt cannot be empty');
        }

        // Check if user has reached their monthly prompt limit
        $userModel = new User($conn);
        $subscriptionModel = new Subscription($conn);
        
        $user = $userModel->getById($_SESSION['user_id']);
        $subscription = $subscriptionModel->getById($user['subscription_id'] ?? 1);
        $monthlyUsage = $userModel->getMonthlyUsage($_SESSION['user_id']);
        
        if ($subscription && $monthlyUsage >= $subscription['prompt_limit']) {
            die('You have reached your monthly prompt limit of ' . $subscription['prompt_limit'] . '. Please upgrade your plan or wait until next month.');
        }

        $prompt = trim($_GET['prompt']);
        $public = isset($_GET['public']) ? 1 : 0;
        $conversationId = intval($_GET['conversation_id'] ?? 0);
        $conversationMessages = [];

        if ($conversationId > 0) {
            $conversationMessages = $model->getConversation($conversationId);
            if (empty($conversationMessages) || $conversationMessages[0]['user_id'] != $_SESSION['user_id']) {
                $conversationId = 0;
                $conversationMessages = [];
            }
        }

        $newsContext = getRelevantNewsContext($prompt, 5);
        $hasNewsContext = !empty($newsContext) && strpos($newsContext, 'Recent news context') !== false;

        $analysisPrompt = "You are an AI news analyst. ";
        if ($hasNewsContext) {
            $analysisPrompt .= "Use the following recent news context and conversation history to provide a comprehensive, evidence-based analysis.\n\n";
            $analysisPrompt .= $newsContext . "\n";
        } else {
            $analysisPrompt .= "Use your conversation history and general news knowledge to provide analysis based on your knowledge and general news patterns.\n\n";
        }
        $analysisPrompt .= "Current Question: " . $prompt . "\n\n";
        $analysisPrompt .= "Instructions:\n";
        $analysisPrompt .= "- Provide factual, evidence-based analysis\n";
        if ($hasNewsContext) {
            $analysisPrompt .= "- Cite specific news sources and dates when relevant\n";
        }
        $analysisPrompt .= "- Acknowledge if information is limited or outdated\n";
        $analysisPrompt .= "- Consider multiple perspectives when available\n";
        $analysisPrompt .= "- Be concise but comprehensive\n\n";
        if (!$hasNewsContext) {
            $analysisPrompt .= "Note: Real-time news context is currently unavailable. Analysis is based on general knowledge patterns.\n\n";
        }
        $analysisPrompt .= "Analysis:";

        $response = analyseArticle($analysisPrompt, $conversationMessages);
        if (!$response) {
            die('Error: Could not get response from API');
        }

        // Clean the response - remove PHP tags and dangerous HTML, but allow basic formatting
        $normalizedResponse = preg_replace('/<\?php.*?\?>/s', '', $response); // Remove PHP tags
        $normalizedResponse = strip_tags($normalizedResponse); // Remove all HTML tags

        // Convert [source: URL] inline citations to markdown-style links
        $normalizedResponse = preg_replace_callback(
            '/\[source:\s*(https?:\/\/[^\s\]]+)\]/i',
            function($matches) {
                $url = $matches[1];
                // Extract domain for link text
                $urlParts = parse_url($url);
                $domain = $urlParts['host'] ?? 'source';
                $domain = preg_replace('/^www\./', '', $domain);
                return '[' . $domain . '](' . $url . ')';
            },
            $normalizedResponse
        );

        $normalizedResponse = trim($normalizedResponse); // Trim whitespace
        $normalizedResponse = preg_replace('/\n{3,}/', "\n\n", $normalizedResponse); // Normalize line breaks

        // Track token usage for analytics
        $inputTokens = (int) (strlen($analysisPrompt) / 4); // Rough estimate: 1 token ≈ 4 characters
        $outputTokens = (int) (strlen($normalizedResponse) / 4);
        $totalTokens = $inputTokens + $outputTokens;

        $analyticsModel = new Analytics($conn);
        $analyticsModel->log($_SESSION['user_id'], $totalTokens);

        $conversationId = $model->save($_SESSION['user_id'], $prompt, $normalizedResponse, $public, $conversationId ?: null);
        echo $normalizedResponse;
    }
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>