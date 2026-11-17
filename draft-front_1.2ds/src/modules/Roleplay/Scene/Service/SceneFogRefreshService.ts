import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';

/**
 * Каденция тумана витрины.
 *
 * Туман считается от точной позиции наблюдателя, а не от грубой клетки:
 * теневое литьё по запечённым растрам стоит единицы миллисекунд, поэтому маска
 * идёт за актором плавно. Ограничение по времени нужно только чтобы не
 * считать её чаще, чем успевает меняться картинка.
 */
export class SceneFogRefreshService {
  /** Момент последнего расчёта, мс. */
  private refreshedAt = Number.NEGATIVE_INFINITY;

  /** Пора ли пересчитать туман; при true отмечает момент расчёта. */
  isDue(now: number): boolean {
    if (now - this.refreshedAt < SCENE_OVERLAY.fogIntervalMs) {
      return false;
    }

    this.refreshedAt = now;

    return true;
  }

  /** Сбрасывает каденцию: следующий расчёт обязателен. */
  reset(): void {
    this.refreshedAt = Number.NEGATIVE_INFINITY;
  }
}
