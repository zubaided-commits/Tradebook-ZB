<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';
header('Location: ' . (angemeldet() ? 'ruf.php' : 'anmelden.php'));
