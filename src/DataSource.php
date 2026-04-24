<?php

namespace Flyokai\AmpDataPipeline;

use Amp\Pipeline\ConcurrentIterator;
use Flyokai\AmpDataPipeline\DataItem\DataItem;

/**
* @extends \IteratorAggregate<int, DataItem>
 */
interface DataSource extends \IteratorAggregate
{
    /**
     * @return ConcurrentIterator<DataItem>
     */
    public function getIterator(): ConcurrentIterator;
}
