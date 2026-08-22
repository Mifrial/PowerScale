import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterSettings } from '@/modules/Core/UI/Dto/Filter/FilterSettings';

export function buildVisibleFields(allFields: FilterField[], settings: FilterSettings | null): FilterField[] {
  if (!settings) return allFields;

  const savedMap = new Map(settings.fields.map((f) => [f.key, f.visible]));
  const visible = allFields.filter((f) => savedMap.get(f.key) !== false);
  if (!visible.length) return allFields;

  return visible;
}
