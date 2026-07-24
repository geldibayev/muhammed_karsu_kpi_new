<?php

namespace App\View\Composers;

use App\Actions\GetAiReviewerHealth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiStatusMenuComposer
{
    public function __construct(private GetAiReviewerHealth $getAiReviewerHealth) {}

    public function compose(View $view): void
    {
        if (! Gate::allows('view-ai-status')) {
            return;
        }

        $view->with('aiMenuStatus', $this->getAiReviewerHealth->handle());
    }
}
