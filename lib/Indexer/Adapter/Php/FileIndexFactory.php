<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Php;

use Phpactor\Indexer\Adapter\Php\Serialized\FileRepository;
use Phpactor\Indexer\Adapter\Php\Serialized\SerializedIndex;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\IndexFactoryInterface;
use Phpactor\Indexer\Model\RecordSerializer;
use Phpactor\Indexer\Model\RecordSerializer\PhpSerializer;
use Psr\Log\LoggerInterface;

class FileIndexFactory implements IndexFactoryInterface
{
    public function __construct(
        private string $indexRoot,
        private LoggerInterface $logger
    ) {
    }

    public function create(): Index
    {
        $repository = new FileRepository(
            $this->indexRoot,
            $this->buildRecordSerializer(),
            $this->logger
        );

        return new SerializedIndex($repository);
    }

    private function buildRecordSerializer(): RecordSerializer
    {
        return new PhpSerializer();
    }
}
