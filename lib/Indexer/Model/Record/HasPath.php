<?php

namespace Phpactor\Indexer\Model\Record;

use Phpactor\TextDocument\TextDocumentUri;

interface HasDefinitions
{
    /**
     * @return $this
     */
    public function addDefinition(TextDocumentUri $definition): self;

    /**
     * @return array<TextDocumentUri>
     */
    public function definitions(): array;
}
