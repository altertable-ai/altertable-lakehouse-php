<?php

declare(strict_types=1);

namespace Altertable\Lakehouse\Models;

enum UploadFormat: string
{
    case Csv = 'csv';
    case Json = 'json';
    case Parquet = 'parquet';
}
