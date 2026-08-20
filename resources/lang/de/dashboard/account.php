<?php

return [
    'email' => [
        'title' => 'E-Mail aktualisieren',
        'updated' => 'Deine E-Mail-Adresse wurde aktualisiert.',
    ],
    'password' => [
        'title' => 'Passwort ändern',
        'requirements' => 'Dein neues Passwort sollte mindestens 8 Zeichen lang sein.',
        'updated' => 'Dein Passwort wurde aktualisiert.',
    ],
    'two_factor' => [
        'button' => 'Zwei-Faktor-Authentifizierung einrichten',
        'disabled' => 'Die Zwei-Faktor-Authentifizierung wurde für dein Konto deaktiviert. Du wirst beim Anmelden nicht mehr zur Eingabe eines Tokens aufgefordert.',
        'enabled' => 'Die Zwei-Faktor-Authentifizierung wurde für dein Konto aktiviert! Ab sofort musst du beim Anmelden den von deinem Gerät erzeugten Code eingeben.',
        'invalid' => 'Der angegebene Token war ungültig.',
        'setup' => [
            'title' => 'Zwei-Faktor-Authentifizierung einrichten',
            'help' => 'Code nicht scannbar? Gib den folgenden Code in deine Anwendung ein:',
            'field' => 'Token eingeben',
        ],
        'disable' => [
            'title' => 'Zwei-Faktor-Authentifizierung deaktivieren',
            'field' => 'Token eingeben',
        ],
    ],
];
