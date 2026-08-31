import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterBuildService } from '@/modules/Roleplay/Character/init';
import { characterEditorService } from '@/modules/Roleplay/Character/init';
import { useKeywords } from '@/modules/Roleplay/Rule/init';

/** Движковое значение характеристики (для пула проверки инициативы). */
export interface InitiativeCharacteristicView {
  code: string;
  name: string;
  value: DimensionalNumberValue;
}

// Кэш на версию листа (WeakMap): сборка движка на персонажа выполняется один раз за сессию.
const cache = new WeakMap<CharacterVersion, Map<string, InitiativeCharacteristicView>>();

/**
 * Движковые значения характеристик персонажа (производные по формулам, модификаторы,
 * снаряжение, возраст) — через `characterEditorService.build` (как движок; D77).
 * Кэш по объекту версии. Возвращает Map<code, view>.
 */
export async function initiativeCharacteristics(
  version: CharacterVersion,
  spaceId: number,
  rules: Rule[],
): Promise<Map<string, InitiativeCharacteristicView>> {
  const cached = cache.get(version);
  if (cached) return cached;

  const { keywords, fetchTags } = useKeywords();
  if (keywords.value.length === 0) {
    await fetchTags();
  }
  const build = characterBuildService.fromVersion(version, spaceId, rules);
  const model = characterEditorService.build(
    build,
    rules,
    { osTotal: null, orTotal: null, moneyBudget: null },
    keywords.value,
  );

  const map = new Map<string, InitiativeCharacteristicView>();
  for (const characteristic of model.characteristics) {
    map.set(characteristic.code, {
      code: characteristic.code,
      name: characteristic.name,
      value: characteristic.value,
    });
  }
  cache.set(version, map);

  return map;
}
