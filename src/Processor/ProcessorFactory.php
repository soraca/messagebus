<?php
declare(strict_types=1);

namespace Soraca\MessageBus\Processor;

use Hyperf\Contract\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;


class ProcessorFactory
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 获取处理器
     * @param string $name
     * @return ProcessorInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get(string $name): ?ProcessorInterface
    {
        $processors = $this->find();
        foreach ($processors as $processor) {
            $processor = $this->container->get($processor);
            if ($processor->enable($name)) {
                return $processor;
            }
        }
        return null;
    }

    /**
     * 自动加载处理器
     * @return array
     */
    protected function find(): array
    {
        $reflector = new ReflectionClass(__CLASS__);
        $directory = dirname($reflector->getFileName());
        $files = scandir($directory);
        $processors = [];
        foreach ($files as $file) {
            if (str_ends_with($file, 'Processor.php')) {
                $class = __NAMESPACE__ . '\\' . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($class)) {
                    $reflection = new ReflectionClass($class);
                    if ($reflection->implementsInterface(ProcessorInterface::class)) {
                        $processors[] = $class;
                    }
                }
            }
        }
        return $processors;
    }

}