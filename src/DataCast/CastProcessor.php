<?php

namespace Flyokai\AmpDataPipeline\DataCast;

use Amp\Pipeline\ConcurrentIterator;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\DataSource;

interface CastProcessor
{
    /**
     * @param \Closure(ConcurrentIterator,?DataItem $origItem,?CastProcessor $castProcessor):void $acceptCastItem
     * @return void
     */
    public function cast(ConcurrentIterator $source, \Closure $acceptCastItem): void;
}
