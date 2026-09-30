import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameBattleCommandContext } from '@/modules/Roleplay/Game/Dto/GameBattleCommandContext';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

interface GameCombatCommandBase extends GameBattleCommandContext {
  commandId: string;
  actorKey: CombatEntityKey;
}

interface GameAttackDecisionCommand extends GameCombatCommandBase {
  commandType: 'attackDecision';
  processId: null;
  offerId: null;
  targetKey: CombatEntityKey;
  action: {
    actionRuleCode: string;
    itemRuleCode: string;
    profileType: 'strike' | 'throw' | 'shoot';
    profileIndex?: number;
    actionPointCost: number;
  };
}

interface GameDefenseDecisionCommand extends GameCombatCommandBase {
  commandType: 'defenseDecision';
  processId: string;
  offerId: number;
  expectedProcessStateVersion: number;
  targetKey: CombatEntityKey;
  defense: {
    reaction: HitDefenseReaction;
    blockItemRuleCode?: string | null;
  };
}

export type GameCombatCommand = GameAttackDecisionCommand | GameDefenseDecisionCommand;
