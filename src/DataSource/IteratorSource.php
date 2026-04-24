<?php

namespace Flyokai\AmpDataPipeline\DataSource;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Internal\ConcurrentIterableIterator;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\DataSource;

class IteratorSource implements DataSource
{
    /**
     * @var ConcurrentIterableIterator<DataItem>
     */
    protected ConcurrentIterableIterator $iterator;

    public function __construct(
        iterable $iterator,
    )
    {
        $this->iterator = new ConcurrentIterableIterator($iterator);
    }

    public function getIterator(): ConcurrentIterator
    {
        return $this->iterator;
    }
}
