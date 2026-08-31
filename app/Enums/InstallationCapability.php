<?php

namespace App\Enums;

enum InstallationCapability: string
{
    case Writes = 'writes';
    case Queues = 'queues';
    case Outbox = 'outbox';
    case Uploads = 'uploads';
    case Forms = 'forms';
}
