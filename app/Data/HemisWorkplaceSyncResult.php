<?php

namespace App\Data;

use App\Models\User;

final readonly class HemisWorkplaceSyncResult
{
    public function __construct(
        public User $user,
        public bool $degreeChanged,
        public int $primaryWorkplaceCount,
    ) {}
}
