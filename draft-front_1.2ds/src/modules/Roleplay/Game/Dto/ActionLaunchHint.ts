import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Предвыбор действия из попапа состояния: правило, цель и запись. */
export interface ActionLaunchHint {
  actionCode: string;
  targetKey: CombatEntityKey;
  stateIndex: number;
}
