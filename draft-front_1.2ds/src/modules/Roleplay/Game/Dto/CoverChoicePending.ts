import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CheckOffer } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { CoveredHitRoll } from '@/modules/Roleplay/Game/Dto/CoveredHitRoll';

export interface CoverChoicePending {
  accepted: CheckOffer;
  attack: AttackOverview;
  covered: CoveredHitRoll;
  choiceKeys: CombatEntityKey[];
}
