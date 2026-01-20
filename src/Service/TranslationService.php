<?php
namespace App\Service;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Negotiation\LanguageNegotiator;

class TranslationService {
    private $translator;
    private $currentLocale;

    public function __construct() {
        $transPath = __DIR__ . '/../../translations';
        
        // 1. Verfügbare Sprachen automatisch ermitteln
        // Wir scannen den Ordner nach *.json Dateien
        $files = glob($transPath . '/*.json');
        $supportedLocales = [];
        
        foreach ($files as $file) {
            // Aus '/pfad/zu/fr.json' wird 'fr'
            $supportedLocales[] = basename($file, '.json');
        }

        // Falls (warum auch immer) keine Dateien da sind, Fallback auf Englisch
        if (empty($supportedLocales)) {
            $supportedLocales = ['en'];
        }

        // 2. Sprache ermitteln (Browser Anfrage vs. Verfügbare Dateien)
        $negotiator = new LanguageNegotiator();
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en';
        
        // Der Negotiator prüft nun gegen ALLE gefundenen JSON-Dateien.
        // Wenn der User 'fr-FR' will und wir 'fr.json' haben, matcht er das korrekt.
        $bestMatch = $negotiator->getBest($acceptLanguage, $supportedLocales);
        
        $matchedLocale = $bestMatch ? $bestMatch->getType() : 'en';

        // Wir schneiden auf 2 Zeichen ab (aus 'fr-CA' wird 'fr'), 
        // damit es zum Dateinamen passt.
        $this->currentLocale = substr($matchedLocale, 0, 2);

        // Sicherheits-Check: Haben wir diese Datei wirklich? (Falls Negotiator komisch matcht)
        if (!in_array($this->currentLocale, $supportedLocales)) {
            $this->currentLocale = 'en';
        }

        // 3. Translator initialisieren
        $this->translator = new Translator($this->currentLocale);
        $this->translator->addLoader('json', new JsonFileLoader());
        
        // 4. Alle gefundenen Sprachdateien laden
        foreach ($supportedLocales as $locale) {
            $this->translator->addResource('json', $transPath . '/' . $locale . '.json', $locale);
        }
        
        // Fallback immer auf Englisch
        $this->translator->setFallbackLocales(['en']);
    }

    public function trans(string $id, array $parameters = []): string {
        return $this->translator->trans($id, $parameters);
    }

    public function getLocale(): string {
        return $this->currentLocale;
    }

    public function getJsTranslations(): string {
        $catalogue = $this->translator->getCatalogue($this->currentLocale);
        $all = $catalogue->all('messages');
        
        $jsMessages = [];
        foreach ($all as $key => $val) {
            if (strpos($key, 'js_') === 0) {
                $jsMessages[$key] = $val;
            }
        }
        return json_encode($jsMessages);
    }
}