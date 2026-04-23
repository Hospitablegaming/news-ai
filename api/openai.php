<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/newsapi.php';

function analyseArticle($article, $conversationMessages = []) {
    // Extract the core user query from structured input
    $originalPrompt = '';
    if (preg_match('/Current Question:\s*(.+?)(?:\n\n|\nInstructions:|$)/s', $article, $matches)) {
        $originalPrompt = trim($matches[1]);
    } elseif (preg_match('/Original prompt:\s*(.+)$/m', $article, $matches)) {
        $originalPrompt = trim($matches[1]);
    } else {
        $originalPrompt = trim($article);
    }

    // Determine whether the prompt is news-related
    $newsKeywords = [
        'news', 'latest', 'update', 'breaking', 'report', 'election', 'poll', 'approval rating',
        'president', 'government', 'economy', 'economic', 'recovery', 'recovering', 'recession', 'GDP',
        'unemployment', 'jobs', 'interest rate', 'fed', 'inflation', 'stock', 'market', 'business',
        'health', 'climate', 'environment', 'crime', 'politics', 'foreign', 'military', 'survey',
        'commentary', 'scandal', 'attack', 'policy', 'crisis', 'boom', 'bust', 'trade', 'finance'
    ];

    $hasSource = containsSourceInPrompt($originalPrompt);

    $isNewsRelated = false;
    foreach ($newsKeywords as $keyword) {
        if (stripos($originalPrompt, $keyword) !== false) {
            $isNewsRelated = true;
            break;
        }
    }

    $newsContext = getRelevantNewsContext($originalPrompt, 7);
    if (!empty($newsContext)) {
        $isNewsRelated = true;
    }

    $webSearchContext = '';
    if (!$hasSource) {
        $webSearchContext = performWebSearchTool($originalPrompt);
        if (!empty($webSearchContext) && stripos($webSearchContext, 'No relevant verified news sources were found for the query:') === false) {
            $isNewsRelated = true;
        }
    }

    if ($newsContext && isBroadApprovalQuery($originalPrompt) && newsContextIsSubgroupSpecific($newsContext)) {
        return generateSafeFallbackResponse($originalPrompt, null);
    }

    if (!$isNewsRelated) {
        $newsRelevanceNote = "Note: This query does not appear strongly news-related, but the tool will still attempt to answer it using available news context and general knowledge.\n\n";
    } else {
        $newsRelevanceNote = "Note: This query appears to be news-related, including economic/business topics.\n\n";
    }

    $systemPrompt = "You are a news analyst AI that answers questions using provided news sources. Always use sources to synthesize answers. Respond with these exact sections:\n\n" .
                    "Summary: Brief summary\n" .
                    "Answer: Direct answer with [source: URL] citations inline\n" .
                    "Analysis: Detailed explanation with [source: URL] citations\n" .
                    "Bias Detection: Any bias in question or answer\n" .
                    "Contradiction Detection: Inconsistencies between sources\n" .
                    "Contradiction Score: Percentage\n" .
                    "Claims Found: Specific claims with status\n" .
                    "Reliability Score: Percentage\n\n" .
                    "CRITICAL RULE: If news/web search results are provided in the context, you MUST synthesize them into an answer. Never refuse to answer when sources are available.";

    $userPrompt = "Current Question: " . $originalPrompt . "\n\n";
    $userPrompt .= isset($newsRelevanceNote) ? $newsRelevanceNote : "";
    if (!empty($newsContext)) {
        $userPrompt .= "Recent news context (from NewsAPI search):\n" . $newsContext . "\n";
    } elseif (!empty($webSearchContext) && stripos($webSearchContext, 'No relevant verified news sources were found for the query:') === false) {
        $userPrompt .= "Recent news context (from NewsAPI search):\n" . $webSearchContext . "\n";
    } else {
        $userPrompt .= "Real-time news context is currently unavailable. Answer using the best available information and general knowledge when needed.\n\n";
    }

    if (!empty($conversationMessages)) {
        $userPrompt .= "Conversation history:\n";
        foreach ($conversationMessages as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $role = $msg['role'];
                $content = $msg['content'];
                $userPrompt .= "- " . ucfirst($role) . ": " . $content . "\n";
            } else {
                if (!empty($msg['prompt'])) {
                    $userPrompt .= "- User: " . trim($msg['prompt']) . "\n";
                }
                if (!empty($msg['response'])) {
                    $userPrompt .= "- Assistant: " . trim($msg['response']) . "\n";
                }
            }
        }
        $userPrompt .= "\n";
    }

    $userPrompt .= "Instructions:\n" .
                   "- Use only verified news sources and the provided news context when available.\n" .
                   "- Answer the exact question in the Answer field.\n" .
                   "- The search result titles below represent summaries from major news and financial institutions.\n" .
                   "- For economic/financial questions: synthesize the titles and sources (e.g., The Economist, Goldman Sachs, Federal Reserve, White House, etc.) to construct an answer.\n" .
                   "- Do NOT refuse to answer if sources are provided, even if they don't give a single clear verdict. Synthesize what they collectively indicate.\n" .
                   "- If the search results show economic stability/growth from major sources, state that. If they show mixed views, note the different perspectives.\n" .
                   "- Include clear, news-backed claims in Claims Found.\n";

    if (!defined('OPENAI_API_KEY') || OPENAI_API_KEY === 'YOUR_OPENAI_API_KEY') {
        return generateSafeFallbackResponse($originalPrompt, $newsContext);
    }

    $searchResult = $webSearchContext;
    if (!$hasSource && empty($searchResult)) {
        $searchResult = performWebSearchTool($originalPrompt);
    }
    if (!empty($searchResult) && stripos($searchResult, 'No relevant verified news sources were found for the query:') === false) {
        $userPrompt .= "NewsAPI search results:\n" . $searchResult . "\n";
    } else {
        $userPrompt .= "A search for related verified news sources was attempted but no directly relevant sources were found. Use the best available verified news context if possible.\n";
    }

    $response = callOpenAIContent($systemPrompt, $userPrompt);

    if (!$response) {
        return generateSafeFallbackResponse($originalPrompt, $newsContext);
    }

    return $response;
}

function callOpenAIWithMessages($messages, $tools = [], $function_call = null) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = [
        'model' => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo',
        'messages' => $messages,
        'temperature' => 0.5,
        'max_tokens' => 1000,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];

    if (!empty($tools)) {
        $payload['functions'] = $tools;
    }

    if ($function_call !== null) {
        $payload['function_call'] = $function_call;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Disable SSL verification for compatibility (in production, proper CA certificates should be installed)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return null;
    }

    $response = json_decode($result, true);
    curl_close($ch);

    return $response;
}

function callOpenAIContent($systemPrompt, $userPrompt) {
    $response = callOpenAIWithMessages([
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt]
    ]);

    if (!isset($response['choices'][0]['message']['content'])) {
        return null;
    }

    return trim($response['choices'][0]['message']['content']);
}


function generateSafeFallbackResponse($originalPrompt, $newsContext = null) {
    $summary = "Unable to verify current news data for the exact question.";
    $answer = "No verified news source was found that directly answers the exact approval rating question. Please provide a prompt with an available recent news article or allow the system to search a verified news source.";
    $analysis = "The system did not find a directly relevant and verified recent news article for the exact question asked. To avoid hallucinating a statistic, it is safer to state that no verified current news source supports a precise answer.";
    $biasDetection = "The question is factual and neutral. No bias is introduced by refusing to provide unsupported statistics.";
    $contradictionDetection = "No contradictions are found because the system is not asserting an unverifiable statistic.";
    $contradictionScore = "0";
    $claimsFound = "No verified claims available because no directly relevant news source was found.";
    $reliabilityScore = "0";

    return "Summary: " . $summary . "\n\n" .
           "Answer: " . $answer . "\n\n" .
           "Analysis: " . $analysis . "\n\n" .
           "Bias Detection: " . $biasDetection . "\n\n" .
           "Contradiction Detection: " . $contradictionDetection . "\n\n" .
           "Contradiction Score: " . $contradictionScore . "%\n\n" .
           "Claims Found: " . $claimsFound . "\n\n" .
           "Reliability Score: " . $reliabilityScore . "%\n";
}

function isBroadApprovalQuery($prompt) {
    $promptLower = strtolower($prompt);
    $approvalKeywords = ['approval rating', 'approval', 'poll', 'survey', 'rating', 'approval percentage'];
    $demographicKeywords = ['catholic', 'church', 'church-going', 'evangelical', 'religious', 'hispanic', 'latino', 'black', 'white', 'women', 'men', 'youth', 'young voters', 'suburban', 'urban', 'rural', 'demographic', 'group', 'generation', 'age', 'voters'];

    $hasApproval = false;
    foreach ($approvalKeywords as $keyword) {
        if (strpos($promptLower, $keyword) !== false) {
            $hasApproval = true;
            break;
        }
    }
    if (!$hasApproval) {
        return false;
    }

    foreach ($demographicKeywords as $keyword) {
        if (strpos($promptLower, $keyword) !== false) {
            return false;
        }
    }

    return true;
}

function newsContextIsSubgroupSpecific($newsContext) {
    if (empty($newsContext)) {
        return false;
    }

    $subgroupKeywords = ['catholic', 'church', 'church-going', 'evangelical', 'religious', 'hispanic', 'latino', 'black', 'white', 'women', 'men', 'youth', 'young voters', 'suburban', 'urban', 'rural', 'demographic', 'group', 'generation', 'age', 'voters'];
    $contextLower = strtolower($newsContext);

    foreach ($subgroupKeywords as $keyword) {
        if (strpos($contextLower, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

function containsSourceInPrompt($prompt) {
    if (preg_match('/https?:\/\/[^\s]+/i', $prompt)) {
        return true;
    }

    if (preg_match('/\bsource[s]?\b\s*[:]?/i', $prompt)) {
        return true;
    }

    if (preg_match('/\bvia\b\s+\S+/i', $prompt)) {
        return true;
    }

    return false;
}

function performNewsSearchTool($query) {
    $expandedQuery = $query;
    if (stripos($query, 'approval rating') !== false && stripos($query, 'trump') !== false) {
        $expandedQuery .= ' latest polls approval rating news';
    }

    $searchResult = searchNews($expandedQuery, 21);
    if (!$searchResult || !isset($searchResult['articles']) || empty($searchResult['articles'])) {
        return "No relevant verified news sources were found for the query: " . $query;
    }

    $results = "Web search results for query: " . $query . "\n\n";
    $articles = array_slice($searchResult['articles'], 0, 8);
    foreach ($articles as $index => $article) {
        $publishedDate = isset($article['publishedAt']) ? date('M j, Y', strtotime($article['publishedAt'])) : 'Recent';
        $sourceName = isset($article['source']['name']) ? $article['source']['name'] : 'Unknown';
        $title = isset($article['title']) ? trim($article['title']) : 'No title available';
        $description = isset($article['description']) ? trim($article['description']) : 'No description available';
        $url = isset($article['url']) ? $article['url'] : 'No URL available';

        $results .= "[" . ($index + 1) . "] " . $publishedDate . " - " . $title . "\n";
        $results .= "Source: " . $sourceName . "\n";
        $results .= "Summary: " . $description . "\n";
        $results .= "URL: " . $url . "\n\n";
    }

    return trim($results);
}

function performWebSearchTool($query) {
    $expandedQuery = $query;
    if (stripos($query, 'approval rating') !== false && stripos($query, 'trump') !== false) {
        $expandedQuery .= ' latest polls approval rating news';
    }

    return performNewsSearchTool($expandedQuery);
}

function generateMockAnalysis($article) {
    // Parse the enhanced prompt to extract original question and context
    $originalPrompt = '';
    $hasNewsContext = false;
    $hasHistory = false;

    // Extract original prompt - look for "Current Question:" first, then fallback
    if (preg_match('/Current Question:\s*(.+?)(?:\n\n|\nInstructions:|$)/s', $article, $matches)) {
        $originalPrompt = trim($matches[1]);
    } elseif (preg_match('/Original prompt:\s*(.+)$/m', $article, $matches)) {
        $originalPrompt = trim($matches[1]);
    } else {
        $originalPrompt = substr($article, 0, min(1000, strlen($article)));
    }

    // Check for NewsAPI context
    if (strpos($article, 'Recent news context') !== false || strpos($article, 'Related news articles from NewsAPI') !== false) {
        $hasNewsContext = true;
    }
    if (strpos($article, 'Real-time news context is currently unavailable') !== false) {
        $hasNewsContext = false;
    }

    // Check for conversation history
    if (strpos($article, 'Conversation history:') !== false) {
        $hasHistory = true;
    }

    // If no valid news context is available for an exact statistic query, return a safe fallback rather than fabricate a number.
    if (!$hasNewsContext && preg_match('/approval\s+rating|poll|survey|percentage|rating/i', $originalPrompt)) {
        return generateSafeFallbackResponse($originalPrompt, null);
    }

    // Analyze content for specific topics
    $isIranUS = false;
    $isMilitary = false;
    $isDiplomatic = false;

    if (stripos($originalPrompt, 'iran') !== false && (stripos($originalPrompt, 'us') !== false || stripos($originalPrompt, 'america') !== false || stripos($originalPrompt, 'united states') !== false)) {
        $isIranUS = true;
    }
    if (stripos($originalPrompt, 'military') !== false || stripos($originalPrompt, 'strike') !== false || stripos($originalPrompt, 'attack') !== false || stripos($originalPrompt, 'bomb') !== false) {
        $isMilitary = true;
    }
    if (stripos($originalPrompt, 'diplomatic') !== false || stripos($originalPrompt, 'condemn') !== false || stripos($originalPrompt, 'rhetoric') !== false) {
        $isDiplomatic = true;
    }

    // Generate analysis based on content
    if ($isIranUS) {
        $summary = "Iran has responded to US actions through military retaliation, diplomatic condemnation, and increased nuclear activities, maintaining a complex relationship marked by tension and strategic balancing.";
        $analysis = "Iran's response to US conflicts involves multiple dimensions: military retaliation (such as missile strikes following the Soleimani killing), diplomatic efforts to gain international support, and rhetorical condemnation of US policies. The nuclear program has been accelerated in response to sanctions, creating a cycle of escalation and international concern.";
        $biasAssessment = "The analysis appears balanced, presenting Iran's actions as multi-faceted responses to perceived US aggression. Language is factual and avoids overt partisanship, though it frames Iranian actions as 'responses' which may imply defensive positioning.";
        $contradictionDetection = "No direct contradictions found in the presented information. Claims about military responses and diplomatic actions are consistent with historical patterns.";
        $contradictionScore = "25%";

        // Generate claims with source links for Iran-US topics
        $claimsFound = [
            "Iran retaliated militarily after Soleimani killing",
            "Iran uses diplomatic channels for condemnation",
            "Iran increased nuclear activities in response to sanctions",
            "Iranian leaders use strong rhetoric against US"
        ];

        $sourceUrls = [
            "https://en.wikipedia.org/wiki/Killing_of_Qasem_Soleimani",
            "https://www.state.gov/countries-areas/iran/",
            "https://www.iaea.org/topics/nuclear-programme-of-iran",
            "https://www.aljazeera.com/tag/iran-us-relations/"
        ];

        $claimsAnalysis = "";
        foreach ($claimsFound as $i => $claim) {
            $claimsAnalysis .= ($i+1) . ". " . $claim . " - Source: " . $sourceUrls[$i] . " - Verified against historical records";

            if ($i < count($claimsFound) - 1) {
                $claimsAnalysis .= "\n";
            }
        }

        $reliabilityScore = "85%";
        $sources = "Historical records (2020), IAEA reports, US State Department statements, Iranian government communications, International news archives";
    } else {
        $isQuestion = preg_match('/\b(what|where|when|who|why|how)\b/i', $originalPrompt);
        $answer = '';

        if ($isQuestion && stripos($originalPrompt, 'approval rating') !== false && stripos($originalPrompt, 'trump') !== false) {
            $summary = "Donald Trump's approval rating has generally been in the low-to-mid 40% range in recent polling, though exact current figures depend on the latest polls.";
            $answer = "Recent aggregated polling data shows Trump approval near 40-45%, but exact current values vary by pollster and should be verified with the latest released data.";
            $analysis = "Pollsters have generally tracked Trump's approval in the low-to-mid 40 range. Without live polling data, this answer is based on the latest accessible historical polling trends and the available news context. Specific sources may differ, but this range is consistent with the most recent public polling summaries.";
            $claimsFound = [
                "Trump approval rating is approximately 40-45% based on recent polls",
                "Exact approval values vary by pollster and require current polling data"
            ];
            $claimsAnalysis = "1. " . $claimsFound[0] . " - Source: https://www.realclearpolitics.com/ -- Based on recent aggregated polling trends\n" .
                              "2. " . $claimsFound[1] . " - Source: https://www.gallup.com/ -- Polling values change over time";
            $biasAssessment = "The question is neutral and factual. The analysis focuses on available polling patterns and notes the limitation that live figures may have changed.";
            $contradictionDetection = "No direct contradiction detected within the available polling context. The answer is consistent with the most recent polling trends.";
            $contradictionScore = "25";
            $reliabilityScore = "70";
            $sources = "RealClearPolitics polling summaries, Gallup approval polling, NewsAPI context if available";
        } else {
            $summary = "Answer: " . trim(substr($originalPrompt, 0, 100));
            if (strlen($originalPrompt) > 100) {
                $summary .= "...";
            }

            $answer = "The best available response is derived from general knowledge and recent news context. For precise verification, follow the source links provided in the claims section.";
            $analysis = "This question appears to ask for a factual answer. The response is structured to identify key claims and verify them against what is currently known. When live news context is present, the answer uses that context where possible; otherwise it relies on historical patterns.";

            // Detect possible bias indicators
            $biasIndicators = [];
            $provocativeWords = ['attack', 'slam', 'horrible', 'fantastic', 'disaster', 'triumph'];
            foreach ($provocativeWords as $word) {
                if (stripos($originalPrompt, $word) !== false) {
                    $biasIndicators[] = 'Emotionally charged language detected';
                }
            }

            $biasAssessment = "Language analysis: ";
            if (!empty($biasIndicators)) {
                $biasAssessment .= implode('; ', $biasIndicators) . ". ";
            } else {
                $biasAssessment .= "Neutral tone observed. ";
            }

            if (preg_match('/(?:should|must|need to|critical)/i', $originalPrompt)) {
                $biasAssessment .= "Prescriptive framing may indicate advocacy language.";
            } else {
                $biasAssessment .= "Balanced question structure without obvious advocacy.";
            }

            $contradictionDetection = "";
            $contradictionScore = 50;

            if ($hasNewsContext) {
                $contradictionDetection = "Cross-referenced with available news sources. Checking for factual discrepancies between claims and reported events.";
                $contradictionScore = 35;
            } else {
                $contradictionDetection = "Limited external sources available. Analysis based on general knowledge patterns through 2024. Recent claims (2025+) may not be verified.";
                $contradictionScore = 65;
            }

            // Extract potential claims for analysis
            $claimsFound = [];
            if (preg_match_all('/\b(?:is|was|will|has|have|did|does)\s+[^.!?]+[.!?]/', $originalPrompt, $matches)) {
                $claimsFound = array_slice($matches[0], 0, 3);
            }
            if (empty($claimsFound)) {
                $claimsFound[] = trim($originalPrompt);
            }

            $claimsAnalysis = "";
            foreach ($claimsFound as $i => $claim) {
                $claimText = trim(substr($claim, 0, 80));
                $searchQuery = urlencode($claimText);
                $searchUrl = "https://www.google.com/search?q=" . $searchQuery;
                $claimsAnalysis .= ($i+1) . ". " . $claimText . " - Source: " . $searchUrl;

                if ($hasNewsContext) {
                    $claimsAnalysis .= " - Cross-checked against NewsAPI sources";
                } else {
                    $claimsAnalysis .= " - Based on general knowledge (knowledge cutoff: April 2024)";
                }
                if ($i < count($claimsFound) - 1) {
                    $claimsAnalysis .= "\n";
                }
            }

            $reliabilityScore = 50;
            if ($hasNewsContext) {
                $reliabilityScore = 75;
                if ($hasHistory) {
                    $reliabilityScore = 80;
                }
            } else {
                if ($hasHistory) {
                    $reliabilityScore = 60;
                }
                if (preg_match('/202[5-9]|203[0-9]/', $originalPrompt)) {
                    $reliabilityScore = 35;
                }
            }

            $sources = "";
            if ($hasNewsContext) {
                $sources = "NewsAPI sources (various international news outlets), General knowledge up to April 2024";
            } else {
                $sources = "General knowledge and historical records up to April 2024, No real-time news sources available";
            }
        }
    }

    return "Summary: " . $summary . "\n\n" .
           ($answer ? "Answer: " . $answer . "\n\n" : "") .
           "Analysis: " . $analysis . "\n\n" .
           "Bias Detection: " . $biasAssessment . "\n\n" .
           "Contradiction Detection: " . $contradictionDetection . "\n\n" .
           "Contradiction Score: " . $contradictionScore . "%\n\n" .
           "Claims Found: " . $claimsAnalysis . "\n\n" .
           "Reliability Score: " . $reliabilityScore . "%\n\n" .
           "Sources: " . $sources . "\n";
}