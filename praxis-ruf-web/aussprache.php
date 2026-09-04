<?php
declare(strict_types=1);
/**
 * Aussprache-Hilfe fuer die Sprachausgabe.
 *
 * Eine deutsche Stimme liest deutsche Rechtschreibung. Bei Namen aus dem
 * Persischen, Dari, Paschto und Arabischen geht das schief, weil dieselben
 * Buchstaben im Deutschen anders klingen:
 *
 *   Z  klingt deutsch wie "ts"  -> Zubaida wuerde "Tsubaida"
 *   J  klingt deutsch wie "j"   -> Najib   wuerde "Na-jib" statt "Nadschib"
 *   SH kennt das Deutsche nicht -> Shahnaz wuerde "S-hahnas"
 *   Q  steht deutsch nur vor U  -> Qayoum  wird zerlegt
 *
 * Geloest wird das, indem der Name fuer die Ansage in deutsche
 * Rechtschreibung umgeschrieben wird: "Zubaida" wird der Stimme als
 * "Subaida" gegeben, "Najib" als "Nadschib". Angezeigt und gespeichert
 * bleibt immer der echte Name — umgeschrieben wird nur, was gesprochen wird.
 *
 * Bewusst woerterbuchgestuetzt statt regelgestuetzt: Regeln wie "Z wird S"
 * wuerden deutsche Namen zerstoeren (aus Zimmermann wuerde "Simmermann").
 * Automatisch umgeschrieben wird nur, was es im Deutschen ueberhaupt nicht
 * gibt und deshalb gefahrlos ist. Alles andere steht im Woerterbuch, und die
 * Praxis kann es in config.php um ihre eigenen Namen ergaenzen.
 */

/**
 * Namen, die eine deutsche Stimme sonst falsch liest.
 * Links der Name so, wie er geschrieben wird; rechts, wie er der Stimme
 * gegeben wird. Der Schluessel wird klein geschrieben verglichen.
 */
function ausspracheWoerterbuch(): array
{
    return [
        /* --- weibliche Vornamen --- */
        'zubaida' => 'Subaida',      'zubaidah' => 'Subaida',
        'farzana' => 'Farsana',      'shahnaz' => 'Schahnas',
        'zarmina' => 'Sarmina',      'zahra' => 'Sahra',
        'zainab' => 'Sainab',        'zaynab' => 'Sainab',
        'najiba' => 'Nadschiba',     'khadija' => 'Chadidscha',
        'aisha' => 'Aischa',         'ayesha' => 'Aischa',
        'shakila' => 'Schakila',     'shukria' => 'Schukria',
        'palwasha' => 'Palwascha',   'benafsha' => 'Benafscha',
        'freshta' => 'Freschta',     'roya' => 'Roja',
        'soraya' => 'Soraja',        'marjan' => 'Mardschan',
        'yalda' => 'Jalda',          'shaima' => 'Schaima',
        'shabnam' => 'Schabnam',     'shirin' => 'Schirin',
        'zohra' => 'Sohra',          'zarghona' => 'Sargona',
        'jamila' => 'Dschamila',     'khatera' => 'Chatera',
        'nazia' => 'Nasia',          'zakia' => 'Sakia',
        'shazia' => 'Schasia',       'yasmin' => 'Jasmin',
        'zarina' => 'Sarina',        'shafiqa' => 'Schafika',
        'razia' => 'Rasia',          'zarmeena' => 'Sarmina',
        'khalida' => 'Chalida',      'shamsia' => 'Schamsia',
        'zainabi' => 'Sainabi',      'gulalai' => 'Gulalai',
        'malalai' => 'Malalai',      'homaira' => 'Homaira',

        /* --- maennliche Vornamen --- */
        'ahmad' => 'Achmad',         'ahmed' => 'Achmed',
        'najib' => 'Nadschib',       'najibullah' => 'Nadschibulla',
        'nasrullah' => 'Nasrulla',   'wahidullah' => 'Wahidulla',
        'rahmatullah' => 'Rachmatulla', 'zabihullah' => 'Sabihulla',
        'samiullah' => 'Samiulla',   'rohullah' => 'Rohulla',
        'ziaullah' => 'Siaulla',     'hedayatullah' => 'Hedajatulla',
        'khalid' => 'Chalid',        'khan' => 'Chan',
        'massoud' => 'Massud',       'masoud' => 'Massud',
        'mahmood' => 'Mahmud',       'mahmoud' => 'Mahmud',
        'qayoum' => 'Kajum',         'qayyum' => 'Kajum',
        'yaqoob' => 'Jakub',         'yaqub' => 'Jakub',
        'yousuf' => 'Jusuf',         'yusuf' => 'Jusuf',
        'younus' => 'Junus',         'yunus' => 'Junus',
        'yasin' => 'Jasin',          'yaser' => 'Jasser',
        'jamshid' => 'Dschamschid',  'javed' => 'Dschawed',
        'jawed' => 'Dschawed',       'jalil' => 'Dschalil',
        'ajmal' => 'Adschmal',       'sanjar' => 'Sandschar',
        'sharif' => 'Scharif',       'shafiq' => 'Schafik',
        'rashid' => 'Raschid',       'sher' => 'Schir',
        'shir' => 'Schir',           'shah' => 'Schah',
        'hashmat' => 'Haschmat',     'shakir' => 'Schakir',
        'shoaib' => 'Schoaib',       'shamsuddin' => 'Schamsuddin',
        'zaher' => 'Saher',          'zia' => 'Sia',
        'zamir' => 'Samir',          'zaman' => 'Saman',
        'zarif' => 'Sarif',          'zakir' => 'Sakir',
        'aziz' => 'Asis',            'parwiz' => 'Parwis',
        'nazir' => 'Nasir',          'fazal' => 'Fasal',
        'qasim' => 'Kasim',          'rafiq' => 'Rafik',
        'sadiq' => 'Sadik',          'tariq' => 'Tarik',
        'qadir' => 'Kadir',          'qais' => 'Kais',
        'hameed' => 'Hamid',         'nadeem' => 'Nadim',
        'waheed' => 'Wahid',         'saeed' => 'Said',
        'rasoul' => 'Rasul',         'rasool' => 'Rasul',
        'daoud' => 'Dawud',          'dawood' => 'Dawud',
        'noor' => 'Nur',             'baryalai' => 'Barjalai',
        'toryalai' => 'Torjalai',    'sayed' => 'Sajed',
        'sayyed' => 'Sajed',         'yar' => 'Jar',

        /* --- Familiennamen --- */
        'ahmadzai' => 'Achmadsai',   'noorzai' => 'Nursai',
        'barakzai' => 'Baraksai',    'alizai' => 'Alisai',
        'zadran' => 'Sadran',        'zazai' => 'Sasai',
        'niazi' => 'Niasi',          'karzai' => 'Karsai',
        'ghani' => 'Gani',           'ghafoor' => 'Gafur',
        'ghulam' => 'Gulam',         'sherzad' => 'Schirsad',
        'shinwari' => 'Schinwari',   'panjshiri' => 'Pandschiri',
        'hazara' => 'Hasara',        'tajik' => 'Tadschik',
        'nazari' => 'Nasari',        'azimi' => 'Asimi',
        'faizi' => 'Faisi',          'qaderi' => 'Kaderi',
        'zahedi' => 'Sahedi',        'rezai' => 'Resai',
        'rezaei' => 'Resai',         'hosseini' => 'Hosseini',
        'mojaddedi' => 'Modschaddedi', 'mujahid' => 'Mudschahid',
        'khalili' => 'Chalili',      'khaliqi' => 'Chaliki',
        'zaki' => 'Saki',            'ziai' => 'Siai',
    ];
}

/**
 * Buchstabenfolgen, die es im Deutschen nicht gibt und die deshalb
 * gefahrlos umgeschrieben werden koennen — auch bei Namen, die nicht im
 * Woerterbuch stehen. Alles, was auch deutsch vorkommt (z, j, v, oo, ee),
 * bleibt bewusst unangetastet: Dort waere der Schaden an deutschen Namen
 * groesser als der Gewinn.
 */
function ausspracheRegeln(): array
{
    return [
        '/kh/i' => 'ch',    // Khalid  -> Chalid
        '/gh/i' => 'g',     // Ghani   -> Gani
        '/^sh/i' => 'Sch',  // Sharif  -> Scharif  (nur am Wortanfang)
        '/q(?!u)/i' => 'K', // Qasim   -> Kasim    (deutsch steht q nur vor u)
    ];
}

/**
 * Schreibt einen Namen fuer die Sprachausgabe um. Wort fuer Wort: erst im
 * Woerterbuch nachsehen, sonst die gefahrlosen Regeln anwenden.
 * Gross- und Kleinschreibung des Wortanfangs bleibt erhalten.
 */
function fuerAussprache(string $name, array $zusatz = []): string
{
    $buch = $zusatz + ausspracheWoerterbuch();
    $buch = array_change_key_case($buch, CASE_LOWER);

    $teile = preg_split('/(\s+|-)/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($teile as $i => $wort) {
        if ($wort === '' || preg_match('/^(\s+|-)$/u', $wort)) {
            continue;
        }
        $schluessel = mb_strtolower($wort);
        if (isset($buch[$schluessel])) {
            $teile[$i] = (string) $buch[$schluessel];
            continue;
        }
        $neu = $wort;
        foreach (ausspracheRegeln() as $muster => $ersatz) {
            $neu = preg_replace($muster, $ersatz, $neu) ?? $neu;
        }
        // Ein am Wortanfang grossgeschriebener Name bleibt grossgeschrieben.
        if ($neu !== $wort && preg_match('/^\p{Lu}/u', $wort)) {
            $neu = mb_strtoupper(mb_substr($neu, 0, 1)) . mb_substr($neu, 1);
        }
        $teile[$i] = $neu;
    }
    return implode('', $teile);
}
