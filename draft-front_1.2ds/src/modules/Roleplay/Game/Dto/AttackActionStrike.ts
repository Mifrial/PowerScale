import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface AttackActionStrike {
  targetKey: CombatEntityKey;
  profile: AttackOverview;
}
