<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Visualização',
        'mode_code' => 'Código',
        'mode_tree' => 'Árvore',
        'format' => 'Formatar',
        'errorAtLine' => 'Linha :line: :message',
        'treeInvalid' => 'O texto não é um JSON válido. Corrija-o na visualização de código para usar a árvore.',
        'tree' => [
            'type' => 'Tipo',
            'value' => 'Valor',
            'key' => 'Chave',
            'add' => 'Adicionar',
            'remove' => 'Remover',
            'expand' => 'Expandir',
            'collapse' => 'Recolher',
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
        'invalid_json' => 'O campo :attribute deve ser um JSON válido (:message).',
        'container_only' => 'O campo :attribute deve ser um objeto ou array JSON.',
        'invalid_yaml' => 'O campo :attribute deve ser um YAML válido (:message).',
        'schema_invalid' => 'O campo :attribute não corresponde ao esquema: :errors.',
    ],
    'entry' => [
        'copy' => 'Copiar',
    ],
];
