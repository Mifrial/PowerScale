/**
 * Блоки информации листа (персонажа / НПС в игре), видимые по зонам видимости.
 * Имя видно всегда, когда лист видим — здесь только настраиваемые блоки.
 */
export type SheetSection =
  | 'shortDescription'
  | 'fullDescription'
  | 'race'
  | 'states'
  | 'characteristics'
  | 'resources'
  | 'abilities'
  | 'inventory';
