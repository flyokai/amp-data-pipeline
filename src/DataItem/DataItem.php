<?php

namespace Flyokai\AmpDataPipeline\DataItem;

interface DataItem
{
    public static function fromArray(array $data, array $meta): static;

    public function getMeta(mixed $key = null): mixed;
    public function setMeta(array $meta): static;
    public function addMeta(mixed $value, mixed $key): static;

    public function getData(mixed $key = null): mixed;
    public function setData(array $data): static;
    public function addData(mixed $value, mixed $key): static;
}
