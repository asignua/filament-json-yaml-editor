<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Ansicht',
        'mode_code' => 'Code',
        'mode_tree' => 'Baum',
        'format' => 'Formatieren',
        'errorAtLine' => 'Zeile :line: :message',
        'treeInvalid' => 'Der Text ist kein gültiges JSON. Korrigieren Sie ihn in der Code-Ansicht, um den Baum zu nutzen.',
        'tree' => [
            'type' => 'Typ',
            'value' => 'Wert',
            'key' => 'Schlüssel',
            'add' => 'Hinzufügen',
            'remove' => 'Entfernen',
            'expand' => 'Aufklappen',
            'collapse' => 'Zuklappen',
            'types' => [
                'string' => 'Text',
                'number' => 'Zahl',
                'boolean' => 'Boolesch',
                'null' => 'null',
                'object' => 'Objekt',
                'array' => 'Array',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Das Feld :attribute muss gültiges JSON sein (:message).',
        'container_only' => 'Das Feld :attribute muss ein JSON-Objekt oder -Array sein.',
        'invalid_yaml' => 'Das Feld :attribute muss gültiges YAML sein (:message).',
        'schema_invalid' => 'Das Feld :attribute entspricht nicht dem Schema: :errors.',
    ],
    'entry' => [
        'copy' => 'Kopieren',
    ],
];
