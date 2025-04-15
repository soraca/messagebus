<?php
declare(strict_types = 1);

namespace Soraca\MessageBus\Persistence;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;

class RedisPersistence implements PersistenceInterface
{
    #[Inject]
    protected Redis $redis;

    protected string $queueKey = 'message_queue:%s:%d';// %d优先级，%s队列名

    protected string $storageKey = 'message_storage:%s';

    protected string $lockKey = 'message_lock:%s';

    protected int $lockTimeout = 10;

    protected function key(int $priority, string $queue): string
    {
        return sprintf($this->queueKey, $queue, $priority);
    }

    /**
     * 初始化
     * @param int $lockTimeout
     */
    public function __construct(int $lockTimeout = 10)
    {
        $this->lockTimeout = $lockTimeout;
    }

    /**
     * 发布消息
     * @param mixed $message
     * @param string $messageId
     * @param int $priority
     * @param string $queue
     * @return bool
     */
    public function publish(mixed $message, string $messageId, int $priority = 0, string $queue = 'default'): bool
    {
        $storageKey = sprintf($this->storageKey, $messageId);
        $this->redis->set($storageKey, json_encode($message));
        $queueKey = $this->key($priority, $queue);
        return $this->redis->lPush($queueKey, $messageId) !== false;
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
        $queueKey = $this->key($priority, $queue);
        $messageIds = $this->redis->lRange($queueKey,0, $size - 1);
        if (!$messageIds) {
            return [];
        }
        $messages = [];
        foreach ($messageIds as $messageId) {
            $lockKey = sprintf($this->lockKey, $messageId);
            if (
                $this->redis->setnx($lockKey, 1)
                &&
                $this->redis->expire($lockKey, $this->lockTimeout)
            ) {
                // 获取锁成功，加入消息列表
                $storageKey = sprintf($this->storageKey, $messageId);
                $message = $this->redis->get($storageKey);
                if ($message) {
                    $messages[] = [
                        'message' => json_decode($message, true),
                        'messageId' => $messageId,
                    ];
                }
            }
        }
        // 原子操作：消费后删除消息（仅成功获取锁的消息）
        $this->redis->pipeline(function ($pipe) use ($queueKey, $messageIds) {
            foreach ($messageIds as $messageId) {
                $pipe->lRem($queueKey, $messageId,0); // 每次传入一个messageId
            }
        });

        return $messages;
    }

    /**
     * 确认消息（自动释放锁）
     * @param string $messageId
     * @return bool
     */
    public function acknowledge(string $messageId): bool
    {
        $lockKey = sprintf($this->lockKey, $messageId);
        $this->redis->del($lockKey);//释放锁
        $storageKey = sprintf($this->storageKey, $messageId);
        return $this->redis->del($storageKey) === 1;
    }

    /**
     * 重试消息（重新入队并释放锁）
     * @param string $messageId
     * @param int $priority
     * @param string $queue
     * @return bool
     */
    public function retry(string $messageId, int $priority = 0, string $queue = 'default'): bool
    {
        $lockKey = sprintf($this->lockKey, $messageId);
        $this->redis->del($lockKey);//释放锁
        $queueKey = $this->key($priority, $queue);
        return $this->redis->lPush($queueKey, $messageId) !== false;
    }

    /**
     * 获取指标
     * @return array
     */
    public function getMetrics(): array
    {
        $queues = ['high', 'medium', 'low'];
        $metrics = [];
        foreach ($queues as $queue) {
            foreach (range(0, 2) as $priority) {
                $queueKey = $this->key($priority, $queue);
                $metrics[$queueKey][$priority] = $this->redis->lLen($queueKey);
            }
        }
        $metrics['locks'] = count($this->redis->keys('lock:*'));
        return $metrics;
    }
}
