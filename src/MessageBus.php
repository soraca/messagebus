<?php
declare(strict_types=1);

namespace Soraca\MessageBus;

use Hyperf\Coroutine\Coroutine;
use Soraca\MessageBus\Persistence\PersistenceInterface;
use Soraca\MessageBus\Processor\ProcessorFactory;
use function Hyperf\Config\config;
use function Hyperf\Coroutine\go;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Throwable;

class MessageBus
{
    #[Inject]
    protected LoggerFactory $logger;

    #[Inject]
    protected ProcessorFactory $processor;

    protected PersistenceInterface $persistence;

    /**
     * @param PersistenceInterface $persistence
     */
    public function __construct(PersistenceInterface $persistence)
    {
        $this->persistence = $persistence;
    }

    /**
     * 发布消息
     * @param mixed $message
     * @return string
     */
    public function publish(mixed $message): string
    {
        $messageId = uniqid('m_', true);
        $type = $message['type'] ?? 'default';
        $queue = config('message.queues.' . $type, 'default');
        $priority = $message['priority'] ?? 0;
        if ($this->persistence->publish($message, $messageId, $priority, $queue)) {
            return $messageId;
        }
        return '';
    }

    /**
     * 消费消息
     * @param int $size
     * @return void
     */
    public function consume(int $size = 10): void
    {
        $queues = array_values(config('message.queues')) ?: ['default'];
        while (true) {
            $all  = [];
            foreach ($queues as $queue) {
                foreach (range(0, 2) as $priority) {
                    $messages = $this->persistence->consume($size, $priority, $queue);
                    $all = array_merge($all, $messages);
                }
            }
            if (empty($all)) {
                Coroutine::sleep(0.);
                continue;
            }
            foreach ($all as $message) {
                go(function () use ($message) {
                    $this->process($message);
                });
            }
        }
    }

    /**
     * 消息处理
     * @param array $message
     * @return void
     */
    protected function process(array $message): void
    {
        $id = $message['messageId'];
        $data = $message['message'];
        $queue = $data['queue'] ?? 'default';
        $priority = $data['priority'] ?? 0;
        try {
            $processor = $this->processor->get($data['type'] ?? '');
            if ($processor) {
                $processor->process($data);
            } else {
                $this->logger->get('message-bus')->error('未找到匹配消息处理器：' . $data['type'] ?? 'unknown');
            }
            $this->persistence->acknowledge($id);
        } catch (Throwable $e) {
            $this->logger->get('message-bus')->error('消息处理失败：' . $e->getMessage());
            $this->persistence->retry($id, $priority, $queue);
        }
    }
}