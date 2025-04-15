<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Processor;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;

class WebsocketProcessor implements ProcessorInterface
{
    #[Inject]
    protected LoggerFactory $logger;

    public function enable(string $name): bool
    {
        return $name === 'websocket';
    }

    public function process(array $message): void
    {
        $data = $message['data'];
        // 实际业务逻辑（如用户指令处理）
        echo "处理WebSocket消息: " . json_encode($data) . "\n";
    }
}