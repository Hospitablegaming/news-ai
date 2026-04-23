<?php
require_once __DIR__ . '/../config/config.php';

// Simple in-memory cache to avoid hitting API limits
$newsCache = [];

// Check if NEWS_API_KEY is configured
function isNewsApiConfigured() {
    return !empty(NEWS_API_KEY) && NEWS_API_KEY !== 'your_newsapi_key_here';
}

function fetchNews($category = null, $country = 'us') {
    global $newsCache;
    
    // Ensure API key is configured
    if (empty(NEWS_API_KEY)) {
        error_log("NEWS_API_KEY is not configured. Cannot fetch news data.");
        return ['status' => 'error', 'message' => 'NEWS_API_KEY not configured'];
    }
    
    $cacheKey = 'top_headlines_' . ($category ?: 'general') . '_' . $country;

    // Check cache (5 minute expiry)
    if (isset($newsCache[$cacheKey]) && (time() - $newsCache[$cacheKey]['timestamp']) < 300) {
        return $newsCache[$cacheKey]['data'];
    }

    $url = 'https://newsapi.org/v2/top-headlines?country=' . $country . '&pageSize=20&apiKey=' . NEWS_API_KEY;
    if ($category && $category !== 'general') {
        $url .= '&category=' . urlencode($category);
    }

    $data = null;
    
    // Try using cURL first if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NewsAI/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("NewsAPI fetchNews cURL error: $curlError");
        } else {
            error_log("NewsAPI fetchNews cURL request returned HTTP $httpCode");
        }
    } else {
        // Fallback to file_get_contents
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'NewsAI/1.0',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        $data = @file_get_contents($url, false, $context);
    }
    
    $result = $data ? json_decode($data, true) : null;

    // Check for API errors
    if (!$result) {
        error_log("NewsAPI fetchNews failed: No valid JSON response");
        return ['status' => 'error', 'message' => 'No response from NewsAPI'];
    }

    if (isset($result['status']) && $result['status'] === 'error') {
        error_log("NewsAPI fetchNews error: " . ($result['message'] ?? 'Unknown error'));
        return $result;
    }

    if (!$result || !isset($result['status']) || $result['status'] !== 'ok') {
        // Fallback to everything endpoint
        $fallbackUrl = 'https://newsapi.org/v2/everything?q=news&language=en&pageSize=20&sortBy=publishedAt&apiKey=' . NEWS_API_KEY;
        
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fallbackUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'NewsAI/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            
            $data = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'user_agent' => 'NewsAI/1.0', 'ignore_errors' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $data = @file_get_contents($fallbackUrl, false, $context);
        }
        
        $result = $data ? json_decode($data, true) : null;

        if (!$result || !isset($result['status']) || $result['status'] !== 'ok') {
            error_log("NewsAPI fallback also failed");
            return ['status' => 'error', 'message' => 'Both NewsAPI endpoints failed'];
        }
    }

    // Cache the result
    if ($result && isset($result['status']) && $result['status'] === 'ok') {
        $newsCache[$cacheKey] = [
            'timestamp' => time(),
            'data' => $result
        ];
    }

    return $result;
}

function searchNews($query, $daysBack = 7) {
    global $newsCache;
    
    // Ensure API key is configured
    if (empty(NEWS_API_KEY)) {
        error_log("NEWS_API_KEY is not configured. Cannot fetch news data.");
        return ['status' => 'error', 'message' => 'NEWS_API_KEY not configured'];
    }
    
    $query = trim($query);
    if (!$query) {
        return null;
    }

    $cacheKey = 'search_' . md5($query) . '_' . $daysBack;

    // Check cache (10 minute expiry for searches)
    if (isset($newsCache[$cacheKey]) && (time() - $newsCache[$cacheKey]['timestamp']) < 600) {
        return $newsCache[$cacheKey]['data'];
    }

    // Calculate date range for more recent results
    $fromDate = date('Y-m-d', strtotime("-{$daysBack} days"));
    $toDate = date('Y-m-d');

    $url = 'https://newsapi.org/v2/everything?' . http_build_query([
        'q' => $query,
        'language' => 'en',
        'pageSize' => 10,
        'sortBy' => 'publishedAt',
        'from' => $fromDate,
        'to' => $toDate,
        'apiKey' => NEWS_API_KEY
    ]);

    $data = null;
    
    // Try using cURL first if available
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NewsAI/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("NewsAPI cURL error for query '$query': $curlError");
        } else {
            error_log("NewsAPI cURL request for query '$query' returned HTTP $httpCode");
        }
    } else {
        // Fallback to file_get_contents
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'NewsAI/1.0',
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $data = @file_get_contents($url, false, $context);
        error_log("NewsAPI file_get_contents attempt for query '$query'");
    }
    
    $result = $data ? json_decode($data, true) : null;

    // Check for API errors
    if (!$result) {
        error_log("NewsAPI searchNews failed: No valid JSON response for query: $query. Raw data length: " . strlen($data ?? ''));
        return ['status' => 'error', 'message' => 'No valid response from NewsAPI'];
    }

    if (isset($result['status']) && $result['status'] === 'error') {
        error_log("NewsAPI searchNews error: " . ($result['message'] ?? 'Unknown error') . " for query: $query");
        return $result;
    }

    // Cache the result
    if ($result && isset($result['status']) && $result['status'] === 'ok') {
        $newsCache[$cacheKey] = [
            'timestamp' => time(),
            'data' => $result
        ];
    }

    return $result;
}

function getRelevantNewsContext($query, $maxArticles = 5) {
    $searchResult = searchNews($query, 14); // Search last 2 weeks for more context

    if (!$searchResult || !isset($searchResult['articles']) || empty($searchResult['articles'])) {
        return null;
    }

    $context = "Recent news context (last 2 weeks):\n\n";
    $articles = array_slice($searchResult['articles'], 0, $maxArticles);

    foreach ($articles as $index => $article) {
        $publishedDate = isset($article['publishedAt']) ?
            date('M j, Y', strtotime($article['publishedAt'])) : 'Recent';

        $context .= "[" . ($index + 1) . "] {$publishedDate} - " . ($article['title'] ?? 'No title') . "\n";
        $context .= "Source: " . ($article['source']['name'] ?? 'Unknown') . "\n";

        if (!empty($article['description'])) {
            $context .= "Summary: " . trim($article['description']) . "\n";
        }

        if (!empty($article['url'])) {
            $context .= "URL: {$article['url']}\n";
        }

        $context .= "\n";
    }

    return $context;
}

function getBreakingNews() {
    return fetchNews(null, 'us');
}

function getNewsByCategory($category) {
    $validCategories = ['business', 'entertainment', 'general', 'health', 'science', 'sports', 'technology'];
    if (!in_array($category, $validCategories)) {
        $category = 'general';
    }
    return fetchNews($category, 'us');
}
?>
