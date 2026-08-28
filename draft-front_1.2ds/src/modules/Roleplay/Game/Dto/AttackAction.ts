import type { AttackActionStrike } from '@/modules/Roleplay/Game/Dto/AttackActionStrike';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessActionContext } from '@/modules/Roleplay/Game/Dto/ProcessActionContext';

export interface AttackAction {
  initiator: CombatEntityKey;
  source: { kind: 'action'; actionRuleId: string } | { kind: 'process'; process: ProcessActionContext };
  strikes: AttackActionStrike[];
  reactionMode: 'simultaneous';
  totalOdCost: number;
}
