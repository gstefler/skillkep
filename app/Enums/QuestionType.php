<?php

namespace App\Enums;

enum QuestionType: string
{
    case TrueFalse = 'true_false';
    case Write = 'write';
    case SelectOne = 'select_one';
    case SelectMultiple = 'select_multiple';
    case Pair = 'pair';
}
