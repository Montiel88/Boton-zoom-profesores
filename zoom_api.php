<?php
// zoom_api.php
class ZoomAPI {
    private $account_id;
    private $client_id;
    private $client_secret;

    public function __construct() {
        $this->account_id = getenv('ZOOM_ACCOUNT_ID');
        $this->client_id = getenv('ZOOM_CLIENT_ID');
        $this->client_secret = getenv('ZOOM_CLIENT_SECRET');
    }

    private function getAccessToken() {
        $auth = base64_encode($this->client_id . ':' . $this->client_secret);
        $url = "https://zoom.us/oauth/token?grant_type=account_credentials&account_id={$this->account_id}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            return null;
        }
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    public function getUsers() {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $url = "https://api.zoom.us/v2/users?page_size=300";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) return [];
        $data = json_decode($response, true);
        return $data['users'] ?? [];
    }

    public function getUserMeetings($userId) {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $url = "https://api.zoom.us/v2/users/{$userId}/meetings?page_size=100";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) return [];
        $data = json_decode($response, true);
        return $data['meetings'] ?? [];
    }

    public function getUserRecordings($userId) {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $url = "https://api.zoom.us/v2/users/{$userId}/recordings?page_size=100";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) return [];
        $data = json_decode($response, true);
        return $data['recordings'] ?? [];
    }

    public function testConnection() {
        $token = $this->getAccessToken();
        return $token !== null;
    }
}
?>