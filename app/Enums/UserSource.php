<?php

namespace App\Enums;

enum UserSource: string
{
    case Manual = 'manual';
    case CustomerApi = 'customer_api';
}
