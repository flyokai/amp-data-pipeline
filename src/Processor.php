<?php

namespace Flyokai\AmpDataPipeline;

use Amp\Cancellation;
use Amp\Pipeline\ConcurrentIterator;
use Flyokai\AmpDataPipeline\DataItem\DataItem;

interface Processor extends DataSource
{
    /**
     * @param DataSource $source
     * @return self
     */
    public function setSource(DataSource $source): static;

    /**
     * @param int $concurrency
     * @return self
     */
    public function setConcurrency(int $concurrency): static;

    /**
     * @param int $bufferSize
     * @return self
     */
    public function setBufferSize(int $bufferSize): static;

    public function setCancellation(Cancellation $cancellation): static;

    /**
     * @return ConcurrentIterator<DataItem>
     */
    public function getIterator(): ConcurrentIterator;

    public function reset(): static;

    public function run(?callable $itemCallback=null, ?callable $beforeRun=null, ?callable $afterRun=null): void;
}
