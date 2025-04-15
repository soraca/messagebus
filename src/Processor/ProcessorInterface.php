<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Processor;

interface ProcessorInterface
{
    public function enable(string $name): bool;

    public function process(array $message): void;
}