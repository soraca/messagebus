<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Persistence;

use Carbon\Carbon;
use Hyperf\DbConnection\Db;

class MySQLPersistence implements PersistenceInterface
{
    protected string $table = 'message_queue';

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
        return Db::table($this->table)->insert([
            'id' => $messageId,
            'payload' => json_encode($message),
            'status' => 'pending',
            'priority' => $priority,
            'queue' => $queue,
            'created_at' => Carbon::now(),
        ]);
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
        return Db::transaction(function () use ($size, $priority, $queue) {
            $messages = Db::table($this->table)
                ->where('status', 'pending')
                ->where('priority', '>=', $priority)
                ->where('queue', $queue)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->take($size)
                ->get();
            $messageIds = $messages->pluck('id')->toArray();
            if (!empty($messageIds)) {
                Db::table($this->table)
                    ->whereIn('id', $messageIds)
                    ->update(['status' => 'processing']);
            }
            return $messages->map(function ($message) {
                return [
                    'message' => json_decode($message->payload, true),
                    'messageId' => $message->id,
                ];
            })->toArray();
        });
    }

    /**
     * 确认消息
     * @param string $messageId
     * @return bool
     */
    public function acknowledge(string $messageId): bool
    {
        return Db::table($this->table)
                ->where('id', $messageId)
                ->update(['status' => 'completed']) > 0;
    }

    /**
     * 重试消息
     * @param string $messageId
     * @param int $priority
     * @param string $queue
     * @return bool
     */
    public function retry(string $messageId, int $priority = 0, string $queue = 'default'): bool
    {
        return Db::table($this->table)
                ->where('id', $messageId)
                ->update([
                    'status' => 'pending',
                    'priority' => $priority,
                    'queue' => $queue
                ]) > 0;
    }

    /**
     * 获取指标
     * @return array
     */
    public function getMetrics(): array
    {
        return Db::table($this->table)
            ->selectRaw('queue, priority, COUNT(*) as count')
            ->groupBy(['queue', 'priority'])
            ->get()
            ->keyBy(function ($item) {
                return "queue_{$item->queue}-priority_{$item->priority}";
            })
            ->pluck('count');
    }
}