import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import { ADVANTAGE_SOURCE_ACTION } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

/**
 * Подготовка «Противодействовать защите»: одно висящее сравнение с прошлой реакцией цели.
 */
export class DefenseCounterService {
  of(pendingEffects: PendingActionEffect[]): Extract<ActionEffect, { type: 'prepared_defense_counter' }> | null {
    const pending = [...pendingEffects].reverse().find((item) => item.effect.type === 'prepared_defense_counter');
    if (!pending || pending.effect.type !== 'prepared_defense_counter') return null;

    return pending.effect;
  }

  hitModifier(
    pendingEffects: PendingActionEffect[],
    targetKey: string | null,
    reaction: string | null,
  ): AdvantageModifier | null {
    const prepared = this.of(pendingEffects);
    if (!prepared || !targetKey || !reaction || prepared.targetKey !== targetKey) return null;

    return {
      source_code: ADVANTAGE_SOURCE_ACTION,
      source_label: 'Подготовка',
      delta: prepared.reaction === reaction ? 1 : -1,
    };
  }

  replaceOnPending(
    pendingEffects: PendingActionEffect[],
    snapshot: { targetKey: string; reaction: string },
    sourceRuleCode: string,
  ): PendingActionEffect[] {
    const without = pendingEffects.filter((item) => item.effect.type !== 'prepared_defense_counter');

    return [
      ...without,
      {
        sourceRuleCode,
        effect: {
          type: 'prepared_defense_counter',
          targetKey: snapshot.targetKey,
          reaction: snapshot.reaction,
        },
      },
    ];
  }

  describe(
    effect: Extract<ActionEffect, { type: 'prepared_defense_counter' }>,
    nameOf: (key: string) => string,
  ): string {
    return `подготовка против ${this.reactionLabel(effect.reaction)} у ${nameOf(effect.targetKey)}`;
  }

  reactionLabel(reaction: string): string {
    return { ignore: 'игнора', dodge: 'уклонения', block: 'блока' }[reaction] ?? reaction;
  }
}
