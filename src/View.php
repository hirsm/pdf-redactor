<?php
namespace App;

use Slim\Psr7\Response;

class View {
    public static function render(Response $response, string $template, array $data = []): Response {
        // 1. Pfad zur Datei
        $templatePath = __DIR__ . '/../templates/' . $template;
        
        if (!file_exists($templatePath)) {
            throw new \Exception("Template nicht gefunden: $templatePath");
        }

        // 2. Variablen entpacken (aus ['foo' => 'bar'] wird die Variable $foo = 'bar')
        extract($data);

        // 3. Output Buffering starten (fängt HTML ab, statt es sofort auszugeben)
        ob_start();
        
        // 4. Template einbinden (führt PHP-Code darin aus)
        require $templatePath;
        
        // 5. Inhalt holen und Buffer leeren
        $content = ob_get_clean();

        // 6. In die Response schreiben
        $response->getBody()->write($content);
        return $response;
    }
}
