<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response;
use App\Auth\AuthService;

class AuthMiddleware {
    public function __invoke(Request $request, Handler $handler) {
        // 1. Check: Ist Auth überhaupt aktiviert?
        $method = strtolower($_ENV['AUTH_METHOD'] ?? '');
        
        // Wenn weder oidc noch shibboleth, dann "No Auth Mode" -> Durchwinken
        if ($method !== 'oidc' && $method !== 'shibboleth') {
            return $handler->handle($request);
        }

        // Ab hier: Authentifizierung ist aktiv -> Token prüfen
        $cookies = $request->getCookieParams();
        $token = $cookies['auth_token'] ?? null;
        $auth = new AuthService();

        if ($token && $auth->isValidSession($token)) {
            return $handler->handle($request);
        }

        // Fehlerbehandlung (wie bisher)
        $response = new Response();
        $basePath = $_ENV['APP_PROXY_PATH'] ?? '';

        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' || strpos($request->getUri()->getPath(), '/upload') !== false) {
            $response->getBody()->write(json_encode(['error' => 'Session abgelaufen. Bitte neu laden.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        return $response
            ->withHeader('Location', $basePath . '/login')
            ->withStatus(302);
    }
}