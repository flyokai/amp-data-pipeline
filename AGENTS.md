# flyokai/amp-data-pipeline

Async data pipeline framework built on AMPHP 3.x. Composable, concurrent processing through pipeline stages with flexible sources, batch processing, and multicast distribution.

## Core Architecture

### DataItem System

**DataItem** (interface) — immutable-like pipeline data wrapper:
- `getData(?$key)` / `getMeta(?$key)` — payload and metadata access
- `setData(key, value)` / `setMeta(key, value)` — mutation (returns static)
- `fromArray(array $data, array $meta): DataItem` — factory

**DataItemImpl** — default implementation with array-based data and meta.

**DataItemHandler** (interface) — strategy for handling specific items:
- `canHandle(DataItem $value): bool` — predicate
- `handle(DataItem $value): Future` — async processing

**HandlerComposition** — composes multiple handlers with mutual exclusion (only one handler per item). Optional `$ordered` flag maintains batch ordering via Mutex/Sequence.

### Data Sources

All implement `DataSource` interface (`getIterator(): ConcurrentIterator`):

- **ArraySource** — wraps PHP array into `ConcurrentArrayIterator`
- **IteratorSource** — wraps any iterable into `ConcurrentIterableIterator`
- **QueueSource** — wraps AMPHP `Queue` for push-based streaming input

### Processors

**Processor** (interface, extends DataSource) — a pipeline stage:
- `setSource(DataSource)` — input
- `setConcurrency(int)` — number of concurrent fibers
- `setBufferSize(int)` — output queue depth
- `setCancellation(Cancellation)` — for graceful shutdown
- `getIterator(): ConcurrentIterator` — lazy evaluation, starts processing
- `run(?itemCallback, ?beforeRun, ?afterRun)` — blocking execution
- `reset()` — clear state for reuse

**ProcessorAbstract** — base implementation:
- Creates internal `Queue` with configurable buffer
- Spawns `$concurrency` fibers via `Amp\async()`
- Each fiber runs `read()` → iterates source → calls `processDataItem()`
- `releaseDataItem(DataItem)` pushes to output queue
- Subclasses implement `abstract processDataItem(DataItem): void`

**SkipProcessor** — pass-through (releases items unchanged).

**ProcessorComposition** — chains multiple processors sequentially. Each processor's input is the previous processor's output. Propagates cancellation to all.

## Pipeline Patterns

### Linear Pipeline
```
ArraySource → ProcessorA (concurrency=4) → ProcessorB → Output
```
Built with `ProcessorComposition`:
```php
$pipeline = new ProcessorComposition([$processorA, $processorB]);
$pipeline->setSource(new ArraySource($data));
$pipeline->run(function(DataItem $item) { /* consume */ });
```

### Batch Processing

`Batch\BatchProcessor` groups items into fixed-size batches:

```php
new BatchProcessor(
    batchProcessorFactory: fn() => new MyBatchProcessor(),
    resultHandlerFactory: fn() => new MyResultHandler(),
    batchSize: 100,
    ordered: false,      // preserve order across batches
    groupResults: false,  // merge batch results into single item
    throwIfUnhandled: true
)
```

Flow: accumulate items → create Processor per batch → process → route results via DataItemHandler.

### Multicast Distribution

`DataCast\MultiCastProcessor` fans out each item to multiple parallel cast processors:

```php
new MultiCastProcessor(
    castProcessorFactories: [fn() => new CastA(), fn() => new CastB()],
    groupResults: true,     // merge outputs per item
    groupBufferSize: 10,    // backpressure limit
)
```

**CastProcessor** (interface) — alternative to Processor for cast operations:
- `cast(ConcurrentIterator $source, Closure $acceptCastItem): void`

**MultiCastConsumer** aggregates results from all cast processors per item.

## Concurrency Model

- **Inter-stage**: Processors in composition run as chained iterators (pull-based)
- **Intra-stage**: `$concurrency` controls fiber count within a processor
- **Buffer**: `$bufferSize` decouples producer/consumer (0 = same as concurrency)
- **Multicast**: All cast processors execute simultaneously per item
- **Backpressure**: Queue buffer size + `groupBufferSize` limit memory growth

## Gotchas

- **Queue completion required**: `reset()` asserts queue is complete. Resetting incomplete queues throws RuntimeException.
- **Order not preserved by default**: Concurrent fibers process items in any order. Use `$ordered=true` on BatchProcessor or HandlerComposition for ordering.
- **Handler exclusivity**: HandlerComposition enforces one handler per item. Multiple matching handlers throw RuntimeException.
- **CastProcessor ≠ Processor**: Different interfaces. CastProcessor receives raw `ConcurrentIterator` and must manage its own Queue.
- **Reflection in error handling**: `errorDisposeQueue()` uses reflection to access Queue internals — could break on AMPHP version changes.
- **groupBufferSize=0 means unlimited**: High throughput with multicast grouping can grow memory unbounded.
- **Cancellation must propagate**: ProcessorComposition passes cancellation to children, but custom compositions must do this explicitly.
