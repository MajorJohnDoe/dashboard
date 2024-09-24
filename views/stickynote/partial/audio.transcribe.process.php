<?php
// File: transcribe-audio.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fileName'])) {
    $uploadDir = __DIR__ . '/../../../user_upload_temp/';
    $fileName = $_POST['fileName'];
    $filePath = $uploadDir . $fileName;

    if (file_exists($filePath)) {
        $apiKey = $user->getChatGPTAPIKey();
        $url = 'https://api.openai.com/v1/audio/transcriptions';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFILE($filePath),
                'model' => 'whisper-1',
                'language' => 'sv'
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo json_encode([
                'success' => false,
                'message' => 'cURL Error: ' . $err
            ]);
        } else {
            $result = json_decode($response, true);
            if (isset($result['text'])) {
                echo json_encode([
                    'success' => true,
                    'transcription' => $result['text']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Transcription failed: ' . $response
                ]);
            }
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Audio file not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>