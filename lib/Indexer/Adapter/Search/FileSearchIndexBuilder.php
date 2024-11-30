<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Adapter\Search;

use Phpactor\Indexer\Adapter\Php\FileSearchIndex;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\Record\FunctionRecord;
use Phpactor\Indexer\Model\Record\ConstantRecord;
use Phpactor\Indexer\Model\Record\ClassRecord;
use Phpactor\Indexer\Model\SearchIndex;
use Phpactor\Indexer\Model\SearchIndex\FilteredSearchIndex;
use Phpactor\Indexer\Model\SearchIndex\ValidatingSearchIndex;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FileSearchIndexBuilder implements SearchIndexBuilderInterface
{
    public function __construct(
        private string $indexRoot,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function build(Index $index): SearchIndex
    {
        $search = new FileSearchIndex($this->indexRoot . '/search');
        $search = new ValidatingSearchIndex($search, $index, $this->logger);
        $search = new FilteredSearchIndex($search, [
            ClassRecord::RECORD_TYPE,
            FunctionRecord::RECORD_TYPE,
            ConstantRecord::RECORD_TYPE,
        ]);

        return $search;
    }
}
