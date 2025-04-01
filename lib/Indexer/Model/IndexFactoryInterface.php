<?php

declare(strict_types=1);

namespace Phpactor\Indexer\Model;

interface IndexFactoryInterface
{
    public function create(): Index;
}
