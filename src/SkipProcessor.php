<?php

namespace Flyokai\AmpDataPipeline;

use Flyokai\AmpDataPipeline\DataItem\DataItem;

class SkipProcessor extends ProcessorAbstract
{
    protected function processDataItem(DataItem $value): void
    {
        $this->releaseDataItem($value);
    }
}
