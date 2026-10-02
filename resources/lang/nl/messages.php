<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Weergave',
        'mode_code' => 'Code',
        'mode_tree' => 'Boom',
        'format' => 'Formatteren',
        'errorAtLine' => 'Regel :line: :message',
        'treeInvalid' => 'De tekst is geen geldige JSON. Herstel hem in de codeweergave om de boom te gebruiken.',
        'tree' => [
            'type' => 'Type',
            'value' => 'Waarde',
            'key' => 'Sleutel',
            'add' => 'Toevoegen',
            'remove' => 'Verwijderen',
            'expand' => 'Uitklappen',
            'collapse' => 'Inklappen',
            'types' => [
                'string' => 'tekst',
                'number' => 'getal',
                'boolean' => 'booleaans',
                'null' => 'null',
                'object' => 'object',
                'array' => 'array',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Het veld :attribute moet geldige JSON zijn (:message).',
        'container_only' => 'Het veld :attribute moet een JSON-object of -array zijn.',
        'invalid_yaml' => 'Het veld :attribute moet geldige YAML zijn (:message).',
        'schema_invalid' => 'Het veld :attribute voldoet niet aan het schema: :errors.',
    ],
    'entry' => [
        'copy' => 'Kopiëren',
    ],
];
