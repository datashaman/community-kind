<?php

namespace App\Enums;

enum CaseDocumentState: string
{
    case AwaitingUpload = 'awaiting_upload';
    case Quarantined = 'quarantined';
    case Scanning = 'scanning';
    case Clean = 'clean';
    case Rejected = 'rejected';
    case ScanFailed = 'scan_failed';
    case Deleted = 'deleted';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Clean, self::Rejected, self::Deleted], true);
    }
}
