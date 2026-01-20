<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Auth\AuthService;
use App\Service\TranslationService;
use App\View;

class AuthController {
    
    public function loginPage(Request $request, Response $response) {
        $method = $_ENV['AUTH_METHOD'] ?? '';
        $basePath = $_ENV['APP_BASE_PATH'] ?? '';
        
        if ($method !== 'oidc' && $method !== 'shibboleth') {
            return $response->withHeader('Location', $basePath . '/')->withStatus(302);
        }

        $trans = new TranslationService();
        $loginUrl = ($method === 'shibboleth') ? $_ENV['SHIB_LOGIN_URL'] : "$basePath/auth/start";
        
        return View::render($response, 'login.php', [
            't' => $trans,
            'basePath' => $basePath,
            'loginUrl' => $loginUrl,
            'imprintUrl' => $_ENV['IMPRINT_URL'] ?? '',
            'privacyUrl' => $_ENV['PRIVACY_URL'] ?? ''
        ]);
    }

    // callback() ist Logik pur, das bleibt im Controller (Fehlerseite rendern wir aber via View)
    public function callback(Request $request, Response $response) {
        $auth = new AuthService();
        $trans = new TranslationService();
        $method = $_ENV['AUTH_METHOD'] ?? 'oidc';
        
        // ... (User Daten holen Logik wie bisher) ...
        $userData = []; $userId = '';
        if ($method === 'oidc') {
            $oidc = $auth->getOidcClient(); $oidc->authenticate(); 
            $userInfoObj = $oidc->requestUserInfo(); $userData = (array)$userInfoObj;
            $userId = $userData['email'] ?? $userData['sub'] ?? $userData['preferred_username'] ?? '';
        } else {
            if (empty($_SERVER['REMOTE_USER']) && empty($_SERVER['Shib-Session-ID'])) return $response->withStatus(403);
            $userData = $_SERVER; $userId = $_SERVER['REMOTE_USER'] ?? '';
        }

        // Berechtigung prüfen
        if (!$auth->isAuthorized($userData, $userId, $method)) {
            // Fehlerseite rendern
            return View::render($response->withStatus(403), 'error.php', [
                't' => $trans,
                'title' => $trans->trans('access_denied_title'),
                'message' => $trans->trans('access_denied_text', ['%user%' => htmlspecialchars($userId)]),
                'basePath' => $_ENV['APP_BASE_PATH'] ?? ''
            ]);
        }

        // ... (Token und Cookie Logik wie bisher - unverändert übernehmen) ...
        $token = $auth->createSession();
        $cookies = $request->getCookieParams();
        $rememberMe = isset($cookies['temp_remember_me']) && $cookies['temp_remember_me'] === '1';
        $expires = $rememberMe ? $auth->getNextExpiration() : time() + 86400;
        $basePath = $_ENV['APP_BASE_PATH'] ?? ''; $cookiePath = empty($basePath) ? '/' : $basePath;
        $cookieHeader = sprintf('auth_token=%s; Expires=%s; Path=%s; HttpOnly; SameSite=Lax', $token, gmdate('D, d M Y H:i:s T', $expires), $cookiePath);
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') $cookieHeader .= '; Secure';
        $deleteTempCookie = sprintf('temp_remember_me=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=%s; SameSite=Lax', $cookiePath);

        return $response->withAddedHeader('Set-Cookie', $cookieHeader)->withAddedHeader('Set-Cookie', $deleteTempCookie)->withHeader('Location', empty($basePath) ? '/' : $basePath)->withStatus(302);
    }
    
    // start() und logout() bleiben gleich...
    public function start(Request $request, Response $response) { $auth = new AuthService(); $auth->getOidcClient()->authenticate(); return $response; }
    public function logout(Request $request, Response $response) { 
        $cookies = $request->getCookieParams(); if (isset($cookies['auth_token'])) (new AuthService())->deleteSession($cookies['auth_token']);
        $basePath = $_ENV['APP_BASE_PATH'] ?? ''; $cookiePath = empty($basePath) ? '/' : $basePath;
        $cookieHeader = sprintf('auth_token=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=%s; HttpOnly; SameSite=Lax', $cookiePath);
        return $response->withHeader('Set-Cookie', $cookieHeader)->withHeader('Location', $basePath . '/login')->withStatus(302);
    }
}
