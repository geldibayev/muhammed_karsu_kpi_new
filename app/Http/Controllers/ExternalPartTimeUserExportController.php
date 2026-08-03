<?php

namespace App\Http\Controllers;

use App\Actions\ExportExternalPartTimeUsersToXlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExternalPartTimeUserExportController extends Controller
{
    public function __invoke(
        ExportExternalPartTimeUsersToXlsx $exportExternalPartTimeUsersToXlsx,
    ): BinaryFileResponse {
        return $exportExternalPartTimeUsersToXlsx->handle();
    }
}
