<?php

namespace App\Enums;

enum CaseClassification: string
{
    case Confidential = 'confidential';
    case HighlyRestricted = 'highly_restricted';

    public function isAtLeast(self $classification): bool
    {
        return $this->rank() >= $classification->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Confidential => 1,
            self::HighlyRestricted => 2,
        };
    }
}
