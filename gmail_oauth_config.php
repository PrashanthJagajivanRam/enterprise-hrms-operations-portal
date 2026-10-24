<?php
/**
 * Gmail API OAuth 2.0 Client & Token Refresh Manager
 * 
 * Demonstrates:
 * - Offline OAuth 2.0 authorization code flow
 * - Automatic refresh token resolution when access token expires
 * - Scoped permission control (GMAIL_SEND)
 */

// Vendor autoload path (Google API Client library)
$googleApiAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($googleApiAutoload)) {
    require_once $googleApiAutoload;
}

define('OAUTH_CREDENTIALS_PATH', __DIR__ . '/config/client_secret.json');
define('OAUTH_TOKEN_PATH', __DIR__ . '/config/token.json');

/**
 * Initializes and returns an authorized Google_Client instance.
 * Automatically refreshes expired access tokens using the refresh token.
 * 
 * @return Google_Client|array Returns Google_Client on success, error array on failure.
 */
function getAuthorizedGmailClient() {
    if (!class_exists('Google_Client')) {
        return ['status' => 'error', 'message' => 'Google API Client library not installed. Run composer install.'];
    }

    if (!file_exists(OAUTH_CREDENTIALS_PATH)) {
        return ['status' => 'error', 'message' => 'OAuth credentials file missing.'];
    }

    $client = new Google_Client();
    $client->setApplicationName('Enterprise HR Notification Service');
    $client->setScopes([Google_Service_Gmail::GMAIL_SEND]);
    $client->setAuthConfig(OAUTH_CREDENTIALS_PATH);
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load persisted tokens from storage
    if (file_exists(OAUTH_TOKEN_PATH)) {
        $accessToken = json_decode(file_get_contents(OAUTH_TOKEN_PATH), true);
        $client->setAccessToken($accessToken);
    } else {
        return ['status' => 'error', 'message' => 'Application has not yet been authorized. Complete initial OAuth handshake.'];
    }

    // Automatically refresh token if expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            if (!isset($newToken['error'])) {
                file_put_contents(OAUTH_TOKEN_PATH, json_encode($client->getAccessToken()));
            } else {
                return ['status' => 'error', 'message' => 'Token refresh failed: ' . $newToken['error']];
            }
        } else {
            return ['status' => 'error', 'message' => 'Refresh token missing. Re-authorization required.'];
        }
    }

    return $client;
}
?>
