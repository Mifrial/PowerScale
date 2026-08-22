import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';

/** Все настраиваемые блоки информации листа (имя видно всегда). */
export const SHEET_VISIBLE_SECTIONS: SheetSection[] = [
  'shortDescription',
  'fullDescription',
  'race',
  'states',
  'characteristics',
  'resources',
  'abilities',
  'inventory',
];

export const SHEET_SECTION_LABELS: Record<SheetSection, string> = {
  shortDescription: 'Краткое описание',
  fullDescription: 'Полное описание',
  race: 'Раса',
  states: 'Состояния',
  characteristics: 'Характеристики',
  resources: 'Ресурсы',
  abilities: 'Способности',
  inventory: 'Инвентарь',
};
