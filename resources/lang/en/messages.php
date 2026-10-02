<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'View',
        'mode_code' => 'Code',
        'mode_tree' => 'Tree',
        'format' => 'Format',
        'errorAtLine' => 'Line :line: :message',
        'treeInvalid' => 'The text is not valid JSON. Fix it in the code view to use the tree.',
        'tree' => [
            'type' => 'Type',
            'value' => 'Value',
            'key' => 'Key',
            'add' => 'Add',
            'remove' => 'Remove',
            'expand' => 'Expand',
            'collapse' => 'Collapse',
            'types' => [
                'string' => 'text',
                'number' => 'number',
                'boolean' => 'boolean',
                'null' => 'null',
                'object' => 'object',
                'array' => 'array',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'The :attribute must be valid JSON (:message).',
        'container_only' => 'The :attribute must be a JSON object or array.',
        'invalid_yaml' => 'The :attribute must be valid YAML (:message).',
        'schema_invalid' => 'The :attribute does not match the schema: :errors.',
    ],
    'entry' => [
        'copy' => 'Copy',
    ],
];
