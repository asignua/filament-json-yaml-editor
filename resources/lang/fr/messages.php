<?php

declare(strict_types=1);

return [
    'editor' => [
        'view' => 'Vue',
        'mode_code' => 'Code',
        'mode_tree' => 'Arbre',
        'format' => 'Formater',
        'errorAtLine' => 'Ligne :line : :message',
        'treeInvalid' => 'Le texte n\'est pas du JSON valide. Corrigez-le dans la vue code pour utiliser l\'arbre.',
        'tree' => [
            'type' => 'Type',
            'value' => 'Valeur',
            'key' => 'Clé',
            'add' => 'Ajouter',
            'remove' => 'Supprimer',
            'expand' => 'Développer',
            'collapse' => 'Réduire',
            'types' => [
                'string' => 'texte',
                'number' => 'nombre',
                'boolean' => 'booléen',
                'null' => 'null',
                'object' => 'objet',
                'array' => 'tableau',
            ],
        ],
    ],
    'validation' => [
        'invalid_json' => 'Le champ :attribute doit être du JSON valide (:message).',
        'container_only' => 'Le champ :attribute doit être un objet ou un tableau JSON.',
        'invalid_yaml' => 'Le champ :attribute doit être du YAML valide (:message).',
        'schema_invalid' => 'Le champ :attribute ne respecte pas le schéma : :errors.',
    ],
    'entry' => [
        'copy' => 'Copier',
    ],
];
