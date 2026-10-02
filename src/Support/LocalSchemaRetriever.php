<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Support;

use JsonSchema\Exception\ResourceNotFoundException;
use JsonSchema\Uri\UriRetriever;
use ReflectionClass;

/**
 * A `justinrainbow/json-schema` retriever that never leaves the package: the draft
 * meta-schemas bundled with justinrainbow load, every other `$ref` target (http(s),
 * file://, anything) is refused. A schema that comes from data must not turn validation
 * into a server-side fetch or a local file read.
 *
 * @internal used by {@see \Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule}
 */
class LocalSchemaRetriever extends UriRetriever
{
    /**
     * @param string $fetchUri
     */
    protected function loadSchema($fetchUri)
    {
        if (!str_starts_with((string) $fetchUri, $this->bundledSchemas()) || str_contains((string) $fetchUri, '..')) {
            throw new ResourceNotFoundException('External $ref is not allowed: '.$fetchUri);
        }

        return parent::loadSchema($fetchUri);
    }

    /**
     * The same directory UriRetriever::translate() maps `package://` to.
     */
    private function bundledSchemas(): string
    {
        $source = (string) (new ReflectionClass(UriRetriever::class))->getFileName();

        return 'file://'.realpath(dirname($source, 4)).'/dist/schema/';
    }
}
