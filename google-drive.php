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
    $fileExtension = pathinfo(strtolower($filename), PATHINFO_EXTENSION);
    $mimeType = 'application/' . $fileExtension;
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
////////// upload files
if (isset($_POST['action']) && $_POST['action'] === 'upload_files') {

    $accessToken = $_SESSION['access_token'];

    $folderId = isset($_POST['folderId']) && !empty($_POST['folderId']) ? $_POST['folderId'] : 'root';

    if (isset($_FILES['files']) && is_array($_FILES['files']['name'])) {
        handleMultipleFileUpload($accessToken, $_FILES['files'], $folderId);
    } else {
        echo json_encode(['error' => 'No files uploaded or invalid action.']);
    }
}

function handleMultipleFileUpload($accessToken, $files, $folderId) {
    // Loop through each uploaded file
    foreach ($files['name'] as $key => $fileName) {
        $fileTmpPath = $files['tmp_name'][$key];
        $fileType = $files['type'][$key];
        $fileContents = file_get_contents($fileTmpPath);

        $fileMetadata = [
            'name' => $fileName,
            'parents' => [$folderId]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: multipart/related; boundary=foo_bar_baz',
            ],
            CURLOPT_POSTFIELDS => buildMultipartRequest($fileMetadata, $fileContents, $fileType),
        ]);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            echo json_encode(['error' => curl_error($curl)]);
        } else {
            echo $response;
        }

        curl_close($curl);
    }
}

function buildMultipartRequest($fileMetadata, $fileContents, $fileType) {
    $boundary = 'foo_bar_baz';
    $delimiter = '--' . $boundary;

    $metadata = json_encode($fileMetadata);
    $metadataPart = $delimiter . "\r\n" .
                    'Content-Type: application/json; charset=UTF-8' . "\r\n\r\n" .
                    $metadata . "\r\n";

    $filePart = $delimiter . "\r\n" .
                'Content-Type: ' . $fileType . "\r\n" .
                'Content-Transfer-Encoding: base64' . "\r\n\r\n" .
                base64_encode($fileContents) . "\r\n";

    return $metadataPart . $filePart . $delimiter . '--';
}
// Delete folder or file 
if(isset($_POST['action']) && $_POST['action'] === 'delete_file_folder'){

   $accessToken = $_SESSION['access_token'];

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://www.googleapis.com/drive/v3/files/'.$_POST["delete_id"],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'DELETE',
  CURLOPT_HTTPHEADER => array(
    'Authorization: Bearer ' . $accessToken,
  ),
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); 

curl_close($curl);

if ($http_code == 204) {

    echo json_encode(['success' => 'File/Folder deleted successfully']);
} else {

    echo json_encode(['error' => 'Failed to delete file/folder', 'http_code' => $http_code, 'response' => $response]);
}
}

// Function to rename the folder in Google Drive
function renameFolder($accessToken, $folderId, $newName) {

    $url = "https://www.googleapis.com/drive/v3/files/{$folderId}";

    $data = json_encode([
        'name' => $newName  
    ]);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/json"  
        ],
        CURLOPT_CUSTOMREQUEST => 'PATCH',  
        CURLOPT_POSTFIELDS => $data 
    ]);

    $response = curl_exec($curl);
    if (curl_errno($curl)) {
        echo json_encode(['error' => curl_error($curl)]);
        curl_close($curl);
        return;
    }

    curl_close($curl);

    if ($response) {
     
        $responseData = json_decode($response, true);
        return $responseData;
    } else {
        return ['error' => 'Failed to rename folder'];
    }
}
// rename folder
if ($_POST['action'] == 'rename_folder') {
    $accessToken = $_SESSION['access_token'];
    $folderId = $_POST['folderId'];
    $newName = $_POST['newName'];

    $response = renameFolder($accessToken, $folderId, $newName);


    echo json_encode($response);
}
/////////////////////////

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
