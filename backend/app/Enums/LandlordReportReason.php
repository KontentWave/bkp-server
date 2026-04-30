<?php

namespace App\Enums;

enum LandlordReportReason: string
{
    case Drugs = 'drugs';
    case Hygiene = 'hygiene';
    case DidNotPay = 'did_not_pay';
}
