<?php

namespace App\Console\Commands;

use App\Actions\DescribeAiFailure;
use App\Models\Criterion;
use App\Models\Datum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseAiQueue extends Command
{
    protected $signature = 'kpi:ai:diagnose';

    protected $description = 'AI konfiguratsiyasi va queue holatini maxfiy ma’lumotlarni chiqarmasdan tekshiradi';

    public function handle(DescribeAiFailure $describeAiFailure): int
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", 'unknown');
        $unprocessedResources = Datum::query()
            ->whereIn('status', ['received', 'checking'])
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            )
            ->whereDoesntHave(
                'histories',
                fn (Builder $query): Builder => $query->where('message_type', 'ai_evaluation'),
            )
            ->count();

        $queueMetrics = $this->queueMetrics($connection, $driver);
        $failedMetrics = $this->failedMetrics($describeAiFailure);

        $hasGeminiKey = $this->hasConfiguredGeminiKey();
        $workerHeartbeat = Cache::get('kpi:ai-worker:last-seen-at');
        $workerLastSeenAt = is_string($workerHeartbeat)
            ? CarbonImmutable::parse($workerHeartbeat)
            : null;

        $this->table(['Tekshiruv', 'Natija'], [
            ['Muhit', app()->environment()],
            ['Queue connection', $connection],
            ['Queue driver', $driver],
            ['Gemini API kaliti', $hasGeminiKey ? 'Mavjud' : 'Mavjud emas yoki placeholder'],
            ['Gemini timeout', config('gemini.request_timeout').' soniya'],
            ['Gemini rate-limit', config('kpi.ai_requests_per_minute').' so‘rov/daqiqa'],
            ['AI kriteriyalar', Criterion::query()->where('checking', 'ai')->count()],
            ['AI natijasiz resurslar', $unprocessedResources],
            ['Queue’dagi AI joblar', $queueMetrics['total']],
            ['Ishlanayotgan AI joblar', $queueMetrics['reserved']],
            ['Eng eski AI job', $queueMetrics['oldest']],
            ['Worker heartbeat', $workerLastSeenAt instanceof CarbonInterface
                ? $workerLastSeenAt->format('d.m.Y H:i:s')
                : 'Hali qayd etilmagan'],
            ['Failed AI joblar', $failedMetrics['total']],
            ['Oxirgi failed vaqt', $failedMetrics['latest_at']],
            ['Oxirgi xavfsiz xato sababi', $failedMetrics['reason']],
        ]);

        $this->newLine();
        $this->line($this->diagnosis(
            $hasGeminiKey,
            $unprocessedResources,
            $queueMetrics,
            $failedMetrics,
            $workerLastSeenAt,
        ));

        return self::SUCCESS;
    }

    private function hasConfiguredGeminiKey(): bool
    {
        $key = mb_strtolower(trim((string) config('gemini.api_key')), 'UTF-8');

        return ! in_array($key, ['', 'xx', 'xxx', 'change-me', 'your-api-key'], true);
    }

    /** @return array{total: int|string, reserved: int|string, oldest: string} */
    private function queueMetrics(string $connection, string $driver): array
    {
        if ($driver !== 'database') {
            return ['total' => 'N/A', 'reserved' => 'N/A', 'oldest' => 'N/A'];
        }

        $database = config("queue.connections.{$connection}.connection");
        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $schema = Schema::connection(is_string($database) ? $database : null);

        if (! $schema->hasTable($table)) {
            return ['total' => 0, 'reserved' => 0, 'oldest' => 'jobs jadvali mavjud emas'];
        }

        $query = DB::connection(is_string($database) ? $database : null)
            ->table($table)
            ->where('queue', 'ai-evaluations');
        $oldestTimestamp = (clone $query)->min('created_at');

        return [
            'total' => (clone $query)->count(),
            'reserved' => (clone $query)->whereNotNull('reserved_at')->count(),
            'oldest' => is_numeric($oldestTimestamp)
                ? CarbonImmutable::createFromTimestamp((int) $oldestTimestamp)->format('d.m.Y H:i:s')
                : 'Mavjud emas',
        ];
    }

    /** @return array{total: int|string, latest_at: string, reason: string} */
    private function failedMetrics(DescribeAiFailure $describeAiFailure): array
    {
        if ((string) config('queue.failed.driver') !== 'database-uuids') {
            return ['total' => 'N/A', 'latest_at' => 'N/A', 'reason' => 'N/A'];
        }

        $database = config('queue.failed.database');
        $table = (string) config('queue.failed.table', 'failed_jobs');
        $schema = Schema::connection(is_string($database) ? $database : null);

        if (! $schema->hasTable($table)) {
            return ['total' => 0, 'latest_at' => 'Mavjud emas', 'reason' => 'failed_jobs jadvali mavjud emas'];
        }

        $query = DB::connection(is_string($database) ? $database : null)
            ->table($table)
            ->where('queue', 'ai-evaluations');
        $latest = (clone $query)->latest('failed_at')->first(['exception', 'failed_at']);

        return [
            'total' => (clone $query)->count(),
            'latest_at' => $latest?->failed_at !== null
                ? CarbonImmutable::parse($latest->failed_at)->format('d.m.Y H:i:s')
                : 'Mavjud emas',
            'reason' => $latest?->exception !== null
                ? $describeAiFailure->handle($latest->exception)
                : 'Mavjud emas',
        ];
    }

    /**
     * @param  array{total: int|string, reserved: int|string, oldest: string}  $queueMetrics
     * @param  array{total: int|string, latest_at: string, reason: string}  $failedMetrics
     */
    private function diagnosis(
        bool $hasGeminiKey,
        int $unprocessedResources,
        array $queueMetrics,
        array $failedMetrics,
        ?CarbonInterface $workerLastSeenAt,
    ): string {
        if (! $hasGeminiKey) {
            return 'Xulosa: joriy muhit konfiguratsiyasida GEMINI_API_KEY mavjud emas yoki placeholder qiymat ishlatilgan.';
        }

        if (is_int($failedMetrics['total']) && $failedMetrics['total'] > 0) {
            return 'Xulosa: failed AI job mavjud. Jadvaldagi xavfsiz xato sababini va worker logini tekshiring.';
        }

        if (is_int($queueMetrics['total'])
            && $queueMetrics['total'] > 0
            && $queueMetrics['reserved'] === 0
            && ($workerLastSeenAt?->lte(now()->subMinutes(2)) ?? true)) {
            return 'Xulosa: AI joblar navbatda, lekin hech biri worker tomonidan olinmagan. Worker to‘xtagan yoki ai-evaluations queue’ini tinglamayapti.';
        }

        if (is_int($queueMetrics['total'])
            && $queueMetrics['total'] > 0
            && $workerLastSeenAt?->gt(now()->subMinutes(2))) {
            return 'Xulosa: AI worker faol va navbatdagi resurslarni olayapti.';
        }

        if ($unprocessedResources > 0 && $queueMetrics['total'] === 0) {
            return 'Xulosa: AI natijasiz resurslar bor, ammo queue bo‘sh. Backfill komandasini ishga tushirish kerak.';
        }

        return 'Xulosa: Laravel queue ma’lumotlarida aniq nosozlik ko‘rinmadi. Supervisor holati va worker logini tekshiring.';
    }
}
