<?php

namespace App\Enums;

/**
 * How a roleplay persona's call is supposed to end. The values double as
 * the disposition ids the trainee must code the call as.
 */
enum Outcome: string
{
    case Transfer = 'transfer';
    case Dq = 'dq';
    case Dnc = 'dnc';
}
