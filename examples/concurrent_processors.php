<?php

namespace {
    include './bootstrap.php';
}

namespace Flyokai\AmpDataPipeline {

    use Flyokai\AmpDataPipeline\DataItem\DataItem;
    use Flyokai\AmpDataPipeline\DataItem\DataItemImpl;
    use Flyokai\AmpDataPipeline\DataSource\ArraySource;

    class EchoProcessor extends ProcessorAbstract {
        public function __construct(
            public readonly int $index
        ) {
        }
        protected function processDataItem(DataItem $value): void
        {
            echo sprintf("Processor #%d (fiber #%d) accepted value: %s\n",
                $this->index, spl_object_id(\Fiber::getCurrent()), var_export($value->getData(), true)
            );
            $this->queue->push($value);
        }
    }

    $composition = new ProcessorComposition([
        (new EchoProcessor(1))->setConcurrency(2),
        new EchoProcessor(2),
        new EchoProcessor(3),
    ]);

    $input = [
        DataItemImpl::fromArray(['one']),
        DataItemImpl::fromArray(['two']),
        DataItemImpl::fromArray(['three']),
        DataItemImpl::fromArray(['four']),
        DataItemImpl::fromArray(['five']),
    ];

    $composition->setSource(new ArraySource($input));

    foreach ($composition as $value) {}

}
