<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Persistence;

use function Hyperf\Config\config;
class PersistenceFactory
{
    public static function create(): PersistenceInterface
    {
        $driver = config('message.persistence');
        return match ($driver) {
            'redis' => new RedisPersistence(),
            'mysql' => new MySQLPersistence(),
            default => new ChannelPersistence(),
        };
    }
}
