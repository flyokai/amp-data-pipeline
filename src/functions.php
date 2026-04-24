<?php

namespace Flyokai\AmpDataPipeline;

use Amp\Pipeline\Queue;

function errorDisposeQueue(Queue $queue, \Throwable $exception): void
{
    $queueRefl = new \ReflectionObject($queue);
    $queueState = $queueRefl->getProperty('state')->getValue($queue);
    $queueStateRefl = new \ReflectionObject($queueState);
    $queueStateRefl->getMethod('finalize')->invoke($queueState, $exception, true);
}
