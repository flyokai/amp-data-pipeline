<?php

namespace Flyokai\AmpDataPipeline;

use Amp\Pipeline\ConcurrentIterator;
use Flyokai\AmpDataPipeline\DataItem\DataItem;
use Flyokai\AmpDataPipeline\Helper\ProcessorAssertion;
use Flyokai\AmpDataPipeline\Helper\ProcessorHelper;

class ProcessorComposition extends ProcessorAbstract
{
    use ProcessorAssertion;
    use ProcessorHelper;

    protected ?DataSource $lastSource = null;

    /**
     * @param list<Processor> $processors
     */
    public function __construct(
        public readonly array $processors
    )
    {
    }

    /**
     * @param list<Processor> $processors
     */
    public static function selfCreate(array $processors): static
    {
        return new self($processors);
    }

    protected function processDataItem(DataItem $value): void
    {
    }

    public function getIterator(): ConcurrentIterator
    {
        if ($this->lastSource === null) {
            $this->lastSource = $this->getSource();
            foreach ($this->processors as $processor) {
                $processor->setSource($this->lastSource)->setCancellation($this->cancellation);
                $this->lastSource = $processor;
            }
        }
        return $this->lastSource->getIterator();
    }

    public function reset(): static
    {
        foreach ($this->processors as $processor) {
            $processor->reset();
        }
        $this->lastSource = null;
        return $this;
    }

}
