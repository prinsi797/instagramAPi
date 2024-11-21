<?php

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Only POST method is allowed"]);
    http_response_code(405);
    exit;
}
// Get input JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['postUrl']) || empty($data['postUrl'])) {
    echo json_encode(["error" => "The 'postUrl' field is required"]);
    http_response_code(400);
    exit;
}
$videoUrl = $data['postUrl'];
// Validate YouTube URL
if (!isValidYoutubeUrl($videoUrl)) {
    echo json_encode(["error" => "Invalid YouTube URL"]);
    http_response_code(400);
    exit;
}
// Extract the video ID from the URL
$videoId = extractVideoId($videoUrl);
// Call RapidAPI to get video details
$response = fetchYoutubeVideoDetails($videoId);

if ($response['error']) {
    echo json_encode(["error" => $response['message']]);
    http_response_code(500);
    exit;
}
// Return video details
echo json_encode($response['data']);
exit;
/**
 * Validates if the given URL is a YouTube URL (for regular and shorts)
 */
function isValidYoutubeUrl($url) {
    $regex = "/^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/";
    return preg_match($regex, $url);
}
/**
 * Extract the video ID from YouTube URL
 */
function extractVideoId($url) {
    $regex = "/(?:youtube\.com\/(?:shorts\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/";
    preg_match($regex, $url, $matches);
    return $matches[1] ?? null;  // Return the videoId if found, else null
}
/**
 * Fetches video details from RapidAPI
 */
function fetchYoutubeVideoDetails($videoId) {
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
            "x-rapidapi-key: 9da3e28b24mshe036d02fec5c810p112321jsn4f98d6601eb5" // Replace with your RapidAPI key
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return ["error" => true, "message" => "cURL Error: " . $err];
    }

    // Debug the raw response
    $decodedResponse = json_decode($response, true);

    // Debug decoded response
    if (isset($decodedResponse['status']) && $decodedResponse['status'] === 'error') {
        return ["error" => true, "message" => $decodedResponse['message']];
    }

    return [
        "error" => false,
        "data" => [
            "title" => $decodedResponse['title'] ?? "Unknown",
            "duration" => $decodedResponse['duration'] ?? "Unknown",
            "views" => $decodedResponse['views'] ?? "Unknown",
            "downloadLinks" => $decodedResponse['videos'] ?? []
        ]
    ];
}
