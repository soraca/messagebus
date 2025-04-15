<?php
declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends  Migration
{
    protected string $connection = 'default';

    protected string $table = 'message_queue';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->comment('消息队列');
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');
            $table->string('id')->primary()->comment('消息ID');
            $table->json('payload')->comment('消息内容');
            $table->string('status')->default('pending')->comment('消息状态（pending/processing/completed）');
            $table->string('queue')->default('default')->comment('消息队列名');
            $table->integer('priority')->default(0)->comment('优先级（0-低，1-中，2-高）');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['queue', 'priority', 'status'], 'queue_priority_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};