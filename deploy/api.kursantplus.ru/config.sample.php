<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => '46.8.141.165',
        'port' => 3306,
        'database' => 'jfgfmnue_kursantplus',
        'username' => 'root',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'transport' => 'smtp',
        'recipient' => 'info@kursantplus.ru',
        'from_email' => 'noreply@kursantplus.ru',
        'from_name' => 'Kursant+',
        'smtp' => [
            'host' => 'smtp.mail.ru',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'noreply@kursantplus.ru',
            'password' => 'CHANGE_ME',
            'timeout' => 20,
        ],
    ],
    'cors' => [
        'allowed_origins' => [
            'https://kursantplus.ru',
            'https://www.kursantplus.ru',
            'http://localhost:4321',
        ],
    ],
];
