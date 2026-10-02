<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Вигляд',
        'mode_code' => 'Код',
        'mode_tree' => 'Дерево',
        'format' => 'Форматувати',
        'errorAtLine' => 'Рядок :line: :message',
        'treeInvalid' => 'Текст не є коректним JSON. Виправте його в режимі коду, щоб користуватися деревом.',
        'tree' => [
            'type' => 'Тип',
            'value' => 'Значення',
            'key' => 'Ключ',
            'add' => 'Додати',
            'remove' => 'Видалити',
            'expand' => 'Розгорнути',
            'collapse' => 'Згорнути',
            'types' => [
                'string' => 'текст',
                'number' => 'число',
                'boolean' => 'логічне',
                'null' => 'null',
                'object' => 'обʼєкт',
                'array' => 'масив',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Поле :attribute має бути коректним JSON (:message).',
        'container_only' => 'Поле :attribute має бути JSON-обʼєктом або масивом.',
        'invalid_yaml' => 'Поле :attribute має бути коректним YAML (:message).',
        'schema_invalid' => 'Поле :attribute не відповідає схемі: :errors.',
    ],
    'entry' => [
        'copy' => 'Копіювати',
    ],
];
