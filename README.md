# flyokai/amp-data-pipeline

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

> Composable async data pipelines on AMPHP 3.x — sources, processors, batching, multicast, with explicit concurrency and backpressure controls.

A small toolkit for building pull-based concurrent processing pipelines. You wire together a `DataSource` and one or more `Processor`s; each processor controls its own fiber count and output queue size. Batch and multicast operators give you the rest.

## Features

- **`DataItem`** wrapper — `data` + `meta` arrays, immutable-ish
- **Sources** — `ArraySource`, `IteratorSource`, `QueueSource`
- **Processors** — `ProcessorAbstract` (override `processDataItem()`), `SkipProcessor`
- **Composition** — `ProcessorComposition` chains stages sequentially
- **Batching** — `Batch\BatchProcessor` groups items and creates a per-batch processor
- **Multicast** — `DataCast\MultiCastProcessor` fans out each item to parallel cast processors
- **Per-stage concurrency** — fiber count, buffer size, optional ordering
- **Cancellation** propagated through the chain

## Installation

```bash
composer require flyokai/amp-data-pipeline
```

## Quick start

```php
use Flyokai\AmpDataPipeline\{ArraySource, DataItem, ProcessorAbstract, ProcessorComposition};

final class Upper extends ProcessorAbstract
{
    protected function processDataItem(DataItem $item): void
    {
        $item->setData('value', strtoupper($item->getData('value')));
        $this->releaseDataItem($item);
    }
}

$source = new ArraySource([
    DataItem::fromArray(['value' => 'alice'], []),
    DataItem::fromArray(['value' => 'bob'],   []),
]);

$pipeline = new ProcessorComposition([new Upper()]);
$pipeline->setSource($source);

$pipeline->run(function (DataItem $item) {
    echo $item->getData('value'), "\n";   // ALICE, BOB
});
```

## Concepts

### `DataItem`

```php
$item->getData('key');          // payload access
$item->setData('key', 'value'); // returns mutated
$item->getMeta();               // metadata bag
```

### Sources

| Class | Use case |
|-------|----------|
| `ArraySource` | wraps a PHP array (`ConcurrentArrayIterator`) |
| `IteratorSource` | wraps any `iterable` |
| `QueueSource` | wraps an AMPHP `Queue` for push-based input |

### Processors

`ProcessorAbstract` gives you:

- `setConcurrency(int)` — fiber count inside the stage
- `setBufferSize(int)` — output queue depth (0 = same as concurrency)
- `setCancellation(Cancellation)` — graceful shutdown
- `releaseDataItem(DataItem)` — push to output

```php
new MyProcessor()
    ->setConcurrency(8)
    ->setBufferSize(16);
```

### Linear pipeline

```php
$pipeline = new ProcessorComposition([
    new PrepareProcessor(),
    new ValidateProcessor(),
    new SaveProcessor(),
]);
$pipeline->setSource(new ArraySource($rows));
$pipeline->run(/* optional itemCallback */);
```

### Batching

```php
use Flyokai\AmpDataPipeline\Batch\BatchProcessor;

$batcher = new BatchProcessor(
    batchProcessorFactory: fn() => new SaveBatchProcessor(),
    resultHandlerFactory:  fn() => new ResultRouter(),
    batchSize: 100,
    ordered: false,        // true → preserve order across batches
    groupResults: false,   // true → merge batch results into one DataItem
    throwIfUnhandled: true,
);
```

Items accumulate up to `batchSize`, a fresh processor is built for each batch, and results are routed through a `DataItemHandler` strategy.

### Multicast

```php
use Flyokai\AmpDataPipeline\DataCast\MultiCastProcessor;

$cast = new MultiCastProcessor(
    castProcessorFactories: [
        fn() => new IndexInOpensearch(),
        fn() => new WriteToCache(),
    ],
    groupResults: true,
    groupBufferSize: 10,
);
```

Each input item is delivered to every cast processor in parallel; outputs are aggregated by `MultiCastConsumer`.

### Handler strategies

`DataItemHandler` is the strategy for handling specific items:

```php
interface DataItemHandler {
    public function canHandle(DataItem $item): bool;
    public function handle(DataItem $item): Future;
}
```

`HandlerComposition` enforces **mutual exclusion** — exactly one handler per item; multiple matches throw. Pass `$ordered = true` to preserve item order via Mutex / Sequence.

## Concurrency model

- **Inter-stage**: a `ProcessorComposition` chains iterators (pull-based, demand-driven).
- **Intra-stage**: `concurrency` controls the number of fibers servicing the queue inside a processor.
- **Buffer**: `bufferSize` decouples producer/consumer (set to 0 to mirror concurrency).
- **Multicast**: every cast processor fires simultaneously per item.
- **Backpressure**: queue + `groupBufferSize` cap memory growth.

## Gotchas

- **Order is not preserved by default.** Use `$ordered = true` on `BatchProcessor` or `HandlerComposition` if you need it.
- **`reset()` requires queue completion.** Resetting an incomplete queue throws `RuntimeException`.
- **Handler exclusivity** — a `HandlerComposition` enforces one handler per item. Two matching handlers throw.
- **`CastProcessor` ≠ `Processor`.** Cast processors receive a raw `ConcurrentIterator` and own their queues.
- **`groupBufferSize = 0` is unlimited.** Multicast with grouping can grow memory unboundedly.
- **Cancellation must propagate.** `ProcessorComposition` propagates to children automatically, but custom compositions need to do this explicitly.
- **Reflection in error handling** — `errorDisposeQueue()` reaches into `Queue` internals via reflection. AMPHP version updates may break it.

## See also

- [`flyokai/indexer`](../indexer/README.md) — full reindex uses pipelines
- Bulk data-import services typically use this as their processing core (`DataSource → BatchProcessor → Stage Pipeline`).

## License

MIT
