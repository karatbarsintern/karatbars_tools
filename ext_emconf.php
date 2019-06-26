<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Karatbars Tools for Typo3',
    'description' => 'Karatbars Tools for Typo3',
    'category' => 'plugin',
    'author' => 'Oliver Kurzer',
    'author_company' => '',
    'author_email' => 'oliver.kurzer@karatbars.de',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.0.0-9.9.99'
        ]
    ],
    'autoload' => [
        'psr-4' => [
            'Karatbars\\KaratbarsTools\\' => 'Classes'
        ]
    ],
];
