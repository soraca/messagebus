<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Persistence;

interface PersistenceInterface
{
    // 发布消息到指定队列和优先级
    public function publish(mixed $message, string $messageId, int $priority = 0, string $queue = 'default'): bool;

    // 批量消费消息
    public function consume(int $size = 10, int $priority = 0, string $queue = 'default'): array;

    // 确认消息
    public function acknowledge(string $messageId): bool;

    // 重试消息
    public function retry(string $messageId, int $priority = 0, string $queue = 'default'): bool;

    // 监控指标
    public function getMetrics(): array;
}