<?php

header("Content-Type: application/json");

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Get the POST body and decode JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['url']) || empty($data['url'])) {
    http_response_code(400);
    echo json_encode(["error" => "URL is required."]);
    exit;
}

$url = $data['url'];

// Function to scrape video details
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

try {
    $videoDetails = scrapeVideoDetails($url);

    if (!$videoDetails['videoDownloadUrl']) {
        http_response_code(404);
        echo json_encode(["error" => "Video not found or inaccessible."]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "data" => $videoDetails
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
