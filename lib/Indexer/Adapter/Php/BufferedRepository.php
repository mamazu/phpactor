<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Php;

use Phpactor\Indexer\Adapter\Php\Serialized\FileRepository;
use Phpactor\Indexer\Model\Record;

class BufferedRepository
{
    /**
     * Flush to the filesystem after BATCH_SIZE updates
     */
    private const BATCH_SIZE = 10000;

    /**
     * @var array<string,Record>
     */
    private array $buffer = [];

    private int $counter = 0;

    public function __construct(
        private FileRepository $inner
    ) {}

    public function put(Record $record): void
    {
        $this->buffer[$this->bufferKey($record)] = $record;

        if (++$this->counter % self::BATCH_SIZE === 0) {
            $this->flush();
        }
    }

    public function get(Record $record): ?Record
    {
        $bufferKey = $this->bufferKey($record);

        if (isset($this->buffer[$bufferKey])) {
            /** @phpstan-ignore-next-line */
            return $this->buffer[$bufferKey];
        }

        return $this->inner->get($record);
    }

    public function flush(): void
    {
        foreach ($this->buffer as $record) {
            $this->inner->put($record);
        }
        $this->buffer = [];
    }

    private function bufferKey(Record $record): string
    {
        return $record->recordType().$record->identifier();
    }
}
