<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['error' => 'URL is required']);
    exit;
}

function extractTweetId($url) {
    if (preg_match('/status\/(\d+)/', $url, $matches)) {
        return $matches[1];
    }
    return null;
}

function getTwitterData($tweetId) {
    $apiUrl = "https://api.vxtwitter.com/Twitter/status/" . $tweetId;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    return json_decode($response, true);
}

try {
    $tweetId = extractTweetId($url);
    
    if (!$tweetId) {
        throw new Exception('Invalid Twitter URL');
    }
    
    $tweetData = getTwitterData($tweetId);
    
    if (!$tweetData) {
        throw new Exception('Failed to fetch tweet data');
    }
    
    // If the tweet has media
    $mediaUrls = [];
    if (isset($tweetData['media_extended'])) {
        foreach ($tweetData['media_extended'] as $media) {
            $mediaUrls[] = [
                'type' => $media['type'],
                'url' => $media['url'],
                'thumbnail' => $media['thumbnail_url'] ?? null,
            ];
        }
    }
    
    $response = [
        'success' => true,
        'data' => [
            'id' => $tweetData['id'] ?? $tweetId,
            'text' => $tweetData['text'] ?? '',
            'author' => [
                'name' => $tweetData['user_name'] ?? '',
                'username' => $tweetData['user_screen_name'] ?? '',
                'profile_image' => $tweetData['user_profile_image_url'] ?? '',
            ],
            'created_at' => $tweetData['created_at'] ?? '',
            'media' => $mediaUrls,
            'likes_count' => $tweetData['likes'] ?? 0,
            'retweets_count' => $tweetData['retweets'] ?? 0,
            'replies_count' => $tweetData['replies'] ?? 0,
            'url' => $url,
            'download_urls' => array_map(function($media) {
                return $media['url'];
            }, $mediaUrls),
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>