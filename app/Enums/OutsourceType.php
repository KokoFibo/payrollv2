<?php

namespace App\Enums;

enum OutsourceType: string
{
    case Os = 'OS';
    case NonOs = 'Non OS';

    public function label(): string
    {
        return $this->value;
    }
}
