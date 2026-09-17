import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { LYING_STATE_CODE, UNSTABLE_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { RECOVER_STABILITY_CODE } from '@/modules/Roleplay/Game/Constant/Combat/RECOVER_STABILITY_CODE';

/** Переключение лежачего положения и снятие неустойчивости. */
export class PostureStateService {
  hasLying(version: CharacterVersion): boolean {
    return version.states.some((state) => state.stateRuleCode === LYING_STATE_CODE);
  }

  shouldToggleLying(operations: { type: string }[] | undefined): boolean {
    return operations?.some((operation) => operation.type === 'posture') ?? false;
  }

  isRecoverStability(ruleCode: string): boolean {
    return ruleCode === RECOVER_STABILITY_CODE;
  }

  nextAfterStandUp(version: CharacterVersion): { addLying: boolean; removeCodes: string[] } {
    if (this.hasLying(version)) {
      return { addLying: false, removeCodes: [LYING_STATE_CODE] };
    }

    return { addLying: true, removeCodes: [UNSTABLE_STATE_CODE] };
  }
}
