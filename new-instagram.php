<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging for debugging in the live environment
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

function deleteOldFiles($directory)
{
    $files = glob($directory . '*'); // Get all files in the directory

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file); // Delete the file
        }
    }
}

function detectUrlType($url)
{
    if (strpos($url, 'instagram.com') !== false) {
        return 'instagram';
    } elseif (strpos($url, 'youtube.com/shorts') !== false || strpos($url, 'youtu.be') !== false || strpos($url, 'youtube.com/watch?v=') !== false) {
        return 'youtube';
    } else if (strpos($url, 'https://snapchat.com') !== false) {
        return 'snapchat';
    }
    return 'unknown';
}
// Call the function to delete old files in the 'downloads' folder
$downloadsDir = __DIR__ . '/../downloads/';
deleteOldFiles($downloadsDir);

function isValidYoutubeUrl($url)
{
    $regex = "/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/";
    return preg_match($regex, $url);
}

/**
 * Extract the video ID from YouTube URL
 */
function extractVideoId($url)
{
    $regex = "/(?:youtube\.com\/(?:shorts\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/";
    preg_match($regex, $url, $matches);
    return $matches[1] ?? null;  // Return the videoId if found, else null
}
/**
 * Fetches video details from RapidAPI
 */
function fetchYoutubeVideoDetails($videoId)
{
    if (empty($videoId)) {
        return ["error" => true, "message" => "Video ID is missing"];
    }
    $apiUrl = "https://youtube-media-downloader.p.rapidapi.com/v2/video/details?videoId=" . urlencode($videoId);
    $curl = curl_init();


    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: youtube-media-downloader.p.rapidapi.com",
            "x-rapidapi-key: 9da3e28b24mshe036d02fec5c810p112321jsn4f98d6601eb5" // RapidAPI Key
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return ["error" => true, "message" => "cURL Error: " . $err];
    }

    error_log("Raw API Response: " . $response);

    $decodedResponse = json_decode($response, true);

    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
        return ["error" => true, "message" => $decodedResponse['message']];
    }

    return [
        "error" => false,
        "data" => [
            "title" => $decodedResponse['title'] ?? "Unknown",
            "duration" => $decodedResponse['duration'] ?? "Unknown",
            "views" => $decodedResponse['views'] ?? "Unknown",
            "thumbnail" => $decodedResponse['thumbnails'][0]['url'] ?? "",
            "downloadLinks" => $decodedResponse['videos'] ?? []
        ]
    ];
}

// snap code 

function scrapeVideoDetails($videoUrl)
{

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $videoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable SSL host verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL peer verification
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36");

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("cURL error: " . curl_error($ch));
    }

    curl_close($ch);

    // Parse the HTML response using DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($response);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Extract metadata
    $videoElement = $xpath->query("//video/source");
    $thumbnailMeta = $xpath->query("//meta[@property='og:image']");

    $videoDownloadUrl = $videoElement->length > 0 ? $videoElement->item(0)->getAttribute('src') : null;
    $thumbnail = $thumbnailMeta->length > 0 ? $thumbnailMeta->item(0)->getAttribute('content') : null;

    return [
        "videoDownloadUrl" => $videoDownloadUrl,
        "thumbnail" => $thumbnail,
        "title" => $dom->getElementsByTagName("title")->item(0)->textContent ?? "No Title Found"
    ];
}


function proxyDownload($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.5',
        'Referer: https://www.instagram.com/'
    ]);

    $content = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    header('Content-Type: ' . $contentType);
    echo $content;
    exit;
}

// Function to encode request data for the POST request
function encodePostRequestData($shortcode, $after = null, $first = 50)
{
    $variables = [
        'shortcode' => $shortcode,
        'fetch_comment_count' => $first,
        'fetch_related_profile_media_count' => null,
        'parent_comment_count' => $first,
        'child_comment_count' => 3,
        'fetch_like_count' => 10,
        'fetch_tagged_user_count' => null,
        'fetch_preview_comment_count' => $first,
        'has_threaded_comments' => true,
        'hoisted_comment_id' => null,
        'hoisted_reply_id' => null,
    ];

    // Add pagination cursor if provided
    if ($after) {
        $variables['after'] = $after;
    }

    $requestData = [
        'av' => "0",
        '__d' => "www",
        '__user' => "0",
        '__a' => "1",
        '__req' => "3",
        '__hs' => "19624.HYP:instagram_web_pkg.2.1..0.0",
        'dpr' => "3",
        '__ccg' => "UNKNOWN",
        '__rev' => "1008824440",
        '__s' => "xf44ne:zhh75g:xr51e7",
        '__hsi' => "7282217488877343271",
        '__dyn' => "7xeUmwlEnwn8K2WnFw9-2i5U4e0yoW3q32360CEbo1nEhw2nVE4W0om78b87C0yE5ufz81s8hwGwQwoEcE7O2l0Fwqo31w9a9x-0z8-U2zxe2GewGwso88cobEaU2eUlwhEe87q7-0iK2S3qazo7u1xwIw8O321LwTwKG1pg661pwr86C1mwraCg",
        '__csr' => "gZ3yFmJkillQvV6ybimnG8AmhqujGbLADgjyEOWz49z9XDlAXBJpC7Wy-vQTSvUGWGh5u8KibG44dBiigrgjDxGjU0150Q0848azk48N09C02IR0go4SaR70r8owyg9pU0V23hwiA0LQczA48S0f-x-27o05NG0fkw",
        '__comet_req' => "7",
        'lsd' => "AVqbxe3J_YA",
        'jazoest' => "2957",
        '__spin_r' => "1008824440",
        '__spin_b' => "trunk",
        '__spin_t' => "1695523385",
        'fb_api_caller_class' => "RelayModern",
        'fb_api_req_friendly_name' => "PolarisPostActionLoadPostQueryQuery",
        'variables' => json_encode($variables),
        'server_timestamps' => "true",
        'doc_id' => "10015901848480474",
    ];

    return http_build_query($requestData);
}

// Function to make a single API request
function makeGraphQLRequest($shortcode, $after = null, $first = 50)
{
    $API_URL = "https://www.instagram.com/api/graphql";
    $requestData = encodePostRequestData($shortcode, $after, $first);

    $ch = curl_init($API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: */*",
        "Accept-Language: en-US,en;q=0.5",
        "Content-Type: application/x-www-form-urlencoded",
        "X-FB-Friendly-Name: PolarisPostActionLoadPostQueryQuery",
        "X-CSRFToken: RVDUooU5MYsBbS1CNN3CzVAuEP8oHB52",
        "X-IG-App-ID: 1217981644879628",
        "X-FB-LSD: AVqbxe3J_YA",
        "X-ASBD-ID: 129477",
        "User-Agent: Mozilla/5.0 (Linux; Android 11; SAMSUNG SM-G973U) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/14.2 Chrome/87.0.4280.141 Mobile Safari/537.36"
    ]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => "Curl error: $error"];
    }

    curl_close($ch);

    // Remove "for (;;);" if present in response
    if (strpos($response, "for (;;);") === 0) {
        $response = substr($response, 9);
    }

    $data = json_decode($response, true);
    error_log('data main' . print_r($data, true));
    return $data;
}
function fetchAllComments($shortcode, $maxComments = 100)
{
    $allComments = [];
    $hasNextPage = true;
    $after = null;
    $fetchedCount = 0;
    $maxRequestsPerBatch = 5; // Limit requests to avoid rate limiting
    $requestCount = 0;

    while ($hasNextPage && $fetchedCount < $maxComments && $requestCount < $maxRequestsPerBatch) {
        $remainingComments = $maxComments - $fetchedCount;
        $batchSize = min(50, $remainingComments); // Fetch up to 50 per request

        $data = makeGraphQLRequest($shortcode, $after, $batchSize);
        error_log('make graphql data request ' . ($requestCount + 1) . ': ' . print_r($data, true));

        if (isset($data['error'])) {
            error_log("Error fetching comments: " . $data['error']);
            break;
        }

        $mediaData = $data['data']['xdt_shortcode_media'] ?? null;
        if (!$mediaData) {
            error_log("No media data found in response");
            break;
        }

        // Try different comment edge structures
        $commentEdges = [];
        $pageInfo = null;

        // First request usually has edge_media_preview_comment
        // Subsequent paginated requests have edge_media_to_parent_comment
        if ($requestCount === 0 && isset($mediaData['edge_media_preview_comment'])) {
            $commentEdges = $mediaData['edge_media_preview_comment']['edges'] ?? [];
            $pageInfo = $mediaData['edge_media_preview_comment']['page_info'] ?? null;
            error_log("Using edge_media_preview_comment for first request");
        } elseif (isset($mediaData['edge_media_to_parent_comment'])) {
            $commentEdges = $mediaData['edge_media_to_parent_comment']['edges'] ?? [];
            $pageInfo = $mediaData['edge_media_to_parent_comment']['page_info'] ?? null;
            error_log("Using edge_media_to_parent_comment for request " . ($requestCount + 1));
        } elseif (isset($mediaData['edge_media_to_comment'])) {
            $commentEdges = $mediaData['edge_media_to_comment']['edges'] ?? [];
            $pageInfo = $mediaData['edge_media_to_comment']['page_info'] ?? null;
            error_log("Using edge_media_to_comment for request " . ($requestCount + 1));
        }

        error_log("Found " . count($commentEdges) . " comment edges in request " . ($requestCount + 1));

        // Process comments from current batch
        $batchComments = [];
        foreach ($commentEdges as $commentIndex => $comment) {
            if ($fetchedCount >= $maxComments)
                break;

            $commentData = [
                'id' => $comment['node']['id'] ?? '',
                'text' => $comment['node']['text'] ?? '',
                'created_at' => $comment['node']['created_at'] ?? '',
                'like_count' => $comment['node']['edge_liked_by']['count'] ?? 0,
                'user' => [
                    'username' => $comment['node']['owner']['username'] ?? '',
                    'profile_pic_url' => $comment['node']['owner']['profile_pic_url'] ?? '',
                    'profile_url' => 'https://www.instagram.com/' . ($comment['node']['owner']['username'] ?? ''),
                    'is_verified' => $comment['node']['owner']['is_verified'] ?? false
                ],
                'replies' => []
            ];

            // Add replies if they exist
            if (isset($comment['node']['edge_threaded_comments']['edges'])) {
                foreach ($comment['node']['edge_threaded_comments']['edges'] as $reply) {
                    $commentData['replies'][] = [
                        'id' => $reply['node']['id'] ?? '',
                        'text' => $reply['node']['text'] ?? '',
                        'created_at' => $reply['node']['created_at'] ?? '',
                        'like_count' => $reply['node']['edge_liked_by']['count'] ?? 0,
                        'user' => [
                            'username' => $reply['node']['owner']['username'] ?? '',
                            'profile_pic_url' => $reply['node']['owner']['profile_pic_url'] ?? '',
                            'profile_url' => 'https://www.instagram.com/' . ($reply['node']['owner']['username'] ?? ''),
                            'is_verified' => $reply['node']['owner']['is_verified'] ?? false
                        ]
                    ];
                }
            }

            $batchComments[] = $commentData;
            $fetchedCount++;
        }

        // Add batch comments to the main array
        $allComments = array_merge($allComments, $batchComments);

        error_log("Added " . count($batchComments) . " comments from batch " . ($requestCount + 1) . ". Total comments now: " . count($allComments));

        // Check if there are more pages
        $hasNextPage = $pageInfo['has_next_page'] ?? false;
        $after = $pageInfo['end_cursor'] ?? null;
        $requestCount++;

        error_log("Has next page: " . ($hasNextPage ? 'true' : 'false') . ", After cursor: " . ($after ?? 'null'));

        // Add a small delay to avoid rate limiting
        if ($hasNextPage && $requestCount < $maxRequestsPerBatch) {
            usleep(500000); // 0.5 second delay
        }

        error_log("Completed batch {$requestCount}: " . count($batchComments) . " new comments. Total so far: " . count($allComments));
    }

    error_log("Final result: " . count($allComments) . " total comments fetched");
    return $allComments;
}

// Function to fetch data from Instagram GraphQL API
// Function to fetch data from Instagram GraphQL API
function fetchFromGraphQL($postUrl, $maxComments = 100)
{
    preg_match('/\/(p|reel)\/([A-Za-z0-9_-]+)/', $postUrl, $matches);

    if (empty($matches[2])) {
        return ['error' => 'Invalid Instagram URL'];
    }

    $shortcode = $matches[2];

    // First request to get media data and initial comments
    $data = makeGraphQLRequest($shortcode);

    if (isset($data['error'])) {
        return $data;
    }

    $mediaData = $data['data']['xdt_shortcode_media'] ?? null;
    if (!$mediaData) {
        return ['error' => 'No media data found', 'response' => $data];
    }

    // Get total comments count from multiple possible sources
    $totalCommentsCount = 0;
    if (isset($mediaData['edge_media_to_parent_comment'])) {
        $totalCommentsCount = $mediaData['edge_media_to_parent_comment']['count'] ?? 0;
    } elseif (isset($mediaData['edge_media_to_comment'])) {
        $totalCommentsCount = $mediaData['edge_media_to_comment']['count'] ?? 0;
    } elseif (isset($mediaData['edge_media_preview_comment'])) {
        $totalCommentsCount = $mediaData['edge_media_preview_comment']['count'] ?? 0;
    }

    error_log("Total comments available: " . $totalCommentsCount . ", Requested max: " . $maxComments);

    // Fetch all comments with pagination
    $allComments = fetchAllComments($shortcode, $maxComments);

    error_log("Total comments in post: {$totalCommentsCount}, Actually fetched: " . count($allComments));

    // Handle carousel posts
    if ($mediaData['__typename'] === 'XDTGraphSidecar') {
        $mediaItems = $mediaData['edge_sidecar_to_children']['edges'] ?? [];
        $mediaUrls = array_map(function ($item) {
            $childMedia = $item['node'];
            return [
                'type' => $childMedia['is_video'] ? "video" : "image",
                'url' => $childMedia['is_video'] ? $childMedia['video_url'] : $childMedia['display_url'],
                'dimensions' => $childMedia['dimensions'] ?? null,
            ];
        }, $mediaItems);

        return [
            'type' => "carousel",
            'media' => $mediaUrls,
            'comments_count' => $totalCommentsCount,
            'comments_fetched' => count($allComments),
            'comments' => $allComments
        ];
    }

    // Check if it's a video
    if ($mediaData['is_video']) {
        return [
            'type' => "video",
            'url' => $mediaData['video_url'],
            'dimensions' => $mediaData['dimensions'] ?? null,
            'comments_count' => $totalCommentsCount,
            'comments_fetched' => count($allComments),
            'comments' => $allComments
        ];
    }

    // Handle single image posts
    if (!empty($mediaData['display_url'])) {
        return [
            'type' => "image",
            'url' => $mediaData['display_url'],
            'dimensions' => $mediaData['dimensions'] ?? null,
            'comments_count' => $totalCommentsCount,
            'comments_fetched' => count($allComments),
            'comments' => $allComments
        ];
    }

    return ['error' => 'Unknown media type.'];
}

function downloadFile($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.5',
        'Referer: https://www.instagram.com/'
    ]);
    $fileContent = curl_exec($ch);
    curl_close($ch);
    return $fileContent;
}

// Function to download files and create ZIP
function downloadAndCreateZip($mediaUrls)
{
    $zip = new ZipArchive();
    $downloadsDir = __DIR__ . '/../downloads/'; // Adjust the path to go to the desired directory

    $zipPath = $downloadsDir . uniqid('instagram_download_', true) . '.zip';

    if (!file_exists($downloadsDir)) {
        mkdir($downloadsDir, 0777, true);
    }
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        return ['error' => 'Unable to create ZIP file'];
    }

    foreach ($mediaUrls as $index => $media) {
        $fileContent = downloadFile($media['url']);
        if ($fileContent) {
            // Use a proper extension based on media type
            $extension = ($media['type'] === 'video') ? '.mp4' : '.jpg';
            $fileName = 'instagram_media_' . ($index + 1) . $extension;
            $zip->addFromString($fileName, $fileContent);
        }
    }

    $zip->close();

    if (!file_exists($zipPath) || filesize($zipPath) === 0) {
        return ['error' => 'ZIP file creation failed'];
    }
    // Assuming your server's URL is https://mediasave.kryzetech.com
    $baseUrl = "https://mediasave.kryzetech.com/";
    // $zipFileUrl = $baseUrl . 'downloads/' . basename($zipPath);
    $zipFileUrl = $baseUrl . 'downloads/' . basename($zipPath);
    // $zipFileUrl = $baseUrl . $zipPath;  // Construct the full URL to the ZIP file

    return ['zipFilePath' => $zipFileUrl];  // Only return 'zipFilePath' once
}

// Main handler function for API request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $postUrl = $input['postUrl'] ?? '';
    $maxComments = $input['maxComments'] ?? 100; // Default to 100 comments

    if (!$postUrl) {
        echo json_encode(['error' => 'URL is required']);
        exit;
    }

    $urlType = detectUrlType($postUrl);

    switch ($urlType) {
        case 'instagram':
            $mediaInfo = fetchFromGraphQL($postUrl, $maxComments);

            if (isset($mediaInfo['error'])) {
                echo json_encode($mediaInfo);
                exit;
            }

            if ($mediaInfo['type'] === 'carousel') {
                $zipResult = downloadAndCreateZip($mediaInfo['media']);
                echo json_encode([
                    'platform' => 'instagram',
                    'type' => 'carousel',
                    'zipFilePath' => $zipResult['zipFilePath'] ?? null,
                    'comments_count' => $mediaInfo['comments_count'] ?? 0,
                    'comments_fetched' => $mediaInfo['comments_fetched'] ?? 0,
                    'comments' => $mediaInfo['comments'] ?? [],
                    'error' => $zipResult['error'] ?? null
                ]);
            } else {
                echo json_encode([
                    'platform' => 'instagram',
                    'type' => $mediaInfo['type'],
                    'fileUrl' => $mediaInfo['url'],
                    'fileType' => $mediaInfo['type'] === "video" ? "mp4" : "jpg",
                    'dimensions' => $mediaInfo['dimensions'],
                    'comments_count' => $mediaInfo['comments_count'] ?? 0,
                    'comments_fetched' => $mediaInfo['comments_fetched'] ?? 0,
                    'comments' => $mediaInfo['comments'] ?? []
                ]);
            }
            break;
        // ... other cases remain the same
    }
}
?>