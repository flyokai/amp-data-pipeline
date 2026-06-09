<?php

namespace {
    include './bootstrap.php';
}

namespace Flyokai\AmpDataPipeline {

    use Amp\Pipeline\ConcurrentIterator;
    use Amp\Pipeline\Queue;
    use Revolt\EventLoop;
    use Flyokai\AmpDataPipeline\DataItem\DataItem;
    use Flyokai\AmpDataPipeline\DataItem\DataItemImpl;

    class InfiniteSource implements DataSource
    {
        protected ?Queue $queue = null;
        protected ?ConcurrentIterator $iterator = null;
        public function __construct(
        )
        {
        }

        private function read()
        {
            $idx=0;
            while (true) {
                $this->queue->push(DataItemImpl::fromArray([$idx++]));
            }
            $this->queue->complete();
        }

        public function getIterator(): ConcurrentIterator
        {
            if (null === $this->queue) {
                $this->queue = new Queue();
                $this->iterator = $this->queue->iterate();
                EventLoop::queue($this->read(...));
            }
            return $this->iterator;
        }
    }

    class EchoProcessor extends ProcessorAbstract
    {
        public function __construct(
            public readonly int $index
        )
        {
        }

        protected function processDataItem(DataItem $value): void
        {
            var_dump($value->getData());
            $this->queue->push($value);
        }
    }

    $echoProcessor = new EchoProcessor(1);
    $echoProcessor->setSource(new InfiniteSource());

    foreach ($echoProcessor as $item) {
        echo '---'.memory_get_usage()."\n";
    }
}
