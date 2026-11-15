import type { AssetScope } from '@/modules/Library/Asset/Enum/AssetScope';

/** Доступ к коллекции текущего поля: код может смениться вместе с элементом. */
export interface AssetLibraryOptions {
  getCollectionModule: () => string;
  getCollectionCode: () => string;
  /** Только записи с данными потребителя: палитра объектов сцены. */
  getHasMeta?: () => boolean;
  /** Стартовая вкладка: у объектов чаще нужны личные записи. */
  initialScope?: AssetScope;
}
