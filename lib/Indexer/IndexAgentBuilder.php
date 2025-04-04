<?php

namespace Phpactor\Indexer;

use Phpactor\Filesystem\Domain\FilePath;
use Phpactor\Filesystem\Adapter\Simple\SimpleFileListProvider;
use Phpactor\Filesystem\Adapter\Simple\SimpleFilesystem;
use Phpactor\Indexer\Adapter\Filesystem\FilesystemFileListProvider;
use Phpactor\Indexer\Adapter\Php\FileIndexFactory;
use Phpactor\Indexer\Adapter\Search\FileSearchIndexBuilder;
use Phpactor\Indexer\Adapter\Search\QueryClientBuilder;
use Phpactor\Indexer\Adapter\Search\SearchIndexBuilderInterface;
use Phpactor\Indexer\Adapter\Tolerant\TolerantIndexBuilder;
use Phpactor\Indexer\Adapter\Tolerant\TolerantIndexer;
use Phpactor\Indexer\Model\FileListProvider;
use Phpactor\Indexer\Model\FileListProvider\ChainFileListProvider;
use Phpactor\Indexer\Model\FileListProvider\DirtyFileListProvider;
use Phpactor\Indexer\Model\Index;
use Phpactor\Indexer\Model\IndexFactoryInterface;
use Phpactor\Indexer\Model\Index\SearchAwareIndex;
use Phpactor\Indexer\Model\RealIndexAgent;
use Phpactor\Indexer\Model\IndexBuilder;
use Phpactor\Indexer\Model\Indexer;
use Phpactor\Indexer\Model\RecordReferenceEnhancer\NullRecordReferenceEnhancer;
use Phpactor\Indexer\Model\SearchClient\HydratingSearchClient;
use Phpactor\Indexer\Model\TestIndexAgent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Phpactor\Indexer\Model\SearchIndex;

final class IndexAgentBuilder
{
    /**
     * @var array<string>
     */
    private array $includePatterns = [
        '/**/*.php',
        '/**/*.phar',
    ];

    /**
     * @var array<string>
     */
    private array $stubPaths = [];

    /**
     * @var array<string>
     */
    private array $excludePatterns = [
    ];

    /**
     * @var array<TolerantIndexer>|null
     */
    private ?array $indexers = null;

    private bool $followSymlinks = false;

    /**
     * @var list<string>
     */
    private array $supportedExtensions = ['php', 'phar'];

    private LoggerInterface $logger;

    public function __construct(
        private string $indexRoot,
        private string $projectRoot,
        private SearchIndexBuilderInterface $searchBuilder,
        private IndexFactoryInterface $indexFactory,
        private QueryClientBuilder $queryClientBuilder,
    ) {
        $this->logger = new NullLogger();
    }

    public static function create(string $indexRootPath, string $projectRoot): self
    {
        return new self(
            $indexRootPath,
            $projectRoot,
            new FileSearchIndexBuilder($indexRootPath),
            new FileIndexFactory($this->indexRoot, $this->logger),
            new QueryClientBuilder(new NullRecordReferenceEnhancer()),
        );
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function addStubPath(string $path): self
    {
        $this->stubPaths[] = $path;

        return $this;
    }

    public function setSearchBuilder(SearchIndexBuilderInterface $searchBuilder): self
    {
        $this->searchBuilder = $searchBuilder;

        return $this;
    }

    public function buildAgent(): IndexAgent
    {
        return $this->buildTestAgent();
    }

    public function buildTestAgent(): TestIndexAgent
    {
        $index = $this->indexFactory->create();
        $search = $this->searchBuilder->build($index);
        $index = new SearchAwareIndex($index, $search);
        $query = $this->queryClientBuilder->build($index);
        $builder = $this->buildBuilder($index);
        $indexer = $this->buildIndexer($builder, $index, $search);
        $search = new HydratingSearchClient($index, $search);

        return new RealIndexAgent($index, $query, $search, $indexer);
    }

    /**
     * @param array<TolerantIndexer> $indexers
     */
    public function setIndexers(array $indexers): self
    {
        $this->indexers = $indexers;

        return $this;
    }

    /**
     * @param array<string> $excludePatterns
     */
    public function setExcludePatterns(array $excludePatterns): self
    {
        $this->excludePatterns = $excludePatterns;

        return $this;
    }

    /**
     * @param array<string> $includePatterns
     */
    public function setIncludePatterns(array $includePatterns): self
    {
        $this->includePatterns = $includePatterns;

        return $this;
    }

    /**
     * @param list<string> $supportedExtensions
     */
    public function setSupportedExtensions(array $supportedExtensions): self
    {
        $this->supportedExtensions = $supportedExtensions;

        return $this;
    }

    public function setFollowSymlinks(bool $followSymlinks): self
    {
        $this->followSymlinks = $followSymlinks;

        return $this;
    }

    /**
     * @param array<string> $stubPaths
     */
    public function setStubPaths(array $stubPaths): self
    {
        $this->stubPaths = $stubPaths;

        return $this;
    }

    private function buildBuilder(Index $index): IndexBuilder
    {
        if (null !== $this->indexers) {
            return new TolerantIndexBuilder($index, $this->indexers, $this->logger);
        }
        return TolerantIndexBuilder::create($index);
    }

    private function buildIndexer(
        IndexBuilder $builder,
        Index $index,
        SearchIndex $seachIndex,
    ): Indexer {
        return new Indexer(
            $builder,
            $index,
            $seachIndex,
            $this->buildFileListProvider(),
            $this->buildDirtyTracker()
        );
    }

    private function buildFileListProvider(): FileListProvider
    {
        return new ChainFileListProvider(...$this->buildFileListProviders());
    }

    private function buildFilesystem(string $root): SimpleFilesystem
    {
        return new SimpleFilesystem(
            FilePath::fromString($this->indexRoot),
            new SimpleFileListProvider(
                FilePath::fromString($root),
                $this->followSymlinks
            )
        );
    }

    /**
     * @return array<FileListProvider>
     */
    private function buildFileListProviders(): array
    {
        $providers = [
            new FilesystemFileListProvider(
                $this->buildFilesystem($this->projectRoot),
                $this->includePatterns,
                $this->excludePatterns,
                $this->supportedExtensions,
            )
        ];

        foreach ($this->stubPaths as $stubPath) {
            $providers[] = new FilesystemFileListProvider(
                $this->buildFilesystem($stubPath)
            );
        }

        $providers[] = $this->buildDirtyTracker();

        return $providers;
    }

    private function buildDirtyTracker(): DirtyFileListProvider
    {
        return new DirtyFileListProvider($this->indexRoot . '/dirty');
    }
}
