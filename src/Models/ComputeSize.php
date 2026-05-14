<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

enum ComputeSize: string
{
    case S = 'S';
    case M = 'M';
    case L = 'L';
}
