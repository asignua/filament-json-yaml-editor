<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Enums;

/**
 * The views a {@see \Asignua\FilamentJsonYamlEditor\Forms\JsonEditor} can switch between.
 */
enum EditorMode: string
{
    /**
     * Syntax highlighting, line numbers, folding, live lint.
     */
    case Code = 'code';

    /**
     * Expand / collapse, edit values, add and remove keys.
     */
    case Tree = 'tree';
}
