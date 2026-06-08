<?php

namespace Flyokai\AmpDataPipeline\DataItem;

use Amp\Future;

interface DataItemHandler
{
    public function canHandle(DataItem $value): bool;

    /**
     * @param DataItem $value
     * @return Future<DataItem>
     */
    public function handle(DataItem $value): Future;
}
