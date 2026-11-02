import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ProcessNotice } from '@/modules/Roleplay/Game/Dto/ProcessNotice';
import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { asProcessAbilitySpec } from '@/modules/Roleplay/Game/Utils/combatActions';

/** Уведомления о ходе процесса: выполненные шаги и прерывание. */
export class ProcessNoticeService {
  /**
   * Уведомление о новом выполненном шаге.
   *
   * @param previous Сессия до обновления.
   * @param next Сессия после обновления.
   * @param entityKey Участник.
   * @param rules Правила ревизии.
   *
   * @returns Уведомление или null, если шаг не менялся.
   */
  completedStep(
    previous: ProcessSession | null | undefined,
    next: ProcessSession | null | undefined,
    entityKey: CombatEntityKey,
    rules: Rule[],
  ): ProcessNotice | null {
    if (!next || next.currentStepStatus !== 'completed') return null;
    if (previous && previous.currentStepCode === next.currentStepCode && previous.currentStepStatus === 'completed') {
      return null;
    }
    const rule = rules.find((entry) => entry.code === next.processRuleCode);
    const spec = rule ? asProcessAbilitySpec(rule) : null;
    const step = spec?.steps.find((entry) => entry.code === next.currentStepCode);
    const name = rule?.name ?? next.processRuleCode;
    const stepName = step?.name ?? next.currentStepCode;
    const canContinue = (next.availableSteps?.length ?? 0) > 0;

    return {
      text: `${name}: шаг «${stepName}» выполнен`,
      entityKey,
      ...(canContinue ? { continueLabel: 'Продолжить' } : {}),
    };
  }

  /**
   * Уведомление о прерывании процесса.
   *
   * @param name Название процесса.
   * @param entityKey Участник.
   *
   * @returns Уведомление.
   */
  aborted(name: string, entityKey: CombatEntityKey): ProcessNotice {
    return { text: `${name}: процесс прерван`, entityKey };
  }
}
