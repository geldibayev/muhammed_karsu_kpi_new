<?php

namespace App\View\Composers;

use App\Support\ResourceUploadWindow;
use Illuminate\View\View;

class ResourceUploadDeadlineComposer
{
    public function __construct(private ResourceUploadWindow $resourceUploadWindow) {}

    public function compose(View $view): void
    {
        $deadline = $this->resourceUploadWindow->deadline();

        $view->with([
            'layoutResourceUploadDeadline' => $deadline,
            'layoutResourceUploadDeadlineLabel' => $this->resourceUploadWindow->formattedDeadline(),
            'layoutResourceUploadWindowOpen' => $this->resourceUploadWindow->isOpen(),
        ]);
    }
}
