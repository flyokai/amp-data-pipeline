<?php

namespace Flyokai\AmpDataPipeline;

use Amp\Cancellation;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Internal\ConcurrentQueueIterator;
use Amp\Pipeline\Queue;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\Helper\ProcessorAssertion;
use Flyokai\AmpDataPipeline\Helper\ProcessorHelper;
use function Amp\async;

abstract class ProcessorAbstract implements Processor
{
    use ProcessorAssertion;
    use ProcessorHelper;

    protected int $bufferSize = 0;
    protected int $concurrency = 1;

    protected ?DataSource $source = null;

    /**
     * @var Queue<DataItem>|null
     */
    protected ?Queue $queue = null;

    /**
     * @var ConcurrentQueueIterator<DataItem>|null
     */
    protected ?ConcurrentQueueIterator $iterator;

    protected ?Cancellation $cancellation = null;

    public function setSource(DataSource $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getSource(): DataSource
    {
        return $this->source ?? throw new \RuntimeException('Undefined processor source');
    }

    public function setConcurrency(int $concurrency): static
    {
        $this->concurrency = $concurrency;
        return $this;
    }

    public function setBufferSize(int $bufferSize): static
    {
        $this->bufferSize = $bufferSize;
        return $this;
    }

    public function setCancellation(?Cancellation $cancellation): static
    {
        $this->cancellation = $cancellation;
        return $this;
    }

    /**
     * @param ConcurrentIterator<DataItem> $iterator
     * @return void
     */
    protected function read(ConcurrentIterator $iterator): void
    {
        while ($iterator->continue($this->cancellation)) {
            $this->processDataItem($iterator->getValue());
        }
    }

    /**
     * @param DataItem $value
     * @return void
     */
    abstract protected function processDataItem(DataItem $value): void;

    protected function releaseDataItem(DataItem $value): void
    {
        $this->queue->push($value);
    }

    public function getIterator(): ConcurrentIterator
    {
        if (!isset($this->queue)) {
            $bufferSize = $this->bufferSize;
            if ($bufferSize === 0) {
                $bufferSize = max($this->concurrency, $this->bufferSize);
            }
            $this->queue = $queue = new Queue($bufferSize);
            $this->iterator = $this->queue->iterate();
            $source = $this->getSource()->getIterator();
            $futures = [];
            for ($i=0; $i<$this->concurrency; $i++) {
                $futures[] = async($this->read(...), $source);
            }
            $this->trackQueueFutures($queue, $source, $futures);
        }
        return $this->iterator;
    }

    public function reset(): static
    {
        $this->assertQueueComplete($this->queue);
        $this->queue = null;
        $this->iterator = null;
        return $this;
    }

    public function run(?callable $itemCallback=null, ?callable $beforeRun=null, ?callable $afterRun=null): void
    {
        if ($beforeRun) $beforeRun();
        foreach ($this as $value) {
            if ($itemCallback) $itemCallback($value);
        }
        if ($afterRun) $afterRun();
    }

}
