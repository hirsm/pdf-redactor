<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use App\Service\TranslationService;
use App\View;

class AppController {

    /**
     * Rendert die Hauptseite (Dashboard).
     * Nutzt die View-Klasse und übergibt Übersetzungsobjekte und Status-Variablen.
     */
    public function index(Request $request, Response $response) {
        // Übersetzungsservice initialisieren (ermittelt Sprache automatisch)
        $trans = new TranslationService();
        
        $basePath = $_ENV['APP_PROXY_PATH'] ?? '';
        
        // Prüfen, ob eine Authentifizierungsmethode aktiv ist.
        // Falls ja ($isLoggedIn = true), zeigt das Template den Logout-Button an.
        $authMethod = $_ENV['AUTH_METHOD'] ?? '';
        $isLoggedIn = ($authMethod === 'oidc' || $authMethod === 'shibboleth');

        // Footer Links vorbereiten
        $imprintUrl = $_ENV['IMPRINT_URL'] ?? '';
        $privacyUrl = $_ENV['PRIVACY_URL'] ?? '';

        // Wir nutzen unsere View-Helper Klasse statt str_replace
        return View::render($response, 'index.php', [
            't' => $trans,             // Das Translator-Objekt für das Template
            'basePath' => $basePath,
            'isLoggedIn' => $isLoggedIn,
            'imprintUrl' => $imprintUrl,
            'privacyUrl' => $privacyUrl
        ]);
    }

    /**
     * Upload Handler: Sendet PDF + Metadaten an den Python Microservice.
     * Antwortet mit dem geschwärzten PDF oder einem JSON-Fehler.
     */
    public function upload(Request $request, Response $response) {
        $uploadedFiles = $request->getUploadedFiles();

        // Check: Wurde eine Datei hochgeladen?
        if (empty($uploadedFiles['pdf'])) {
            $response->getBody()->write(json_encode(['error' => 'Keine Datei empfangen']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $pdfFile = $uploadedFiles['pdf'];
        $params = $request->getParsedBody();
        
        // JSON-String mit den Koordinaten der Schwärzungen
        $redactionsJson = $params['redactions'] ?? '[]';
        
        $pythonServiceUrl = $_ENV['PYTHON_SERVICE_URL'] ?? 'http://127.0.0.1:5000/redact';

        try {
            // Guzzle Client für HTTP Request an Python
            $client = new Client();
            
            // Wir streamen die Datei direkt weiter an Python (Speicherschonend)
            $res = $client->request('POST', $pythonServiceUrl, [
                'multipart' => [
                    [
                        'name'     => 'pdf',
                        'contents' => fopen($pdfFile->getStream()->getMetadata('uri'), 'r'),
                        'filename' => $pdfFile->getClientFilename()
                    ],
                    [
                        'name'     => 'redactions',
                        'contents' => $redactionsJson
                    ]
                ],
                'stream' => true, // Wichtig für große Dateien
                'connect_timeout' => 5,
                'timeout' => 300 // 5 Minuten Timeout für große PDFs
            ]);

            // Den Stream von Python direkt an den Browser durchleiten
            return $response
				->withBody($res->getBody())
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="SAFE_' . $pdfFile->getClientFilename() . '"');

        } catch (ClientException $e) {
            // Fehler vom Python Service (z.B. ungültiges PDF, Password protected etc.)
            $errorMsg = 'Fehler beim PDF-Dienst.';
            if ($e->hasResponse()) {
                $errorBody = json_decode($e->getResponse()->getBody(), true);
                if (isset($errorBody['error'])) {
                    $errorMsg = $errorBody['error'];
                }
            }
            $response->getBody()->write(json_encode(['error' => $errorMsg]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            // Sonstige Fehler (Python down, Netzwerk, etc.)
            $response->getBody()->write(json_encode(['error' => 'Interner Serverfehler: ' . $e->getMessage()]));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
