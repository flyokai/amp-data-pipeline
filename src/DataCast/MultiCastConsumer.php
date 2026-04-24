<?php

namespace Flyokai\AmpDataPipeline\DataCast;

use Amp\Pipeline\Queue;
use Flyokai\AmpDataPipeline\DataItem\DataItem;

interface MultiCastConsumer
{
    public function __construct(
        CastProcessor $processor,
        Queue $queue,
        array $castProcessors,
        \SplObjectStorage $castResults,
        \SplQueue $waitingQueue,
        /** @var \Closure(DataItem):void */
        \Closure $releaseDataItem,
        bool $groupResults,
        int $groupBufferSize
    );
    public function consume(): void;
}
