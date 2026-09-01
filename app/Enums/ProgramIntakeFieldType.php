<?php

namespace App\Enums;

enum ProgramIntakeFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Boolean = 'boolean';
    case Date = 'date';
}
