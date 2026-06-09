<?php

use Flyokai\AmpDataPipeline\EmptyTask;

include './bootstrap.php';

$worker = \Amp\Parallel\Worker\createWorker();

while (true) {
    $worker->submit(new EmptyTask(random_bytes(1000)))->await();
    echo memory_get_usage()."\n";
}