<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');


// Enable error logging for debugging in the live environment
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

function deleteOldFiles($directory) {
    $files = glob($directory . '*'); // Get all files in the directory

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file); // Delete the file
        }
    }
}

// Call the function to delete old files in the 'downloads' folder
$downloadsDir = __DIR__ . '/../downloads/';
deleteOldFiles($downloadsDir);

function proxyDownload($url) {
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
function encodePostRequestData($shortcode) {
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
        'variables' => json_encode([
            'shortcode' => $shortcode,
            'fetch_comment_count' => null,
            'fetch_related_profile_media_count' => null,
            'parent_comment_count' => null,
            'child_comment_count' => null,
            'fetch_like_count' => null,
            'fetch_tagged_user_count' => null,
            'fetch_preview_comment_count' => null,
            'has_threaded_comments' => false,
            'hoisted_comment_id' => null,
            'hoisted_reply_id' => null,
        ]),
        'server_timestamps' => "true",
        'doc_id' => "10015901848480474",
    ];

    return http_build_query($requestData);
}

// Function to fetch data from Instagram GraphQL API
function fetchFromGraphQL($postUrl) {
    preg_match('/\/(p|reel)\/([A-Za-z0-9_-]+)/', $postUrl, $matches);
    
    if (empty($matches[2])) {
        return ['error' => 'Invalid Instagram URL'];
    }

    $shortcode = $matches[2];
    $API_URL = "https://www.instagram.com/api/graphql";
    
    $requestData = encodePostRequestData($shortcode);
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Disable SSL Verification (temporary)
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
    
    $data = json_decode($response, true);

    // Remove "for (;;);" if present in response
    if (strpos($response, "for (;;);") === 0) {
        $response = substr($response, 9);
        $data = json_decode($response, true);
    }
    $mediaData = $data['data']['xdt_shortcode_media'] ?? null;
    if (!$mediaData) {
        return ['error' => 'No media data found', 'response' => $data];
    }
    
    // Handle carousel posts
    if ($mediaData['__typename'] === 'XDTGraphSidecar') {
        $mediaItems = $mediaData['edge_sidecar_to_children']['edges'] ?? [];
        $mediaUrls = array_map(function($item) {
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
        ];
    }

    // Check if it's a video
    if ($mediaData['is_video']) {
        return [
            'type' => "video",
            'url' => $mediaData['video_url'],
            'dimensions' => $mediaData['dimensions'] ?? null,
        ];
    }

    // Handle single image posts
    if (!empty($mediaData['display_url'])) {
        return [
            'type' => "image",
            'url' => $mediaData['display_url'],
            'dimensions' => $mediaData['dimensions'] ?? null,
        ];
    }

    return ['error' => 'Unknown media type.'];
}
function downloadFile($url) {
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
    // $fileContent = curl_exec($ch);
    // curl_close($ch);
    // return $fileContent;
}

// Function to download files and create ZIP
function downloadAndCreateZip($mediaUrls) {
    $zip = new ZipArchive();
    // $zipPath = 'api/downloads/' . uniqid('instagram_download_', true) . '.zip';

    // $downloadsDir = __DIR__ . '/downloads/';
    $downloadsDir = __DIR__ . '/../downloads/'; // Adjust the path to go to the desired directory

    $zipPath = $downloadsDir . uniqid('instagram_download_', true) . '.zip';

    if (!file_exists($downloadsDir)) {
        mkdir($downloadsDir, 0777, true);
    }
    // Create the downloads directory if it doesn't exist
    // if (!file_exists('api/downloads')) {
    //     mkdir('api/downloads', 0777, true);
    // }

    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        return ['error' => 'Unable to create ZIP file'];
    }

    // foreach ($mediaUrls as $media) {
    //     $fileContent = file_get_contents($media['url']);
    //     $fileName = basename($media['url']);
    //     $zip->addFromString($fileName, $fileContent);
    // }
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
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['proxy_url'])) {
    proxyDownload($_GET['proxy_url']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $postUrl = $input['postUrl'] ?? '';

    $mediaInfo = fetchFromGraphQL($postUrl);

    if (isset($mediaInfo['error'])) {
        echo json_encode($mediaInfo);
        exit;
    }
  if ($mediaInfo['type'] === 'carousel') {
    // Create a ZIP for carousel posts
    $zipResult = downloadAndCreateZip($mediaInfo['media']);
    
    // Make sure the response doesn't contain nested zipFilePath
    if (isset($zipResult['zipFilePath'])) {
        echo json_encode([
            'zipFilePath' => $zipResult['zipFilePath'],  // Directly access zipFilePath
        ]);
    } else {
        echo json_encode($zipResult);  // In case there's an error or missing zipFilePath
    }
    // if ($mediaInfo['type'] === 'carousel') {
    //     // Return multiple media URLs
    //     echo json_encode([
    //         'mediaUrls' => $mediaInfo['media'],  // Multiple media URLs in an array
    //     ]);
} else {
    echo json_encode([
        'fileUrl' => $mediaInfo['url'],
        'fileType' => $mediaInfo['type'] === "video" ? "mp4" : "jpg",
        'dimensions' => $mediaInfo['dimensions'],
    ]);
}


} else {
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
?>
