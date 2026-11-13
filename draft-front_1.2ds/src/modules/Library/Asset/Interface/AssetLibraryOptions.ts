/** Доступ к коллекции текущего поля: код может смениться вместе с элементом. */
export interface AssetLibraryOptions {
  getCollectionModule: () => string;
  getCollectionCode: () => string;
}
