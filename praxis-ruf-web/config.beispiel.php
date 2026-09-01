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

    'stimme' => [
        'sprache'     => 'de-DE',
        'bevorzugt'   => ['Google Deutsch', 'Microsoft Katja', 'Microsoft Hedda', 'Anna'],
        'tempo'       => 0.88,        // niedriger = deutlicher
        'tonhoehe'    => 1.0,
        'lautstaerke' => 1.0,
    ],
];
