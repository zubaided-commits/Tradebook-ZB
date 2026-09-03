<?php
/**
 * Diese Datei nach  config.php  kopieren und anpassen.
 * config.php gehoert NICHT ins Repository — sie enthaelt das Passwort.
 */

return [
    'praxis'   => 'Name der Praxis',

    // Gemeinsames Passwort fuer beide Aerztinnen und das Lautsprecher-Geraet.
    // Bitte aendern. Mindestens 12 Zeichen.
    'passwort' => 'BITTE-AENDERN',

    'sprechzimmer' => [
        ['kurz' => '1', 'name' => 'Sprechzimmer 1'],
        ['kurz' => '2', 'name' => 'Sprechzimmer 2'],
    ],

    'wartezimmer' => [
        ['id' => 'wz1', 'name' => 'Wartezimmer 1'],
        ['id' => 'wz2', 'name' => 'Wartezimmer 2'],
    ],

    'nurNachname'          => false,  // true = nur der Nachname wird gesagt
    'wiederholen'          => true,   // Ansage zweimal
    'gong'                 => true,
    'anzeigeDauerSekunden' => 45,     // solange gilt ein Aufruf als aktuell
    'verlaufDauerMinuten'  => 10,     // danach verschwinden die Namen von selbst

    // Haelt Bluetooth-Lautsprecher wach. Viele Funklautsprecher (JBL & Co.)
    // schalten sich nach 10-20 Minuten Stille selbst ab; dann wird der naechste
    // Aufruf nicht mehr gehoert. Ein sehr leiser, tiefer Ton alle 60 Sekunden
    // verhindert das und haelt zugleich die Funkstrecke wach, damit die ersten
    // Silben nicht abgeschnitten werden.
    // Bei einem Lautsprecher am Kabel wird das nicht gebraucht: false.
    'wachton'         => true,
    'wachtonSekunden' => 60,     // Abstand zwischen zwei Wecktoenen
    'wachtonHertz'    => 60,     // tief genug, um nicht zu stoeren
    'wachtonStaerke'  => 0.02,   // 0.01-0.05; hoeher nur, wenn es nicht reicht

    'stimme' => [
        'sprache'     => 'de-DE',
        'bevorzugt'   => ['Google Deutsch', 'Microsoft Katja', 'Microsoft Hedda', 'Anna'],
        'tempo'       => 0.88,        // niedriger = deutlicher
        'tonhoehe'    => 1.0,
        'lautstaerke' => 1.0,
    ],
];
