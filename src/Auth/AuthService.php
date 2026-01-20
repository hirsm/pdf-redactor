<?php
namespace App\Auth;

use App\Database;
use DateTime;
use Jumbojett\OpenIDConnectClient;

class AuthService {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getPdo();
    }

    // ... (Methoden getNextExpiration, createSession, isValidSession, deleteSession, cleanup bleiben UNVERÄNDERT) ...
    // Ich füge sie hier der Übersicht halber nicht erneut ein, da sie gleich bleiben.

    public function getNextExpiration(): int {
        $now = new DateTime();
        $year = (int)$now->format('Y');
        $dates = [ new DateTime("$year-03-31 23:59:59"), new DateTime("$year-09-30 23:59:59"), new DateTime(($year + 1) . "-03-31 23:59:59") ];
        foreach ($dates as $date) { if ($date > $now) return $date->getTimestamp(); }
        return time() + 86400;
    }

    public function createSession(): string {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = $this->getNextExpiration();
        $stmt = $this->db->prepare("INSERT INTO sessions (token_hash, expires_at) VALUES (?, ?)");
        $stmt->execute([$hash, $expires]);
        return $token;
    }

    public function isValidSession(string $token): bool {
        if (rand(1, 100) <= 5) $this->cleanup();
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("SELECT expires_at FROM sessions WHERE token_hash = ?");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if (time() > $row['expires_at']) { $this->deleteSession($token); return false; }
        return true;
    }

    public function deleteSession(string $token) {
        $hash = hash('sha256', $token);
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE token_hash = ?");
        $stmt->execute([$hash]);
    }
    
    private function cleanup() {
        $now = time();
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expires_at < ?");
        $stmt->execute([$now]);
    }

    // --- HIER SIND DIE ÄNDERUNGEN ---

    public function getOidcClient(): OpenIDConnectClient {
        $idpUrl = rtrim($_ENV['OIDC_IDP'], '/');
        $oidc = new OpenIDConnectClient(
            $idpUrl,
            $_ENV['OIDC_CLIENT_ID'],
            $_ENV['OIDC_CLIENT_SECRET']
        );

        // 1. Standard-Scopes für User-Identifikation immer setzen
        $scopes = ['openid', 'profile', 'email'];

        // 2. Gruppen-Scopes aus der .env holen und hinzufügen
        $groupScopesStr = $_ENV['OIDC_GROUP_SCOPES'] ?? '';
        if (!empty($groupScopesStr)) {
            $extraScopes = array_filter(array_map('trim', explode(',', $groupScopesStr)));
            $scopes = array_merge($scopes, $extraScopes);
        }
        
        // Alle Scopes explizit anfragen
        $oidc->addScope($scopes);

        // Config & Redirect (Wie gehabt)
        $base = $_ENV['APP_BASE_PATH'] ?? '';
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'];
        
        $oidc->setRedirectURL("$scheme://$host$base/auth/callback");
        
        // SSL
        $verifySsl = filter_var($_ENV['OIDC_VERIFY_SSL'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (!$verifySsl) {
            $oidc->setVerifyHost(false);
            $oidc->setVerifyPeer(false);
        }

        return $oidc;
    }

    public function isAuthorized(array $userData, string $userId, string $method): bool {
        // 1. Config laden
        $allowedUsersStr = $_ENV['AUTH_ALLOWED_USERS'] ?? '';
        $allowedGroupsStr = $_ENV['AUTH_ALLOWED_GROUPS'] ?? '';

        $allowedUsers = array_filter(array_map('trim', explode(',', $allowedUsersStr)));
        $allowedGroups = array_filter(array_map('trim', explode(',', $allowedGroupsStr)));

        // 2. Keine Einschränkungen? -> Jeder darf rein
        if (empty($allowedUsers) && empty($allowedGroups)) {
            return true;
        }

        // 3. User-Check
        if (in_array($userId, $allowedUsers)) {
            return true;
        }

        // 4. Gruppen-Check
        if (empty($allowedGroups)) {
            return false;
        }

        $userGroups = [];

        if ($method === 'shibboleth') {
            // Shibboleth: $_SERVER Variable nutzen
            $attrKeys = array_filter(array_map('trim', explode(',', $_ENV['SHIB_GROUP_ATTRS'] ?? '')));
            foreach ($attrKeys as $key) {
                if (!empty($userData[$key])) {
                    $parts = explode(';', $userData[$key]);
                    foreach ($parts as $p) $userGroups[] = trim($p);
                }
            }
        } elseif ($method === 'oidc') {
            // OIDC: Wir suchen in denselben Feldern, die wir auch als Scope angefragt haben!
            // Wir nutzen also dieselbe Variable OIDC_GROUP_SCOPES
            $claimKeys = array_filter(array_map('trim', explode(',', $_ENV['OIDC_GROUP_SCOPES'] ?? '')));
            
            foreach ($claimKeys as $key) {
                if (isset($userData[$key])) {
                    $val = $userData[$key];
                    if (is_array($val)) {
                        $userGroups = array_merge($userGroups, $val);
                    } elseif (is_string($val)) {
                        $userGroups[] = trim($val);
                    }
                }
            }
        }

        // Abgleich
        foreach ($userGroups as $g) {
            if (in_array($g, $allowedGroups)) {
                return true;
            }
        }

        return false;
    }
}