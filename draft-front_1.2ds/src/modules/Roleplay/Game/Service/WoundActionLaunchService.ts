import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { WoundLaunchOption } from '@/modules/Roleplay/Game/Dto/WoundLaunchOption';
import { WOUND_ACTION_OD } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_ACTION_OD';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { BANDAGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/BANDAGE_ABILITY_CODE';
import { SQUEEZE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/SQUEEZE_ABILITY_CODE';

/**
 * Поля запуска перевязки и зажима в диалоге действий.
 */
export class WoundActionLaunchService {
  isBandage(code: string | null | undefined): boolean {
    return code === BANDAGE_ABILITY_CODE;
  }

  isSqueeze(code: string | null | undefined): boolean {
    return code === SQUEEZE_ABILITY_CODE;
  }

  isWoundAction(code: string | null | undefined): boolean {
    return this.isBandage(code) || this.isSqueeze(code);
  }

  bandageOd(actor: CharacterVersion | null | undefined, overlay: GameCombatOverlay | null | undefined): number {
    return woundInstanceService.bandageOd({
      medicHasAid: woundInstanceService.medicHasAid(actor),
      medicHasQuick: woundInstanceService.medicHasQuick(actor),
      targetBandagedOnce: overlay?.woundBandagedOnce === true,
    });
  }

  squeezeOd(count: number): number {
    return WOUND_ACTION_OD.squeeze * Math.max(0, count);
  }

  bandageOptions(states: CharacterStateValue[], actor: CharacterVersion | null | undefined): WoundLaunchOption[] {
    const aid = woundInstanceService.medicHasAid(actor);

    return this.woundEntries(states).map((entry) => ({
      title: this.optionTitle(entry.state, entry.index),
      value: entry.index,
      disabled: !woundInstanceService.canBandage(entry.state, aid),
    }));
  }

  squeezeOptions(states: CharacterStateValue[]): WoundLaunchOption[] {
    return this.woundEntries(states).map((entry) => ({
      title: this.optionTitle(entry.state, entry.index),
      value: entry.index,
      disabled: !woundInstanceService.canSqueeze(entry.state),
    }));
  }

  private woundEntries(states: CharacterStateValue[]): { state: CharacterStateValue; index: number }[] {
    return states
      .map((state, index) => ({ state: woundInstanceService.migrate(state), index }))
      .filter((entry) => woundInstanceService.isWound(entry.state));
  }

  private optionTitle(state: CharacterStateValue, index: number): string {
    return `Рана ${index + 1} · сила ${woundInstanceService.strength(state)} · тик ${woundInstanceService.tick(state)}`;
  }
}
