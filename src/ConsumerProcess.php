<?php
declare(strict_types=1);

namespace Soraca\MessageBus;

use Soraca\MessageBus\Persistence\PersistenceFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Process\Annotation\Process;

#[Process(nums: 1, name: 'consumer-process', redirectStdinStdout: false, pipeType: 2, enableCoroutine: true)]
class ConsumerProcess extends AbstractProcess
{
    public function handle(): void
    {
        $bus = new MessageBus(PersistenceFactory::create());
        $bus->consume();
    }
}