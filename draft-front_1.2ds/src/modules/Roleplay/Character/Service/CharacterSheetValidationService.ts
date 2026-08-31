import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';

/**
 * Проблемы, блокирующие «Сохранить» (ТР §7.6 «Готов»): имя, раса, лимиты, требования.
 * Общая для редактора листа и быстрого сохранения с карточки.
 */
export class CharacterSheetValidationService {
  characterSheetValidationIssues(
    build: CharacterBuild | undefined,
    model: CharacterEditorModel | null | undefined,
    requireRace: boolean,
  ): string[] {
    if (!build || !model) return ['Черновик не загружен'];
    const issues: string[] = [];
    if (!build.name.trim()) issues.push('Не задано имя');
    if (requireRace && build.raceRuleCode === null) issues.push('Не выбрана раса');
    const { os, ol, or, money } = model.budgets;
    if (os.exceeded) issues.push('Превышен лимит ОС');
    if (ol.exceeded) issues.push('Превышен лимит ОЛ');
    if (or.exceeded) issues.push('Превышен лимит ОР');
    if (money.exceeded) issues.push('Превышен бюджет денег');
    for (const unmet of this.unmetAbilityRequirements(model)) {
      issues.push(`Не выполнены требования «${unmet.name}»: ${unmet.reason ?? 'условия не соблюдены'}`);
    }

    return issues;
  }

  private unmetAbilityRequirements(model: CharacterEditorModel): { name: string; reason: string | null }[] {
    const result: { name: string; reason: string | null }[] = [];
    for (const ability of model.abilities) {
      if (ability.automatic || ability.gifted || ability.derived) continue;
      if (ability.multiple) {
        for (const instance of ability.instances) {
          if (instance.level <= 0) continue;
          const failed = instance.levels.slice(0, instance.level).find((entry) => !entry.met);
          if (failed) result.push({ name: `${ability.name} (${instance.domain})`, reason: failed.reason });
        }
        continue;
      }
      if (ability.level <= 0) continue;
      const failed = ability.levels.slice(0, ability.level).find((entry) => !entry.met);
      if (failed) result.push({ name: ability.name, reason: failed.reason });
    }

    return result;
  }
}
