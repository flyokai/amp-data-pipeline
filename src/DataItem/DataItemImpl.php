<?php

namespace Flyokai\AmpDataPipeline\DataItem;

class DataItemImpl implements DataItem
{
    public function __construct(
        protected array $data = [],
        protected array $meta = []
    ) {
    }

    public static function fromArray(array $data, array $meta = []): static
    {
        return new static(data: $data, meta: $meta);
    }

    public function getData($key=null): mixed
    {
        return $key !== null ? $this->data[$key]??null : $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getMeta(mixed $key = null): mixed
    {
        return $key !== null ? $this->meta[$key]??null : $this->meta;
    }

    public function setMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    public function addMeta(mixed $value, mixed $key): static
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function addData(mixed $value, mixed $key = null): static
    {
        if ($key === null) {
            $this->data[] = $value;
        } else {
            $this->data[$key] = $value;
        }
        return $this;
    }


}
