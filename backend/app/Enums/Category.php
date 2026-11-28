<?php

namespace App\Enums;

/** Anecdote categories used by the AI classifier and the flag rules. */
enum Category: string
{
    case AttendanceTardiness = 'attendance_tardiness';
    case DisruptiveBehavior = 'disruptive_behavior';
    case DefianceDisrespect = 'defiance_disrespect';
    case AggressionBullying = 'aggression_bullying';
    case AcademicConcern = 'academic_concern';
    case WellbeingConcern = 'wellbeing_concern';
    case PositiveBehavior = 'positive_behavior';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
