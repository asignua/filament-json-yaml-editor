<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Widok',
        'mode_code' => 'Kod',
        'mode_tree' => 'Drzewo',
        'format' => 'Formatuj',
        'errorAtLine' => 'Wiersz :line: :message',
        'treeInvalid' => 'Tekst nie jest poprawnym JSON-em. Popraw go w widoku kodu, aby korzystać z drzewa.',
        'tree' => [
            'type' => 'Typ',
            'value' => 'Wartość',
            'key' => 'Klucz',
            'add' => 'Dodaj',
            'remove' => 'Usuń',
            'expand' => 'Rozwiń',
            'collapse' => 'Zwiń',
            'types' => [
                'string' => 'tekst',
                'number' => 'liczba',
                'boolean' => 'logiczny',
                'null' => 'null',
                'object' => 'obiekt',
                'array' => 'tablica',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Pole :attribute musi być poprawnym JSON-em (:message).',
        'container_only' => 'Pole :attribute musi być obiektem lub tablicą JSON.',
        'invalid_yaml' => 'Pole :attribute musi być poprawnym YAML-em (:message).',
        'schema_invalid' => 'Pole :attribute nie spełnia schematu: :errors.',
    ],
    'entry' => [
        'copy' => 'Kopiuj',
    ],
];
