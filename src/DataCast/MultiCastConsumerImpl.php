<?php

namespace Flyokai\AmpDataPipeline\DataCast;

use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\DisposedException;
use Amp\Pipeline\Queue;
use Revolt\EventLoop;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\DataItem\DataItemImpl;
use Flyokai\AmpDataPipeline\DataSource\IteratorSource;
use function Amp\async;
use function Flyokai\AmpDataPipeline\errorDisposeQueue;

class MultiCastConsumerImpl implements MultiCastConsumer
{
    /**
     * @var \Closure(CastProcessor,DataItem,ConcurrentIterator):void
     */
    protected \Closure $processCastItems;
    /**
     * @var \Closure(ConcurrentIterator):void
     */
    protected \Closure $releaseCastItems;

    public function __construct(
        protected CastProcessor $processor,
        protected Queue $queue,
        protected array $castProcessors,
        protected \SplObjectStorage $castResults,
        protected \SplQueue $waitingQueue,
        /** @var \Closure(DataItem):void */
        protected \Closure $releaseDataItem,
        protected bool $groupResults,
        protected int $groupBufferSize
    )
    {
        $releaseCastItem = $this->releaseDataItem;
        $this->releaseCastItems = static function ($castItems) use($releaseCastItem): void {
            foreach ($castItems as $castItem) {
                $releaseCastItem($castItem);
            }
        };
        $this->processCastItems = static function (
            CastProcessor $castProcessor, DataItem $origItem, ConcurrentIterator $castItems
        ) use ($castResults, $castProcessors, $waitingQueue, $releaseCastItem): void {
            $result = [];
            foreach ($castItems as $castItem) {
                $result[] = $castItem;
            }
            /** @var \SplObjectStorage $__castResults */
            $__castResults = $castResults[$origItem];
            $__castResults[$castProcessor] = $result;
            if ($__castResults->count() === count($castProcessors)) {
                $result = [];
                foreach ($__castResults as $__castProc) {
                    $result = array_merge($result, $__castResults[$__castProc]);
                }
                $castResults->detach($origItem);
                $releaseCastItem(DataItemImpl::fromArray($result));
                if (!$waitingQueue->isEmpty()) {
                    $suspension = $waitingQueue->dequeue();
                    $suspension->resume();
                }
            }
        };
    }

    public static function selfCreate(
        CastProcessor $processor,
        Queue $queue,
        array $castProcessors,
        \SplObjectStorage $castResults,
        \SplQueue $waitingQueue,
        /** @var \Closure(DataItem):void */
        \Closure $releaseDataItem,
        bool $groupResults,
        int $groupBufferSize
    ): static {
        return new self(
            $processor,
            $queue,
            $castProcessors,
            $castResults,
            $waitingQueue,
            $releaseDataItem,
            $groupResults,
            $groupBufferSize
        );
    }

    public function consume(): void
    {
        try {
            $queue = $this->queue;
            $source = $this->queue->iterate();
            $this->processor->cast($source, $this->acceptCastItem(...));
        } catch (\Throwable $throwable) {
            $queue->error(new DisposedException(previous: $throwable));
            $source->dispose();
        }
    }

    /**
     * @throws \RuntimeException
     */
    protected function acceptCastItem(
        ConcurrentIterator $castItems, ?DataItem $origItem=null, ?CastProcessor $castProcessor=null): void
    {
        if ($this->groupResults) {
            if ($origItem === null) {
                throw new \RuntimeException(
                    'MultiCastConsumer::acceptCastItem expects $origItem parameter'
                );
            }
            if ($castProcessor === null) {
                throw new \RuntimeException(
                    'MultiCastConsumer::acceptCastItem expects $castProcessor parameter'
                );
            }
            if (!$this->castResults->contains($origItem) && $this->castResults->count()>$this->groupBufferSize) {
                $suspension = EventLoop::getSuspension();
                $this->waitingQueue->enqueue($suspension);
                $suspension->suspend();
            }
            if (!$this->castResults->contains($origItem)) {
                $this->castResults[$origItem] = new \SplObjectStorage();
            }
            EventLoop::queue($this->processCastItems, $castProcessor, $origItem, $castItems);
        } else {
            EventLoop::queue($this->releaseCastItems, $castItems);
        }
    }

}
