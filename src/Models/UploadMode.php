<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

enum UploadMode: string
{
    case Create = 'create';
    case Append = 'append';
    case Upsert = 'upsert';
    case Overwrite = 'overwrite';
}
