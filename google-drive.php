<?php
session_start();
include('credentials.php');

// Function to get access token using the refresh token
function refreshAccessToken($refreshToken) {
    $postData = [
        'refresh_token' => $refreshToken, 
        'grant_type' => 'refresh_token',
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
    ]; 

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => REFRESH_TOKEN_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['error' => curl_error($ch)]);
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    $data = json_decode($response, true);

    return isset($data['access_token']) ? $data : false;
}


// Function to get files and folders from Google Drive
if(isset($_POST['action']) && $_POST['action'] === 'load_files'){
    function getFiles($folderId = 'root', $accessToken) {
        $url = DRIVE_API_URL;

    $params = [
        'q' => "'{$folderId}' in parents and trashed = false",
        'fields' => 'nextPageToken, files(id, name, mimeType, parents)',
    ];

    $query = http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return json_encode(['error' => curl_error($ch)]);
    }

    curl_close($ch);
    $data = json_decode($response, true);
    
    if (isset($data['files'])) {

        $folders = [];
        $files = [];

        foreach ($data['files'] as $file) {
            if ($file['mimeType'] === 'application/vnd.google-apps.folder') {
                $folders[] = $file;
            } else {
                $files[] = $file;
            }
        }
        usort($folders, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        usort($files, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $sortedFiles = array_merge($folders, $files);
        $data['files'] = $sortedFiles;
    }

    return json_encode($data);
}
}
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $foldername = $_POST['name'];
    $folderId = $_POST['folderId'];
    
    $accessToken = $_SESSION['access_token'];
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $foldername,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$folderId], // Parent folder ID
        ]),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"
        ),
    ));
    
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
        echo json_encode(['error' => curl_error($curl)]);
    } else {
        echo $response;
    }
    
    curl_close($curl);
}

if(isset($_POST['action']) && $_POST['action'] === 'create_file'){

$filename = $_POST['name'];  
$folderId = $_POST['folderId'];  // Google Drive folder ID where the file should be created

// Define the mime type based on file extension
$fileExtension = pathinfo(strtolower($filename), PATHINFO_EXTENSION);
$mimeType = 'application/' . $fileExtension;

// Google Drive API URL for file creation
$createFileUrl = 'https://www.googleapis.com/drive/v3/files';

$metadata = json_encode([
    'name' => $filename,   
    'mimeType' => $mimeType,
    'parents' => [$folderId],
]);

// Get the access token from the session
$accessToken = $_SESSION['access_token']; 

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => $createFileUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $metadata,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',  
    ),
));

$response = curl_exec($curl);

if (curl_errno($curl)) {
    echo 'cURL Error: ' . curl_error($curl);
} else {
    echo $response;
}

curl_close($curl);
}




// Check for valid access token in session
if (!isset($_SESSION['access_token']) || !isset($_SESSION['access_token_expiry']) || time() > $_SESSION['access_token_expiry']) {
    // Refresh the access token if expired or not present
    $accessTokenData = refreshAccessToken(REFRESH_TOKEN);
    if ($accessTokenData && isset($accessTokenData['access_token'])) {
        $_SESSION['access_token'] = $accessTokenData['access_token'];
        $_SESSION['access_token_expiry'] = time() + $accessTokenData['expires_in'];
    } else {
        echo json_encode(['error' => 'Unable to refresh access token']);
        exit;
    }
}

// Get the access token from the session
$accessToken = $_SESSION['access_token'];

// Handle folder requests
if (isset($_POST['folderId']) && $_POST['action'] === 'load_files') {
    $folderId = $_POST['folderId'];
    echo getFiles($folderId, $accessToken);
}
?>
