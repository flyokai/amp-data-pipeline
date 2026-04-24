<?php

namespace Flyokai\AmpDataPipeline\DataCast;

use Amp\Future;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use function Amp\async;
use function Amp\Future\await;
use Flyokai\AmpDataPipeline\ProcessorAbstract;

class MultiCastProcessor extends ProcessorAbstract
{
    /** @var \SplQueue<DataItem> */
    protected \SplQueue $waitingQueue;

    /**
     * @param list<\Closure():CastProcessor> $castProcessorFactories
     * @param \Closure():MultiCastConsumer|null $consumerFactory
     */
    public function __construct(
        protected readonly array $castProcessorFactories,
        protected bool           $groupResults = false,
        protected int            $groupBufferSize = 0,
        protected ?\Closure      $consumerFactory = null
    )
    {
        $this->waitingQueue = new \SplQueue();
        $this->consumerFactory ??= MultiCastConsumerImpl::selfCreate(...);
    }

    /**
     * @param list<\Closure():CastProcessor> $castProcessorFactories
     * @param \Closure():MultiCastConsumer|null $consumerFactory
     */
    public static function selfCreate(
        array     $castProcessorFactories,
        bool      $groupResults = false,
        int       $groupBufferSize = 0,
        ?\Closure $consumerFactory = null
    ): static
    {
        return new self(
            $castProcessorFactories,
            $groupResults,
            $groupBufferSize,
            $consumerFactory
        );
    }

    protected function read(ConcurrentIterator $iterator): void
    {
        $castProcessors = [];
        /** @var \SplObjectStorage<CastProcessor, Queue> $castQueues */
        $castQueues = new \SplObjectStorage();
        /** @var \SplObjectStorage<CastProcessor, Future> $castFutures */
        $castFutures = new \SplObjectStorage();
        /** @var \SplObjectStorage<DataItem, \SplObjectStorage> $castResults */
        $castResults = new \SplObjectStorage();
        $waitingQueue = new \SplQueue();
        foreach ($this->castProcessorFactories as $castProcessorFactory) {
            $castProcessors[] = $castProcessorFactory();
        }
        foreach ($castProcessors as $processor) {
            $castQueues[$processor] = $queue = new Queue($this->bufferSize);
            $consumer = ($this->consumerFactory)(
                processor: $processor,
                queue: $queue,
                castProcessors: $castProcessors,
                castResults: $castResults,
                waitingQueue: $waitingQueue,
                releaseDataItem: $this->releaseDataItem(...),
                groupResults: $this->groupResults,
                groupBufferSize: $this->groupBufferSize
            );
            $castFutures[$processor] = async($consumer->consume(...));
        }

        $pushCastQueue = static function (CastProcessor $processor, Queue $castQueue, mixed $value): void {
            $castQueue->push($value);
        };
        $completeCastQueue = static function (CastProcessor $processor, Queue $castQueue): void {
            $castQueue->complete();
        };

        while ($iterator->continue($this->cancellation)) {
            $value = $iterator->getValue();
            $this->tapObjectStorage($castQueues, $pushCastQueue, $value);
        }

        $this->tapObjectStorage($castQueues, $completeCastQueue);

        $castFutures = $this->collectObjectStorage(
            $castFutures,
            static fn(CastProcessor $processor, Future $castFuture): Future => $castFuture
        );
        await($castFutures);
    }

    protected function processDataItem(DataItem $value): void
    {
    }

}
