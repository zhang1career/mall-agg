<?php

declare(strict_types=1);

namespace App\Contracts;

interface HasDictionaryLabel
{
    public function label(): string;
}
