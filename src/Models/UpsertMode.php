<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

enum UpsertMode: string
{
    case Create = 'create';
    case Append = 'append';
    case Upsert = 'upsert';
    case Overwrite = 'overwrite';
}
