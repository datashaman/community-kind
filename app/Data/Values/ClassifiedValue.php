<?php

namespace App\Data\Values;

class ClassifiedValue
{
    public function __construct(#[\SensitiveParameter] private readonly string $value) {}

    public function reveal(): string
    {
        return $this->value;
    }
}
