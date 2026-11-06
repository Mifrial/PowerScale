import type { GameRealtimeEvent } from '@/modules/Roleplay/Game/Dto/GameRealtimeEvent';

/**
 * Очередь применения кадров доставки: строгий порядок, но неудачный кадр не
 * блокирует следующие — он уходит в точечный догон, а очередь едет дальше.
 */
export class GameRealtimeEventQueue {
  private chain: Promise<void> = Promise.resolve();

  /**
   * @param apply Применяет кадр; false — данные не применились.
   * @param onMissed Догон по кадру, который не применился.
   */
  constructor(
    private readonly apply: (event: GameRealtimeEvent) => Promise<boolean>,
    private readonly onMissed: (event: GameRealtimeEvent) => void,
  ) {}

  /**
   * Ставит кадр в очередь.
   *
   * @param event Кадр.
   *
   * @return void
   */
  enqueue(event: GameRealtimeEvent): void {
    this.chain = this.chain.then(async () => {
      try {
        if (await this.apply(event)) return;
      } catch {
        // Падение чтения — тот же пропуск: кадр уходит в догон.
      }
      this.onMissed(event);
    });
  }

  /**
   * Хвост очереди: тесты и выгрузка ждут его.
   *
   * @return Promise<void> Хвост.
   */
  idle(): Promise<void> {
    return this.chain;
  }
}
