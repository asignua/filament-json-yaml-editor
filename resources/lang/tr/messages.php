<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Görünüm',
        'mode_code' => 'Kod',
        'mode_tree' => 'Ağaç',
        'format' => 'Biçimlendir',
        'errorAtLine' => 'Satır :line: :message',
        'treeInvalid' => 'Metin geçerli bir JSON değil. Ağacı kullanmak için kod görünümünde düzeltin.',
        'tree' => [
            'type' => 'Tür',
            'value' => 'Değer',
            'key' => 'Anahtar',
            'add' => 'Ekle',
            'remove' => 'Kaldır',
            'expand' => 'Genişlet',
            'collapse' => 'Daralt',
            'types' => [
                'string' => 'metin',
                'number' => 'sayı',
                'boolean' => 'mantıksal',
                'null' => 'null',
                'object' => 'nesne',
                'array' => 'dizi',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Alan :attribute geçerli bir JSON olmalıdır (:message).',
        'container_only' => 'Alan :attribute bir JSON nesnesi veya dizisi olmalıdır.',
        'invalid_yaml' => 'Alan :attribute geçerli bir YAML olmalıdır (:message).',
        'schema_invalid' => 'Alan :attribute şemaya uymuyor: :errors.',
    ],
    'entry' => [
        'copy' => 'Kopyala',
    ],
];
