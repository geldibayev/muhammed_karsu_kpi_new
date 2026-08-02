<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GetAiQueueMetrics
{
    /** @return array{supported: bool, connection: string, total: int|null, reserved: int|null, oldest_at: CarbonInterface|null} */
    public function handle(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", 'unknown');

        if ($driver !== 'database') {
            return [
                'supported' => false,
                'connection' => $connection,
                'total' => null,
                'reserved' => null,
                'oldest_at' => null,
            ];
        }

        $database = config("queue.connections.{$connection}.connection");
        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $databaseConnection = is_string($database) ? $database : null;

        if (! Schema::connection($databaseConnection)->hasTable($table)) {
            return [
                'supported' => true,
                'connection' => $connection,
                'total' => 0,
                'reserved' => 0,
                'oldest_at' => null,
            ];
        }

        $query = DB::connection($databaseConnection)
            ->table($table)
            ->where('queue', ProcessAiDatumEvaluation::QUEUE);
        $oldestTimestamp = (clone $query)->min('created_at');

        return [
            'supported' => true,
            'connection' => $connection,
            'total' => (clone $query)->count(),
            'reserved' => (clone $query)->whereNotNull('reserved_at')->count(),
            'oldest_at' => is_numeric($oldestTimestamp)
                ? CarbonImmutable::createFromTimestamp((int) $oldestTimestamp)
                : null,
        ];
    }
}
