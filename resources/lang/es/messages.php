<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Vista',
        'mode_code' => 'Código',
        'mode_tree' => 'Árbol',
        'format' => 'Formatear',
        'errorAtLine' => 'Línea :line: :message',
        'treeInvalid' => 'El texto no es JSON válido. Corríjalo en la vista de código para usar el árbol.',
        'tree' => [
            'type' => 'Tipo',
            'value' => 'Valor',
            'key' => 'Clave',
            'add' => 'Añadir',
            'remove' => 'Eliminar',
            'expand' => 'Expandir',
            'collapse' => 'Contraer',
            'types' => [
                'string' => 'texto',
                'number' => 'número',
                'boolean' => 'booleano',
                'null' => 'null',
                'object' => 'objeto',
                'array' => 'array',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'El campo :attribute debe ser JSON válido (:message).',
        'container_only' => 'El campo :attribute debe ser un objeto o un array JSON.',
        'invalid_yaml' => 'El campo :attribute debe ser YAML válido (:message).',
        'schema_invalid' => 'El campo :attribute no cumple el esquema: :errors.',
    ],
    'entry' => [
        'copy' => 'Copiar',
    ],
];
