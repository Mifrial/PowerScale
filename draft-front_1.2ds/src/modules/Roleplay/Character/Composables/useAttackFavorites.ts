import { useAttackFavoritesStore } from '@/modules/Roleplay/Character/Store/attackFavorites';

export function useAttackFavorites() {
  const store = useAttackFavoritesStore();

  return {
    favoriteOf: store.favoriteOf,
    isFavorite: store.isFavorite,
    setFavorite: store.setFavorite,
  };
}
