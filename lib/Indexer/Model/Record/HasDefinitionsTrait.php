<?php

namespace Phpactor\Indexer\Model\Record;

use Phpactor\TextDocument\TextDocumentUri;

trait HasDefinitionsTrait
{
    protected array $definitions = [];

    public function addDefinition(TextDocumentUri $definition): self
    {
        $this->definitions[] = $definition;
        return $this;
    }

    public function definitions(): array
    {
        return $this->definitions;
    }
}
