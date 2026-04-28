<?php

namespace App\Enums;

enum PenaltyTypes: string
{
    case BeforeDeparture = 'Before Departure';
    case AfterDeparture = 'After Departure';
    case Anytime = 'Anytime';
    case NoShow = 'No Show';
    case Minimum = 'Minimum';
    case Maximum = 'Maximum';
    case ExchangeRequired = 'Exchange Required';
    case ExchangeNotRequired = 'Exchange Not Required';

    public static function type(string $val): string
    {
        return constant("self::$val")->value;
    }
}