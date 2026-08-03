<?php

namespace App\Enums;

enum ContactStatus: string
{
    case New = 'new';
    case Processed = 'processed';
    case Archived = 'archived';
}
