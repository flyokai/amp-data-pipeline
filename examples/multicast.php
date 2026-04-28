<?php

namespace {
    include './bootstrap.php';
}

namespace Flyokai\AmpDataPipeline {

    use Amp\Pipeline\ConcurrentIterator;
    use Amp\Pipeline\Queue;
    use Revolt\EventLoop;
    use Flyokai\AmpDataPipeline\DataCast\CastProcessor;
    use Flyokai\AmpDataPipeline\DataCast\MultiCastProcessor;
    use Flyokai\AmpDataPipeline\DataItem\DataItem;
    use Flyokai\AmpDataPipeline\DataItem\DataItemImpl;
    use Flyokai\AmpDataPipeline\DataSource\ArraySource;
    use Flyokai\AmpDataPipeline\Helper\ProcessorAssertion;

    class ExtractColumns implements CastProcessor {
        use ProcessorAssertion;
        public function __construct(
            public readonly array $columns
        ) {
        }

        public function cast(ConcurrentIterator $source, \Closure $acceptCastItem): void
        {
            $this->assertSourceIterator($source);
            /** @var DataItem $item */
            foreach ($source as $item) {
                $queue = new Queue(10);
                $acceptCastItem($queue->iterate(), $item, $this);
                $data = $item->getData();
                foreach ($this->columns as $column) {
                    $queue->push(DataItemImpl::fromArray([$data[$column] ?? null], [$column]));
                }
                $queue->complete();
            }
        }

    }

    function run()
    {
        $multicast = new MultiCastProcessor(
            castProcessorFactories: [
                fn() => new ExtractColumns([0,2]),
                fn() => new ExtractColumns([3]),
                fn() => new ExtractColumns([1,4]),
            ],
            groupResults: true,
            groupBufferSize: 10
        );

        $composition = new ProcessorComposition([
            $multicast->setConcurrency(5)
        ]);

        $input = [];

        for ($i = 0; $i < 500; $i++) {
            $input[] = DataItemImpl::fromArray(['aa-'.$i,'bb-'.$i,'cc-'.$i,'dd-'.$i,'ee-'.$i]);
        }

        $composition->setSource(new ArraySource($input));

        foreach ($composition as $value) {
            var_dump($value->getData());
        }

        $composition->reset();

        echo "\n\n=========RESET========\n\n";

        /*$composition->setSource(new ArraySource($input));

        foreach ($composition as $value) {
            var_dump($value->getData());
        }*/
    }

    run();

}
