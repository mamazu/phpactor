<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Model;

use Closure;

class LazyIndexAccess implements IndexAccess
{
    private ?IndexAccess $index = null;

    /**
     * @param Closure(): ?IndexAccess $lazy
     */
    public function __construct(private Closure $lazy)
    {
    }

    public function get(Record $record): Record
    {
        return $this->getIndex()->get($record);
    }

    public function has(Record $record): bool
    {
        return $this->getIndex()->has($record);
    }

    private function getIndex(): IndexAccess
    {
        if ($this->index === null) {
            $this->index = call_user_func($this->lazy);
        }

        return $this->index;
    }
}
