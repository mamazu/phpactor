<?php

namespace Phpactor\Indexer\Model\Query;

use Generator;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\IndexQuery;
use Phpactor\Indexer\Model\LocationConfidence;
use Phpactor\Indexer\Model\RecordReferenceEnhancer;
use Phpactor\Indexer\Model\Record\FileRecord;
use Phpactor\Indexer\Model\Record\MemberRecordType;
use Phpactor\TextDocument\Location;
use Phpactor\Indexer\Model\Record\MemberRecord;

class MemberQuery implements IndexQuery
{
    public function __construct(private Index $index, private RecordReferenceEnhancer $enhancer)
    {
    }

    public function get(string $identifier): ?MemberRecord
    {
        if (!MemberRecord::isIdentifier($identifier)) {
            return null;
        }

        $prototype = MemberRecord::fromIdentifier($identifier);

        if (false === $this->index->has($prototype)) {
            return null;
        }

        return $this->index->get($prototype);
    }

    /**
     * @return Generator<LocationConfidence>
     */
    public function referencesTo(MemberRecordType $type, string $memberName, ?string $containerType = null): Generator
    {
        $record = $this->getByTypeAndName($type, $memberName);

        if (null === $record) {
            return;
        }

        assert($record instanceof MemberRecord);

        foreach ($record->references() as $fileReference) {
            $fileRecord = $this->index->get(FileRecord::fromPath($fileReference));
            assert($fileRecord instanceof FileRecord);

            foreach ($fileRecord->references()->to($record) as $memberReference) {
                if ($containerType && null === $memberReference->contaninerType()) {
                    $memberReference = $this->enhancer->enhance($fileRecord, $memberReference);
                }

                $location = Location::fromPathAndOffsets(
                    $fileRecord->filePath() ?? '',
                    $memberReference->start(),
                    $memberReference->end()
                );

                if (null === $memberReference->contaninerType()) {
                    yield LocationConfidence::maybe($location);
                    continue;
                }

                if ($containerType && $containerType !== $memberReference->contaninerType()) {
                    yield LocationConfidence::not($location);
                    continue;
                }

                yield LocationConfidence::surely($location);
            }
        }
    }

    private function getByTypeAndName(MemberRecordType $type, string $name): ?MemberRecord
    {
        return $this->get($type->value . '#' . $name);
    }
}
