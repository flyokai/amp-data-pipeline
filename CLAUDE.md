# flyokai/amp-data-pipeline

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

Async data pipeline framework with composable stages, batch processing, and multicast distribution on AMPHP 3.x.

See [AGENTS.md](AGENTS.md) for detailed documentation.

## Quick Reference

- **Data**: `DataItem` (data + meta arrays), `DataItemHandler` (strategy pattern)
- **Sources**: `ArraySource`, `IteratorSource`, `QueueSource`
- **Processors**: `ProcessorAbstract` (override `processDataItem()`), `SkipProcessor` (pass-through)
- **Composition**: `ProcessorComposition` chains stages sequentially
- **Batch**: `BatchProcessor` — groups items, creates Processor per batch
- **Multicast**: `MultiCastProcessor` — fans out to parallel `CastProcessor`s
- **Concurrency**: Per-processor fiber count + buffer size for backpressure
- **Key rule**: Order not preserved by default — use `$ordered=true` where needed
