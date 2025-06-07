<?php

namespace Phpactor\Indexer\Model\Record;

use Phpactor\Indexer\Model\Record;
use Stringable;

final class FunctionRecord implements HasFileReferences, HasPath, Record, HasFullyQualifiedName, Stringable
{
    use FullyQualifiedReferenceTrait;
    use HasFileReferencesTrait;
    use HasPathTrait;
    public const RECORD_TYPE = 'function';

    public function __toString(): string
    {
        return self::class.' ('.$this->fqn.')';
    }

    public static function fromName(string $name): self
    {
        return new self($name);
    }


    public function recordType(): string
    {
        return self::RECORD_TYPE;
    }
}
