import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Характеристика блока навигации редактора: запись модели + резолвнутое правило.
 * Для производных (формула в спеке) — список кодов и сами записи базовых характеристик.
 */
export interface EditorStatView {
  characteristic: EditorCharacteristic;
  rule: Rule | undefined;
  derived: boolean;
  bases: EditorCharacteristic[];
}

/**
 * Строит записи для компактного блока характеристик панели навигации редактора:
 * показываются только основные характеристики (group 'primary'). Остальные (базовые,
 * важные, боевые, вторичные) доступны в попапе «Все характеристики» (buildAllEditorStatViews).
 * Производные определяются по формуле правила характеристики.
 */
export function buildEditorStatViews(characteristics: EditorCharacteristic[], rules: Rule[]): EditorStatView[] {
  return buildStatViews(characteristics, rules, (group) => group === 'primary');
}

/**
 * Полный набор характеристик (включая базовые) для попапа «Все характеристики»:
 * записи уже сгруппированы по group правила; производные помечены derived.
 */
export function buildAllEditorStatViews(characteristics: EditorCharacteristic[], rules: Rule[]): EditorStatView[] {
  return buildStatViews(characteristics, rules, () => true);
}

function buildStatViews(
  characteristics: EditorCharacteristic[],
  rules: Rule[],
  includeGroup: (group: CharacteristicGroup | undefined) => boolean,
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

      return includeGroup(group);
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
