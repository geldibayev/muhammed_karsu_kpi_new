<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Option;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class SetAiEvaluationState
{
    /**
     * @return array{enabled: bool, queue_paused: bool, queue_resumed: bool}
     */
    public function handle(bool $enabled): array
    {
        return Cache::lock('kpi:ai-evaluation-state', 10)->block(5, function () use ($enabled): array {
            $connection = (string) config('queue.default');
            $wasEnabled = Option::aiEvaluationsEnabled();
            $queueWasPaused = Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE);
            $pausedBySetting = Option::aiQueuePausedBySetting();

            if ($enabled) {
                Option::setAiEvaluationsEnabled(true);

                $isLegacySettingsPause = ! $wasEnabled
                    && $pausedBySetting === null
                    && ! Cache::has('kpi:ai-worker:paused-reason');
                $queueResumed = $queueWasPaused && ($pausedBySetting === true || $isLegacySettingsPause);

                if ($queueResumed) {
                    Queue::resume($connection, ProcessAiDatumEvaluation::QUEUE);
                }

                Option::setAiQueuePausedBySetting(false);

                return [
                    'enabled' => true,
                    'queue_paused' => Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE),
                    'queue_resumed' => $queueResumed,
                ];
            }

            Option::setAiEvaluationsEnabled(false);

            if (! $queueWasPaused) {
                Option::setAiQueuePausedBySetting(true);
                Queue::pause($connection, ProcessAiDatumEvaluation::QUEUE);
            } elseif ($wasEnabled) {
                Option::setAiQueuePausedBySetting(false);
            }

            return [
                'enabled' => false,
                'queue_paused' => Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE),
                'queue_resumed' => false,
            ];
        });
    }
}
