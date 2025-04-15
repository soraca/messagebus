<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Persistence;

use Swoole\Coroutine\Channel;

class ChannelPersistence implements PersistenceInterface
{
    protected array $channels = [];

    protected array $messageMap = [];

    protected int $capacity = 1024;

    /**
     * 格式化键
     * @param int $priority
     * @param string $queue
     * @return string
     */
    protected function key(int $priority, string $queue): string
    {
        return sprintf('message_queue:%s:%d', $queue, $priority);
    }

    /**
     * @param int $capacity
     */
    public function __construct(int $capacity = 1024)
    {
        $this->capacity = $capacity;
    }

    /**
     * 发布消息
     * @param string $message
     * @param string $messageId
     * @param int $priority
     * @param string $queue
     * @return bool
     */
    public function publish(mixed $message, string $messageId, int $priority = 0, string $queue = 'default'): bool
    {
        if (count($this->messageMap) >= $this->capacity) {
            return false;
        }
        $this->messageMap[$messageId] = $message;
        $key = $this->key($priority, $queue);
        if (!isset($this->channels[$key])) {
            $this->channels[$key] = new Channel($this->capacity);
        }
        return $this->channels[$key]->push($messageId);
    }

    /**
     * 消费消息
     * @param int $size
     * @param int $priority
     * @param string $queue
     * @return array
     */
    public function consume(int $size = 10, int $priority = 0, string $queue = 'default'): array
    {
        $key = $this->key($priority, $queue);
        $channel = $this->channels[$key] ?? new Channel(0);// 默认空通道
        $messages = [];
        for ($i = 0; $i < $size; $i++) {
            $messageId = $channel->pop();
            if ($messageId === null) {
                break;
            }
            $message = $this->messageMap[$messageId] ?? null;
            if ($message === null) {
                continue; // 消息不存在（可能已被确认）
            }
            $messages[] = ['message' => $message, 'messageId' => $messageId];
        }
        return $messages;
    }

    /**
     * 确认消息
     * @param string $messageId
     * @return bool
     */
    public function acknowledge(string $messageId): bool
    {
        unset($this->messageMap[$messageId]);
        return true;
    }

    /**
     * 重试
     * @param string $messageId
     * @param int $priority
     * @param string $queue
     * @return bool
     */
    public function retry(string $messageId, int $priority = 0, string $queue = 'default'): bool
    {
        if (!isset($this->messageMap[$messageId])) {
            return false;
        }
        $key = $this->key($priority, $queue);
        $channel = $this->channels[$key] ?? new Channel(0);
        return $channel->push($messageId);
    }

    /**
     * 获取指标
     * @return array
     */
    public function getMetrics(): array
    {
        $metrics = ['total' => count($this->messageMap)];
        foreach ($this->channels as $key => $channel) {
            $metrics[$key] = $channel->length();
        }
        return $metrics;
    }
}
