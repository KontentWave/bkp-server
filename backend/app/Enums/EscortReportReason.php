<?php

namespace App\Enums;

enum EscortReportReason: string
{
    case Pimp = 'pimp';
    case Harassing = 'harassing';
    case DidNotKeepAgreement = 'did_not_keep_agreement';
}
