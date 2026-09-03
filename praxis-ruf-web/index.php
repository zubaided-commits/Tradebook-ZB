<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';

// Fuehrt zur richtigen Seite je nachdem, mit welcher Rolle zuletzt
// angemeldet wurde — wichtig, damit ein Lesezeichen auf "/" das
// Lautsprecher-Geraet nicht auf der Sprechzimmer-Seite laden laesst.
$rolle = angemeldeteRolle();
header('Location: ' . match ($rolle) {
    'lautsprecher' => 'lautsprecher.php',
    'arzt'         => 'ruf.php',
    default        => 'anmelden.php',
});
