<?php

namespace Flyokai\AmpDataPipeline\Batch;

use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\DataItem\DataItemHandler;
use Flyokai\AmpDataPipeline\DataItem\DataItemImpl;
use Flyokai\AmpDataPipeline\DataSource\QueueSource;
use Flyokai\AmpDataPipeline\Helper\ProcessorAssertion;
use Flyokai\AmpDataPipeline\Processor;
use Flyokai\AmpDataPipeline\ProcessorAbstract;
use function Amp\async;

class BatchProcessor extends ProcessorAbstract
{
    use ProcessorAssertion;
    /**
     * @param \Closure():Processor $batchProcessorFactory
     * @param \Closure(bool $ordered):DataItemHandler $resultHandlerFactory
     */
    public function __construct(
        protected \Closure $batchProcessorFactory,
        protected \Closure $resultHandlerFactory,
        protected int      $batchSize = 100,
        protected bool     $ordered = false,
        protected bool     $groupResults = false,
        protected bool     $throwIfUnhadled = true,
    )
    {
    }

    /**
     * @param \Closure():Processor $batchProcessorFactory
     * @param \Closure(bool $ordered):DataItemHandler $resultHandlerFactory
     */
    public static function selfCreate(
        \Closure $batchProcessorFactory,
        \Closure $resultHandlerFactory,
        int      $batchSize = 100,
        bool     $ordered = false,
        bool     $groupResults = false,
        bool     $throwIfUnhadled = true,
    ): static
    {
        return new self(
            $batchProcessorFactory,
            $resultHandlerFactory,
            $batchSize,
            $ordered,
            $groupResults,
            $throwIfUnhadled
        );
    }

    protected function consumeBatchQueue(Queue $queue, Processor $processor, DataItemHandler $handler)
    {
        $processor->reset();
        $processor->setSource(new QueueSource($queue))->setCancellation($this->cancellation);
        $futuresQueue = new Queue();
        foreach ($processor as $resultItem) {
            if ($handler->canHandle($resultItem)) {
                $futuresQueue->pushAsync(
                    $handler->handle($resultItem)
                );
            } elseif ($this->throwIfUnhadled) {
                throw new \RuntimeException('DataItem handler not found');
            }
        }
        $futuresQueue->complete();
        $grouped = [];
        foreach (Future::iterate($futuresQueue->iterate()) as $index => $future) {
            if ($this->groupResults) {
                $grouped[] = $future->await();
            } else {
                $this->releaseDataItem($future->await());
            }
        }
        if ($this->groupResults) {
            $this->releaseDataItem(DataItemImpl::fromArray($grouped));
        }
    }

    protected function read(ConcurrentIterator $iterator): void
    {
        $count = 0;
        $this->assertProcessor($processor = ($this->batchProcessorFactory)());
        $this->assertHandler($handler = ($this->resultHandlerFactory)($this->ordered));
        $batchQueue = new Queue($this->batchSize);
        $batchFuture = async($this->consumeBatchQueue(...), $batchQueue, $processor, $handler);
        while ($iterator->continue($this->cancellation)) {
            $item = $iterator->getValue();
            if ($count >= $this->batchSize) {
                $batchQueue?->complete();
                $batchFuture?->await();
                $batchQueue = new Queue($this->batchSize);
                $batchFuture = async($this->consumeBatchQueue(...), $batchQueue, $processor, $handler);
                $count = 0;
            }
            $batchQueue->push($item);
            $count++;
        }
        $batchQueue?->complete();
        $batchFuture?->await();
    }

    protected function processDataItem(DataItem $value): void
    {
    }
}
