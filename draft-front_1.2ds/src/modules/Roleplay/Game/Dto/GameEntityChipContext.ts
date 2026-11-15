/** Контекст inline-чипа персонажа/НПС: хост может перехватить открытие, иначе чип открывает слайдер. */
export interface GameEntityChipContext {
  /** Перехват открытия хостом (игровой чат открывает боевую карточку). */
  openEntity?: (key: string) => void;
  /** Игра для слайдера деталки персонажа/НПС. */
  gameId?: number | null;
  /** `game` — деталка в контексте игры, `standalone` — страница персонажа. */
  entityContext?: 'game' | 'standalone';
}
