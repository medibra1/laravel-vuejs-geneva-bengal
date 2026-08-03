<?php

namespace App\Enums;

enum ContactReason: string
{
    case Adopt = 'adopt';
    case WaitingList = 'waiting_list';
    case Question = 'question';
}
