<?php

namespace Flyokai\AmpDataPipeline\DataCast;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Flyokai\AmpDataPipeline\DataSource;
use Flyokai\AmpDataPipeline\Helper\ProcessorAssertion;

class SkipCastProcessor implements CastProcessor
{
    use ProcessorAssertion;
    public function cast(ConcurrentIterator $source, \Closure $acceptCastItem): void
    {
        $this->assertSourceIterator($source);
        foreach ($source as $item) {
            $queue = new Queue();
            $acceptCastItem($queue->iterate());
            $queue->push($item);
            $queue->complete();
        }
    }
}
