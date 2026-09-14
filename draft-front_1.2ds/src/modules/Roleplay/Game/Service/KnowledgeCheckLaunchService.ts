import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { KnowledgeAnswerCompleteness } from '@/modules/Roleplay/Character/Enum/KnowledgeAnswerCompleteness';
import type { KnowledgeCheckSituation } from '@/modules/Roleplay/Character/Dto/Knowledge/KnowledgeCheckSituation';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { LevelTintItem } from '@/modules/Core/UI/Dto/LevelTintItem';
import { CHECK_KNOWLEDGE_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { LAW_DEFENSE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/LAW_DEFENSE_ABILITY_CODE';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';
import { KNOWLEDGE_SLOT_OTHER_LABEL } from '@/modules/Roleplay/Rule/Constant/Knowledge/KNOWLEDGE_SLOT_OTHER_LABEL';
import { knowledgeCheckService } from '@/modules/Roleplay/Character/init';
import { knowledgeInstanceService } from '@/modules/Roleplay/Character/init';
import { KNOWLEDGE_ANSWER_CHAT_LABEL } from '@/modules/Roleplay/Game/Constant/Knowledge/KNOWLEDGE_ANSWER_CHAT_LABEL';
import type { KnowledgeCheckLaunchHint } from '@/modules/Roleplay/Game/Dto/KnowledgeCheckLaunchHint';

/**
 * Запуск соло-проверки знания: ситуация, сложность', бонус защиты, текст полноты.
 */
export class KnowledgeCheckLaunchService {
  isKnowledgeCheck(checkCode: string): boolean {
    return checkCode === CHECK_KNOWLEDGE_CODE;
  }

  isOtherSlotChoice(choice: string): boolean {
    return choice === KNOWLEDGE_SLOT_OTHER_LABEL;
  }

  hasSlotDictionary(fieldCode: string, rules: readonly Rule[]): boolean {
    return knowledgeInstanceService.slotOptions(fieldCode, rules).length > 0;
  }

  slotLabel(fieldCode: string): string {
    return knowledgeInstanceService.slotInputLabel(fieldCode);
  }

  fieldSelectItems(abilities: readonly CharacterAbility[]): LevelTintItem[] {
    const items = knowledgeInstanceService.fieldSelectItems().map((item) => ({
      title: item.title,
      value: item.value,
      level: this.maxFieldLevel(abilities, item.value),
    }));

    return this.sortTintItems(items);
  }

  slotSelectItems(abilities: readonly CharacterAbility[], fieldCode: string, rules: readonly Rule[]): LevelTintItem[] {
    if (!this.hasSlotDictionary(fieldCode, rules)) return [];
    const items = knowledgeInstanceService.slotOptions(fieldCode, rules).map((option) => {
      const slots = knowledgeInstanceService.composeSlots(fieldCode, option.name, rules);

      return {
        title: option.name,
        value: option.name,
        level: slots ? knowledgeCheckService.effectiveLevel(abilities, fieldCode, slots, rules) : 0,
      };
    });

    return [
      ...this.sortTintItems(items),
      { title: KNOWLEDGE_SLOT_OTHER_LABEL, value: KNOWLEDGE_SLOT_OTHER_LABEL, level: 0 },
    ];
  }

  slotChoiceOf(fieldCode: string, slotText: string, rules: readonly Rule[]): string {
    const trimmed = slotText.trim();
    if (!this.hasSlotDictionary(fieldCode, rules)) return '';
    if (!trimmed) return '';
    if (knowledgeInstanceService.slotOptions(fieldCode, rules).some((option) => option.name === trimmed)) {
      return trimmed;
    }

    return KNOWLEDGE_SLOT_OTHER_LABEL;
  }

  slotTextOfChoice(choice: string, customText: string): string {
    if (this.isOtherSlotChoice(choice)) return customText.trim();

    return choice.trim();
  }

  suggestSoleInstance(abilities: readonly CharacterAbility[]): { fieldCode: string; slotText: string } | null {
    const instances = abilities.filter(
      (ability) => ability.ruleCode === KNOWLEDGE_ABILITY_CODE && Boolean(ability.fieldCode),
    );
    if (instances.length !== 1) return null;
    const instance = instances[0];
    const fieldCode = instance.fieldCode ?? '';
    const key = knowledgeInstanceService.primarySlotKey(fieldCode);
    const slotText = instance.slots?.[key]?.text.trim() ?? '';
    if (!fieldCode || !slotText) return null;

    return { fieldCode, slotText };
  }

  composeSituation(
    fieldCode: string,
    slotText: string,
    band: number,
    rules: readonly Rule[],
  ): KnowledgeCheckSituation | null {
    const slots = knowledgeInstanceService.composeSlots(fieldCode, slotText, rules);
    if (!fieldCode || !slots) return null;

    return { fieldCode, slots, band: Math.min(3, Math.max(1, band)) };
  }

  hint(
    abilities: readonly CharacterAbility[],
    situation: KnowledgeCheckSituation | null,
    base: DimensionalNumberValue,
    rules: readonly Rule[],
  ): KnowledgeCheckLaunchHint | null {
    if (!situation) return null;
    const level = knowledgeCheckService.effectiveLevel(abilities, situation.fieldCode, situation.slots, rules);
    const shortage = knowledgeCheckService.shortage(level, situation.band);

    return {
      level,
      shortage,
      raised: knowledgeCheckService.raisedDifficulty(base, shortage),
      lawDefenseDelta: knowledgeCheckService.lawDefenseDelta(abilities, situation.fieldCode, situation.slots.region),
    };
  }

  raisedDifficulty(
    abilities: readonly CharacterAbility[],
    situation: KnowledgeCheckSituation | null,
    base: DimensionalNumberValue,
    rules: readonly Rule[],
    band = 1,
  ): DimensionalNumberValue {
    if (!situation) {
      return knowledgeCheckService.raisedDifficulty(base, knowledgeCheckService.shortage(0, band));
    }
    const hint = this.hint(abilities, situation, base, rules);

    return hint?.raised ?? base;
  }

  lawDefenseAdvantages(
    abilities: readonly CharacterAbility[],
    situation: KnowledgeCheckSituation | null,
    rules: readonly Rule[],
  ): AdvantageModifier[] {
    if (!situation) return [];
    const delta = knowledgeCheckService.lawDefenseDelta(abilities, situation.fieldCode, situation.slots.region);
    if (delta < 1) return [];
    const rule = rules.find((entry) => entry.code === LAW_DEFENSE_ABILITY_CODE);

    return [
      {
        source_code: LAW_DEFENSE_ABILITY_CODE,
        source_label: rule?.name ?? LAW_DEFENSE_ABILITY_CODE,
        delta,
      },
    ];
  }

  completenessOf(
    abilities: readonly CharacterAbility[],
    situation: KnowledgeCheckSituation | null,
    success: boolean,
    rules: readonly Rule[],
  ): KnowledgeAnswerCompleteness | null {
    if (!situation) return null;
    const level = knowledgeCheckService.effectiveLevel(abilities, situation.fieldCode, situation.slots, rules);
    const shortage = knowledgeCheckService.shortage(level, situation.band);
    const physiologyLevel =
      situation.fieldCode === 'diseases'
        ? knowledgeCheckService.effectiveLevel(abilities, 'physiology', situation.slots, rules)
        : 0;

    return knowledgeCheckService.answerCompleteness({
      success,
      shortage,
      fieldCode: situation.fieldCode,
      physiologyLevel,
    });
  }

  chatText(checkName: string, completeness: KnowledgeAnswerCompleteness | null): string {
    const suffix = completeness ? KNOWLEDGE_ANSWER_CHAT_LABEL[completeness] : '';

    return suffix ? `${checkName} · ${suffix}` : checkName;
  }

  private maxFieldLevel(abilities: readonly CharacterAbility[], fieldCode: string): number {
    let best = 0;
    for (const ability of abilities) {
      if (ability.ruleCode !== KNOWLEDGE_ABILITY_CODE || ability.fieldCode !== fieldCode) continue;
      best = Math.max(best, ability.level);
    }

    return Math.min(3, best);
  }

  private sortTintItems(items: LevelTintItem[]): LevelTintItem[] {
    return [...items].sort((left, right) => {
      if (right.level !== left.level) return right.level - left.level;

      return left.title.localeCompare(right.title, 'ru');
    });
  }
}
