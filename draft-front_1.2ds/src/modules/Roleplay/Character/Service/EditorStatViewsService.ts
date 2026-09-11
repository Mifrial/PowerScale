import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { EditorStatView } from '@/modules/Roleplay/Character/Dto/Editor/EditorStatView';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class EditorStatViewsService {
  /**
   * Строит записи для компактного блока характеристик панели навигации редактора:
   * показываются основные, магические и производные характеристики. Остальные доступны в попапе
   * «Все характеристики» (buildAllEditorStatViews).
   */
  buildEditorStatViews(characteristics: EditorCharacteristic[], rules: Rule[]): EditorStatView[] {
    return this.buildStatViews(
      characteristics,
      rules,
      (group, derived) => group === 'primary' || group === 'magic' || derived,
    );
  }

  /**
   * Полный набор характеристик (включая базовые) для попапа «Все характеристики»:
   * записи уже сгруппированы по group правила; производные помечены derived.
   */
  buildAllEditorStatViews(characteristics: EditorCharacteristic[], rules: Rule[]): EditorStatView[] {
    return this.buildStatViews(characteristics, rules, () => true);
  }

  private buildStatViews(
    characteristics: EditorCharacteristic[],
    rules: Rule[],
    includeCharacteristic: (group: CharacteristicGroup | undefined, derived: boolean) => boolean,
  ): EditorStatView[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));

    const derivedBaseCodes = new Map<string, string[]>();
    for (const rule of rules) {
      if (rule.type !== 'characteristic') continue;
      const formula = (rule.spec as CharacteristicSpec | undefined)?.formula;
      if (!formula) continue;
      const baseCodes = rules
        .filter((entry) => entry.type === 'characteristic' && entry.code !== rule.code && formula.includes(entry.code))
        .map((entry) => entry.code);
      if (baseCodes.length > 0) derivedBaseCodes.set(rule.code, baseCodes);
    }

    return characteristics
      .filter((characteristic) => {
        const rule = byCode.get(characteristic.code);
        const group = (rule?.spec as CharacteristicSpec | undefined)?.group;
        const derived = derivedBaseCodes.has(characteristic.code);

        return includeCharacteristic(group, derived);
      })
      .map((characteristic) => {
        const rule = byCode.get(characteristic.code);
        const baseCodes = derivedBaseCodes.get(characteristic.code) ?? [];
        const bases = baseCodes
          .map((code) => characteristics.find((entry) => entry.code === code))
          .filter((entry): entry is EditorCharacteristic => entry !== undefined);

        return { characteristic, rule, derived: baseCodes.length > 0, bases };
      });
  }
}
