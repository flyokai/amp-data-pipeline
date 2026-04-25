<?php
/**
 * amp-data-pipeline example — a 2-stage pipeline with concurrency.
 *
 * Stage 1 (Double): multiplies the value by 2, with simulated I/O delay.
 * Stage 2 (Format): turns the value into a string.
 *
 * Run from project root:
 *   php vendor/flyokai/amp-data-pipeline/examples/concurrent_double.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Flyokai\AmpDataPipeline\ArraySource;
use Flyokai\AmpDataPipeline\DataItem;
use Flyokai\AmpDataPipeline\DataItemImpl;
use Flyokai\AmpDataPipeline\ProcessorAbstract;
use Flyokai\AmpDataPipeline\ProcessorComposition;
use function Amp\delay;

final class Double extends ProcessorAbstract
{
    protected function processDataItem(DataItem $item): void
    {
        delay(0.01);   // simulate I/O — non-blocking
        $item->setData('value', $item->getData('value') * 2);
        $this->releaseDataItem($item);
    }
}

final class Format extends ProcessorAbstract
{
    protected function processDataItem(DataItem $item): void
    {
        $item->setData('value', sprintf('value=%d', $item->getData('value')));
        $this->releaseDataItem($item);
    }
}

$source = new ArraySource(array_map(
    fn(int $n) => DataItemImpl::fromArray(['value' => $n], []),
    range(1, 8),
));

$double = (new Double())->setConcurrency(4)->setBufferSize(8);
$format = (new Format())->setConcurrency(2);

$pipeline = new ProcessorComposition([$double, $format]);
$pipeline->setSource($source);

$start = microtime(true);
$pipeline->run(function (DataItem $item) {
    echo $item->getData('value'), "\n";
});

printf("Done in %.3fs\n", microtime(true) - $start);
