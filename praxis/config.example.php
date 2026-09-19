<?php
/**
 * Praxis-Kalender - Konfiguration
 *
 * 1. Diese Datei nach  config.php  kopieren (umbenennen).
 * 2. Werte anpassen.
 * 3. config.php per FTP in denselben Ordner wie api.php hochladen.
 *
 * config.php NIEMALS in ein oeffentliches Git-Repository legen.
 */
return [

  'db' => [
    // 'sqlite' = kein Datenbank-Setup noetig, laeuft sofort auf IONOS Webspace.
    // 'mysql'  = empfohlen, wenn im IONOS Control Panel eine Datenbank vorhanden ist.
    'driver' => 'sqlite',

    // Nur fuer driver = 'sqlite':
    'sqlite_path' => __DIR__ . '/data/praxis.sqlite',

    // Nur fuer driver = 'mysql' (Werte aus IONOS > Hosting > Datenbanken):
    'host' => 'db1234567890.hosting-data.io',
    'name' => 'dbs1234567',
    'user' => 'dbu1234567',
    'pass' => 'GEHEIM',

    'prefix' => 'praxis_',
  ],

  'app' => [
    'name'     => 'Praxis-Kalender',
    'timezone' => 'Europe/Berlin',
    // Sitzungsdauer in Minuten (danach automatischer Logout)
    'session_minutes' => 240,
  ],
];
