<?php
require_once 'config.php';

class ZoomAPI {
    private $access_token = null;
    
    public function getAccessToken() {
        if ($this->access_token) return $this->access_token;
        
        $credentials = base64_encode(ZOOM_CLIENT_ID . ':' . ZOOM_CLIENT_SECRET);
        $postData = http_build_query([
            'grant_type' => 'account_credentials',
            'account_id' => ZOOM_ACCOUNT_ID
        ]);
        
        $ch = curl_init('https://zoom.us/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return null;
        
        $data = json_decode($response, true);
        $this->access_token = $data['access_token'] ?? null;
        return $this->access_token;
    }
    
    public function getUsers() {
        $token = $this->getAccessToken();
        if (!$token) return [];
        
        $ch = curl_init('https://api.zoom.us/v2/users?page_size=300');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['users'] ?? [];
    }
    
    public function getUserMeetings($userId) {
        $token = $this->getAccessToken();
        if (!$token) return [];
        
        $ch = curl_init("https://api.zoom.us/v2/users/$userId/meetings?page_size=50");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['meetings'] ?? [];
    }
    
    public function getUserRecordings($userId) {
        $token = $this->getAccessToken();
        if (!$token) return [];
        
        $ch = curl_init("https://api.zoom.us/v2/users/$userId/recordings?page_size=50");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data['recordings'] ?? [];
    }
}
?>