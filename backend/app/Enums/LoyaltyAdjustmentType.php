<?php

namespace App\Enums;

enum LoyaltyAdjustmentType: string
{
    case PointsCorrection = 'points_correction';
    case TierUpgrade = 'tier_upgrade';
    case TierRevoke = 'tier_revoke';
    case GoodwillCredit = 'goodwill_credit';
    case FraudClawback = 'fraud_clawback';
}
