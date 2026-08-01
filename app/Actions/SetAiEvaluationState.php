<?php

namespace App\Actions;

use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Option;
use Illuminate\Support\Facades\Queue;

class SetAiEvaluationState
{
    public function handle(bool $enabled): void
    {
        $connection = (string) config('queue.default');

        if ($enabled) {
            Queue::resume($connection, ProcessAiDatumEvaluation::QUEUE);
            Option::setAiEvaluationsEnabled(true);

            return;
        }

        Option::setAiEvaluationsEnabled(false);
        Queue::pause($connection, ProcessAiDatumEvaluation::QUEUE);
    }
}
