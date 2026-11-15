import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import { SCENE_OVERLAY } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_OVERLAY';

/**
 * Каденция тумана витрины.
 *
 * Точная видимость считается лучом на каждую клетку `occupancyCell` и стоит
 * десятки миллисекунд, поэтому туман нельзя пересобирать на каждый кадр
 * движения актора. Новый расчёт нужен только при переходе наблюдателя в другую
 * грубую клетку и не чаще заданного интервала: стоя на месте и при панораме
 * маска не меняется.
 */
export class SceneFogRefreshService {
  /** Грубая клетка последнего расчёта; пустая строка — расчёт ещё не было. */
  private cell = '';

  /** Момент последнего расчёта, мс. */
  private refreshedAt = Number.NEGATIVE_INFINITY;

  /** Нужен ли новый расчёт тумана в этот момент. */
  isDue(observer: ScenePoint | null, resolution: number, now: number): boolean {
    const cell = this.cellOf(observer, resolution);
    if (cell === this.cell) {
      return false;
    }

    if (now - this.refreshedAt < SCENE_OVERLAY.fogIntervalMs) {
      return false;
    }

    this.cell = cell;
    this.refreshedAt = now;

    return true;
  }

  /** Сбросил ли наблюдатель грубую клетку с последнего расчёта. */
  isCellChanged(observer: ScenePoint | null, resolution: number): boolean {
    return this.cellOf(observer, resolution) !== this.cell;
  }

  /** Сбрасывает каденцию: следующий расчёт обязателен. */
  reset(): void {
    this.cell = '';
    this.refreshedAt = Number.NEGATIVE_INFINITY;
  }

  /** Ключ грубой клетки наблюдателя; без наблюдателя — центр поля. */
  private cellOf(observer: ScenePoint | null, resolution: number): string {
    if (observer === null) {
      return 'center';
    }

    const cell = Math.max(1, resolution) * SCENE_OVERLAY.fogCellIpari;

    return `${Math.floor(observer[0] / cell)}:${Math.floor(observer[1] / cell)}`;
  }
}
