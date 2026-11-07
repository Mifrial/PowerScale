/** Спокойное течение воды: блики едут вдоль осевой ленты. */
export interface MapLineFlow {
  enabled: boolean;
  /** Пиксели подложки в секунду. */
  speed: number;
  /** Плотность бликов, 0–100. */
  density: number;
  /** Непрозрачность бликов, 0–100. */
  opacity: number;
  color: string;
  /** 1 — по порядку точек, -1 — против. */
  direction: 1 | -1;
  /** Детерминированная раскладка бликов. */
  seed: number;
}

/** Визуальный декор ленты; пусто — без декора. */
export interface MapLineDecor {
  /** Плотность заливки, 0–100; нет — наследовать настройку карты. */
  fillOpacity?: number;
  /** Глубина русла, 0–100; 0 — плоская лента. */
  depth?: number;
  flow?: MapLineFlow;
}
