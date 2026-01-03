<?php

namespace Phpactor\Indexer\Adapter\Php\Serialized;

use Phpactor\Indexer\Model\RecordSerializer;
use Phpactor\Indexer\Util\Filesystem;
use Phpactor\Indexer\Model\Record;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class FileRepository
{
    /**
     * Increment this number each time there is a B/C break in the index.
     */
    private const VERSION = 3;

    private int $lastUpdate;

    public function __construct(
        private string $path,
        private RecordSerializer $serializer,
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->initializeLastUpdate();
    }

    public function put(Record $record): void
    {
        $path = $this->pathFor($record);
        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $this->serializer->serialize($record));
    }

    /**
     * @template TRecord of Record
     * @param TRecord $record
     * @return TRecord
     */
    public function get(Record $record): ?Record
    {
        $path = $this->pathFor($record);

        if (!file_exists($path)) {
            $this->remove($record);
            return null;
        }

        try {
            $deserialized = $this->serializer->deserialize((string)file_get_contents($path));
        } catch (Throwable $corrupted) {
            $this->logger->warning(sprintf(
                'Record at path "%s" is corrupted, removing: %s',
                $path,
                $corrupted->getMessage()
            ));
            $this->remove($record);
            return null;
        }

        if (null === $deserialized) {
            return null;
        }

        if (!$deserialized instanceof $record) {
            $this->logger->warning(sprintf(
                'Invalid cache entry file: "%s", got instance of "%s"',
                $path,
                get_class($deserialized)
            ));

            return null;
        }

        return $deserialized;
    }

    public function putTimestamp(?int $time = null): void
    {
        $time = $time ?? time();
        $this->ensureDirectoryExists(dirname($this->timestampPath()));
        file_put_contents($this->timestampPath(), $time);
        $this->lastUpdate = $time;
    }

    public function lastUpdate(): int
    {
        return $this->lastUpdate;
    }

    public function reset(): void
    {
        Filesystem::removeDir($this->path);
        $this->putTimestamp(0);
    }

    public function remove(Record $record): void
    {
        $path = $this->pathFor($record);

        if (!file_exists($path)) {
            return;
        }

        if (@unlink($path)) {
            return;
        }

        $this->logger->warning(sprintf(
            'Could not remove index file "%s"',
            $path
        ));
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }

    private function initializeLastUpdate(): void
    {
        $this->lastUpdate = file_exists($this->timestampPath()) ?
            (int)file_get_contents($this->timestampPath()) :
            0
        ;
    }

    private function timestampPath(): string
    {
        return sprintf('%s/timestamp.v%d', $this->path, self::VERSION);
    }

    private function pathFor(Record $record): string
    {
        $hash = md5($record->identifier());
        return sprintf(
            '%s/%s_%s/%s/%s.cache',
            $this->path,
            $record->recordType(),
            substr($hash, 0, 1),
            substr($hash, 1, 1),
            $hash
        );
    }

}
