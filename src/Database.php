<?php
namespace App;

use PDO;

class Database {
    private $pdo;

    public function __construct() {
        // Pfad korrekt auflösen (Root-Verzeichnis)
        $dbPath = __DIR__ . '/../' . $_ENV['DB_PATH'];
        $dir = dirname($dbPath);
        
        // 1. Ordner erstellen, falls nicht vorhanden (mit 0770 -> User/Group voll, Others nix)
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        // 2. Sicherheits-Check: Datei vor dem PDO-Zugriff anlegen und Rechte setzen
        if (!file_exists($dbPath)) {
            // Leere Datei erstellen
            touch($dbPath);
            // WICHTIG: Rechte auf 600 setzen (rw-------)
            // Nur der User (www-data) darf lesen und schreiben. Kein Gruppen-Zugriff.
            chmod($dbPath, 0600);
        }

        // 3. Verbindung aufbauen
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_TIMEOUT, 5); 

        // 4. Performance Optimierungen (WAL Mode)
        // Hinweis: Im WAL-Mode entstehen temporär .wal und .shm Dateien.
        // SQLite kümmert sich meist selbst um deren Rechte, aber da der Haupt-File 600 ist,
        // ist das Sicherheitsniveau hoch.
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        $this->pdo->exec('PRAGMA synchronous = NORMAL;');

        // Tabelle erstellen
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            token_hash TEXT PRIMARY KEY,
            expires_at INTEGER NOT NULL
        )");
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }
}