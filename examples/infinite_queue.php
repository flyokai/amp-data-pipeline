<?php

include './bootstrap.php';

$queue = new \Amp\Pipeline\Queue();

\Revolt\EventLoop::queue(static function () use ($queue) {
    $idx = 0;
    while (true) $queue->push([$idx++]);
});

$iterator = $queue->iterate();

foreach ($iterator as $item) {
    echo '---'.memory_get_usage()."\n";
}