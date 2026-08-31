<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Throwable;

class ClassifiedDataUnavailable extends RuntimeException implements ShouldntReport
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Classified data is unavailable.', previous: $previous);
    }
}
