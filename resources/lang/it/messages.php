<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Vista',
        'mode_code' => 'Codice',
        'mode_tree' => 'Albero',
        'format' => 'Formatta',
        'errorAtLine' => 'Riga :line: :message',
        'treeInvalid' => 'Il testo non è un JSON valido. Correggilo nella vista codice per usare l\'albero.',
        'tree' => [
            'type' => 'Tipo',
            'value' => 'Valore',
            'key' => 'Chiave',
            'add' => 'Aggiungi',
            'remove' => 'Rimuovi',
            'expand' => 'Espandi',
            'collapse' => 'Comprimi',
            'types' => [
                'string' => 'testo',
                'number' => 'numero',
                'boolean' => 'booleano',
                'null' => 'null',
                'object' => 'oggetto',
                'array' => 'array',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Il campo :attribute deve essere un JSON valido (:message).',
        'container_only' => 'Il campo :attribute deve essere un oggetto o un array JSON.',
        'invalid_yaml' => 'Il campo :attribute deve essere un YAML valido (:message).',
        'schema_invalid' => 'Il campo :attribute non rispetta lo schema: :errors.',
    ],
    'entry' => [
        'copy' => 'Copia',
    ],
];
