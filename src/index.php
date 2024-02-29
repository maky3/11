<?php

use Swoole\Coroutine as co;
use Swoole\Coroutine\Channel;

class ParallelTaskProcessor
{
    private $numThreads;
    private $channel;

    public function __construct(int $numThreads)
    {
        $this->numThreads = $numThreads;
        $this->channel = new Channel($numThreads);
    }
    public function process(iterable $tasks, callable $fn): \Generator
    {
        $resultChannels = [];
        foreach ($tasks as $key => $task) {
            $resultChannels[$key] = new Channel(1);
        }

        foreach ($tasks as $key => $task) {
            go(function () use ($key, $task, $fn, $resultChannels) {
                $this->channel->push(true);

                $result = $fn($task);

                $resultChannels[$key]->push($result);

                $this->channel->pop();
            });
        }

        foreach ($resultChannels as $key => $channel) {
            yield $key => $channel->pop();
        }
    }
}

co\run(function(){
    $ptp = new ParallelTaskProcessor(3);
    $tasks = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $results = $ptp->process($tasks, function ($v) {
        co::sleep(1);
        return $v * 2;
    });

    foreach ($results as $key => $res) {
        echo "Task {$key}: {$res}" . PHP_EOL;
    }
});