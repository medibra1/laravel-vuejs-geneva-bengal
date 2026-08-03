<?php

namespace App\Enums;

/**
 * Status names used with spatie/laravel-model-status (Cat::setStatus()).
 * Not an Eloquent-cast column — status lives in the statuses table so its
 * history over time is preserved.
 */
enum CatStatus: string
{
    case Available = 'disponible';
    case Pending = 'en_attente';
    case Adopted = 'adopte';
}
