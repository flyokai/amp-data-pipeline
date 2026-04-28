<?php

namespace Flyokai\AmpDataPipeline\DataItem;

use Amp\Pipeline\Internal\Sequence;
use Amp\Sync\LocalMutex;
use Amp\Sync\Mutex;
use function Amp\async;
use Amp\Future;
use function Amp\Sync\synchronized;

class HandlerComposition implements DataItemHandler
{
    protected \SplObjectStorage $positions;
    protected Sequence $sequence;
    protected int $accepted = 0;

    public function __construct(
        /** @var list<DataItemHandler> */
        protected readonly array $handlers,
        protected readonly bool  $ordered = false,
        protected Mutex $mutex = new LocalMutex,
    )
    {
        if (empty($handlers)) {
            throw new \RuntimeException('Handlers parameter is empty');
        }
        $i=0;
        $this->positions = new \SplObjectStorage();
        foreach ($this->handlers as $handler) {
            $this->positions->attach($handler, $i++);
        }
        $this->sequence = new Sequence;
    }

    public function canHandle(DataItem $value): bool
    {
        $currentBatchPosition = $this->currentBatchPosition();
        synchronized($this->mutex, $this->waitNextBatch(...), $currentBatchPosition);
        $canHandleCount = 0;
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($value)) {
                $canHandleCount++;
            }
        }
        if ($canHandleCount>1) {
            throw new \RuntimeException('DataItem cannot be handled by multiple handlers');
        }
        return (bool)$canHandleCount;
    }

    protected function currentBatchPosition(): int
    {
        $handlersCount = count($this->handlers);
        $currentBatch = intval(($this->accepted)/$handlersCount)*$handlersCount;
        return $currentBatch;
    }

    public function handle(DataItem $value): Future
    {
        $this->accepted++;
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($value)) {
                return async($this->runHandler(...), $handler, $value);
            }
        }
        throw new \RuntimeException('No matching handler found for DataItem');
    }

    protected function waitNextBatch($currentBatch)
    {
        if ($this->ordered) $this->sequence->await($currentBatch);
    }

    protected function runHandler(DataItemHandler $handler, DataItem $value): mixed
    {
        $position = $this->positions[$handler];
        $this->positions[$handler] = $this->positions[$handler]+count($this->handlers);
        if ($this->ordered) $this->sequence->await($position);
        $result = $handler->handle($value)->await();
        if ($this->ordered) $this->sequence->resume($position);
        return $result;
    }

}
