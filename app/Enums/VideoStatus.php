<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
