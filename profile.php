<?php

class InstagramProfileFetcher
{
    private $cache = [];
    private $lastRequestTime = 0;
    private $rateLimitDelay = 3; // seconds between requests

    /**
     * Get Instagram profile details by username
     * get method username paramater
     */
    public function getProfileDetails($username)
    {
        // Check cache first
        $cacheKey = 'profile_' . $username;
        $cachedData = $this->getFromCache($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Try multiple methods to get profile data
        $profileData = $this->fetchProfileDataGraphQL($username);

        if (!$profileData || isset($profileData['error'])) {
            $profileData = $this->fetchProfileDataWeb($username);
        }

        if (!$profileData || isset($profileData['error'])) {
            $profileData = $this->fetchProfileDataMobile($username);
        }

        if ($profileData && !isset($profileData['error'])) {
            // Cache the result for 5 minutes
            $this->setCache($cacheKey, $profileData, 300);
        }

        return $profileData ?: ['error' => 'Unable to fetch profile data'];
    }

    /**
     * Fetch profile data using Instagram's GraphQL endpoint
     */
    private function fetchProfileDataGraphQL($username)
    {
        $this->applyRateLimit();

        $variables = json_encode([
            'username' => $username,
        ]);

        $url = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);

        $headers = [
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Host: www.instagram.com',
            'User-Agent: ' . $this->getRandomUserAgent(),
            'X-Requested-With: XMLHttpRequest',
            'X-IG-App-ID: 936619743392459',
            'X-IG-WWW-Claim: 0',
            'X-CSRFToken: ' . $this->generateCSRFToken(),
            'Referer: https://www.instagram.com/' . $username . '/',
            'Origin: https://www.instagram.com'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['data']['user'])) {
                return $this->formatGraphQLProfileData($data['data']['user']);
            }
        }
        return null;
    }

    /**
     * Fetch profile data from web page
     */
    private function fetchProfileDataWeb($username)
    {
        $this->applyRateLimit();

        $profileUrl = "https://www.instagram.com/{$username}/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $profileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->getRandomUserAgent());
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return $this->extractProfileDataFromHTML($response, $username);
    }

    /**
     * Fetch using mobile user agent
     */
    private function fetchProfileDataMobile($username)
    {
        $this->applyRateLimit();

        $profileUrl = "https://www.instagram.com/{$username}/";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $profileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return $this->extractProfileDataFromHTML($response, $username);
    }

    /**
     * Format GraphQL profile data
     */
    private function formatGraphQLProfileData($user)
    {
        return [
            'success' => true,
            'username' => $user['username'] ?? '',
            'full_name' => $user['full_name'] ?? '',
            'biography' => $user['biography'] ?? '',
            'profile_pic_url' => $user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? '',
            'followers_count' => $user['edge_followed_by']['count'] ?? 0,
            'following_count' => $user['edge_follow']['count'] ?? 0,
            'posts_count' => $user['edge_owner_to_timeline_media']['count'] ?? 0,
            'is_verified' => $user['is_verified'] ?? false,
            'is_private' => $user['is_private'] ?? false,
            'external_url' => $user['external_url'] ?? '',
            'business_category' => $user['business_category_name'] ?? '',
            'is_business_account' => $user['is_business_account'] ?? false,
            'recent_posts' => $this->getRecentPosts($user['edge_owner_to_timeline_media']['edges'] ?? [])
        ];
    }

    /**
     * Extract profile data from HTML response using multiple methods
     */

    private function extractProfileDataFromHTML($html, $username)
    {
        // Method 1: Try to find JSON data in script tags
        if (preg_match('/window\._sharedData\s*=\s*({.+?});/', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if ($jsonData && isset($jsonData['entry_data']['ProfilePage'][0]['graphql']['user'])) {
                return $this->formatGraphQLProfileData($jsonData['entry_data']['ProfilePage'][0]['graphql']['user']);
            }
        }

        // Method 2: Look for newer JSON structure in script tags
        if (preg_match_all('/<script[^>]*>(.+?)<\/script>/s', $html, $scriptMatches)) {
            foreach ($scriptMatches[1] as $script) {
                if (strpos($script, '"ProfilePage"') !== false || strpos($script, '"user"') !== false) {
                    // Try to extract JSON from script content
                    if (preg_match('/{"config":.+?}(?=<\/script>|$)/', $script, $jsonMatches)) {
                        $jsonData = json_decode($jsonMatches[0], true);
                        if ($jsonData && isset($jsonData['entry_data']['ProfilePage'][0]['graphql']['user'])) {
                            return $this->formatGraphQLProfileData($jsonData['entry_data']['ProfilePage'][0]['graphql']['user']);
                        }
                    }
                }
            }
        }

        // Method 3: Extract from meta tags and visible content
        return $this->extractFromMetaTagsAdvanced($html, $username);
    }

    /**
     * Advanced meta tag extraction with better parsing
     */
    private function extractFromMetaTagsAdvanced($html, $username)
    {
        $data = [
            'success' => true,
            'username' => $username,
            'full_name' => '',
            'biography' => '',
            'profile_pic_url' => '',
            'followers_count' => 0,
            'following_count' => 0,
            'posts_count' => 0,
            'is_verified' => false,
            'is_private' => false,
            'external_url' => '',
            'recent_posts' => []
        ];

        // Extract title
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/', $html, $titleMatches)) {
            $title = html_entity_decode($titleMatches[1]);
            // Parse different title formats
            if (preg_match('/^(.+?)\s*\(@' . preg_quote($username, '/') . '\)/', $title, $nameMatches)) {
                $data['full_name'] = trim($nameMatches[1]);
            } elseif (preg_match('/^(.+?)\s*•\s*Instagram/', $title, $nameMatches)) {
                $data['full_name'] = trim($nameMatches[1]);
            }
        }

        // Extract from og:description
        if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\']/', $html, $descMatches)) {
            $description = html_entity_decode($descMatches[1]);

            // Extract follower counts using various patterns
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*Followers/', $description, $followerMatches)) {
                $data['followers_count'] = $this->parseCount($followerMatches[1]);
            }
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*Following/', $description, $followingMatches)) {
                $data['following_count'] = $this->parseCount($followingMatches[1]);
            }
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*Posts/', $description, $postMatches)) {
                $data['posts_count'] = $this->parseCount($postMatches[1]);
            }

            // Extract bio
            $bio = preg_replace('/\d+(?:,\d+)*(?:\.\d+)?[KMB]?\s*(?:Followers|Following|Posts)\s*[•·-]\s*/', '', $description);
            $bio = preg_replace('/See Instagram photos and videos from.*/', '', $bio);
            $data['biography'] = trim($bio);
        }

        // Extract profile image
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']*)["\']/', $html, $imageMatches)) {
            $data['profile_pic_url'] = $imageMatches[1];
        }

        // Check for verification badge in HTML
        if (strpos($html, 'verified') !== false || strpos($html, 'BadgeType.VERIFIED') !== false) {
            $data['is_verified'] = true;
        }

        // Check for private account
        if (strpos($html, 'private') !== false || strpos($html, 'This Account is Private') !== false) {
            $data['is_private'] = true;
        }

        return $data;
    }

    /**
     * Parse count strings like "1.2M", "15K", "1,234"
     */
    private function parseCount($countStr)
    {
        $countStr = str_replace(',', '', $countStr);

        if (strpos($countStr, 'M') !== false) {
            return (int) (floatval($countStr) * 1000000);
        } elseif (strpos($countStr, 'K') !== false) {
            return (int) (floatval($countStr) * 1000);
        } elseif (strpos($countStr, 'B') !== false) {
            return (int) (floatval($countStr) * 1000000000);
        }

        return (int) $countStr;
    }

    /**
     * Get recent posts data
     */
    private function getRecentPosts($edges)
    {
        $posts = [];
        foreach (array_slice($edges, 0, 30) as $edge) {
            $node = $edge['node'];
            $posts[] = [
                'id' => $node['id'] ?? '',
                'shortcode' => $node['shortcode'] ?? '',
                'display_url' => $node['display_url'] ?? '',
                'is_video' => $node['is_video'] ?? false,
                'likes_count' => $node['edge_liked_by']['count'] ?? 0,
                'comments_count' => $node['edge_media_to_comment']['count'] ?? 0,
                'caption' => $node['edge_media_to_caption']['edges'][0]['node']['text'] ?? '',
                'taken_at_timestamp' => $node['taken_at_timestamp'] ?? 0
            ];
        }
        return $posts;
    }

    /**
     * Generate CSRF token
     */
    private function generateCSRFToken()
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Apply rate limiting
     */
    private function applyRateLimit()
    {
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - $this->lastRequestTime;

        if ($timeSinceLastRequest < $this->rateLimitDelay) {
            $sleepTime = $this->rateLimitDelay - $timeSinceLastRequest;
            usleep($sleepTime * 1000000);
        }

        $this->lastRequestTime = microtime(true);
    }

    /**
     * Get random user agent
     */
    private function getRandomUserAgent()
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15'
        ];

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Simple in-memory cache
     */
    private function getFromCache($key)
    {
        if (isset($this->cache[$key])) {
            $item = $this->cache[$key];
            if (time() < $item['expires']) {
                return $item['data'];
            }
            unset($this->cache[$key]);
        }
        return null;
    }

    /**
     * Set cache data
     */
    private function setCache($key, $data, $ttl = 300)
    {
        $this->cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
    }
}

// API Usage
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['username'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');

    $username = trim($_GET['username']);

    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username parameter is required']);
        exit;
    }

    // Clean username
    $username = ltrim($username, '@');
    $username = strtolower($username);

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9._]{1,30}$/', $username)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid username format']);
        exit;
    }

    try {
        $fetcher = new InstagramProfileFetcher();
        $profileData = $fetcher->getProfileDetails($username);

        if (isset($profileData['error'])) {
            http_response_code(404);
        }

        echo json_encode($profileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    }

} else {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Instagram Profile Fetcher API v2.0',
        'usage' => 'GET ?username=INSTAGRAM_USERNAME',
        'example' => $_SERVER['REQUEST_URI'] . '?username=instagram',
        'methods' => [
            'GraphQL API',
            'Web Scraping',
            'Mobile User Agent',
            'Meta Tag Extraction'
        ],
        'features' => [
            'Multiple fallback methods',
            'Rate limiting',
            'Caching',
            'Count parsing (K/M/B format)',
            'Error handling'
        ]
    ], JSON_PRETTY_PRINT);
}

?>
