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

    // Haelt zweierlei wach:
    //
    // 1. Den Lautsprecher. Viele Funklautsprecher (JBL & Co.) schalten sich
    //    nach 10-20 Minuten Stille selbst ab; dann wird der naechste Aufruf
    //    nicht mehr gehoert.
    // 2. Die Seite selbst. Ist das Browserfenster minimiert oder verdeckt,
    //    bremst der Browser nach wenigen Minuten alle Zeitgeber auf einen
    //    Aufruf pro Minute herunter — der Aufruf kaeme zu spaet oder gar
    //    nicht. Seiten, die Ton ausgeben, sind davon ausgenommen.
    //
    // Dafuer laeuft ein sehr leiser, tiefer Ton dauerhaft in Schleife, im
    // Raum nicht zu hoeren. Nur abschalten, wenn das Fenster immer sichtbar
    // bleibt UND der Lautsprecher am Kabel haengt.
    'wachton'         => true,
    'wachtonSekunden' => 60,     // Abstand zwischen zwei Wecktoenen
    'wachtonHertz'    => 60,     // tief genug, um nicht zu stoeren
    'wachtonStaerke'  => 0.02,   // 0.01-0.05; hoeher nur, wenn es nicht reicht

    // Der Gong: ein weicher Zweiklang, kein Signalton. Danach klingt er aus,
    // bevor der Name faellt — wer im Wartezimmer sitzt, wird erst aufmerksam
    // und hoert den Namen dann von Anfang an.
    'gongStaerke'            => 0.16,  // 0.10 dezenter, 0.25 deutlicher
    'gongPauseSekunden'      => 2,     // zwischen Gong und Name
    'wiederholPauseSekunden' => 2,     // Atempause vor der Wiederholung

    // Welche Stimmen zur Verfuegung stehen, entscheidet allein das Geraet:
    // Eine Webseite kann keine Stimme mitbringen oder nachladen. Hier steht
    // nur, welche bevorzugt genommen wird, wenn es sie gibt — von der besten
    // zur einfachsten. Am Lautsprecher laesst sich zusaetzlich von Hand
    // auswaehlen und sofort vorhoeren.
    //
    // Auf iPad und iPhone lohnt sich der Weg
    //   Einstellungen > Bedienungshilfen > Gesprochene Inhalte > Stimmen >
    //   Deutsch
    // und dort eine Stimme in "Premium" laden. Das ist der groesste
    // hoerbare Unterschied und kostet nichts.
    // Die Ansage soll ruhig, klar und eine Spur langsamer als ein Gespraech
    // klingen — angenehm auch beim zwanzigsten Mal am Tag.
    'stimme' => [
        'sprache'     => 'de-DE',
        'bevorzugt'   => [
            'Anna (Premium)', 'Anna (Erweitert)', 'Anna (Enhanced)',
            'Helena', 'Katja', 'Anna', 'Petra', 'Marlene', 'Martina',
            'Google Deutsch', 'Hedda',
        ],
        'tempo'       => 0.82,        // ruhig; 0.75 sehr langsam, 0.95 zuegig
        'tonhoehe'    => 1.04,        // 0.95 dunkler, 1.10 heller
        'lautstaerke' => 1.0,
    ],

    // Eigene Aussprache: links der Name, rechts die Schreibweise, die eine
    // deutsche Stimme richtig vorliest. Ein mitgeliefertes Woerterbuch deckt
    // die haeufigen afghanischen und persischen Namen bereits ab (siehe
    // aussprache.php); hier kommen nur die Namen dazu, die noch fehlen oder
    // anders klingen sollen.
    //
    //   'Zubaida' => 'Subaida'     (deutsches Z klaenge "Ts")
    //   'Najib'   => 'Nadschib'    (deutsches J klaenge "J" wie in "ja")
    //   'Shirin'  => 'Schirin'
    'aussprache' => [],
];
