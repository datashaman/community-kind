<?php

namespace App\Enums;

enum CaseNoteStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';
}
