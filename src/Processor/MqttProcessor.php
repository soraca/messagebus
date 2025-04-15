<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Processor;

class MqttProcessor implements ProcessorInterface
{
    public function enable(string $name): bool
    {
        return $name === 'mqtt';
    }

    public function process(array $message): void
    {
        $topic = $message['topic'];
        $payload = $message['payload'];
        // 实际业务逻辑（如设备状态更新）
        echo "处理MQTT消息: $topic -> $payload\n";
    }
}