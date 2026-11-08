import type { MapAreaShape } from '@/modules/Roleplay/Map/Dto/MapAreaShape';
import type { MapHandle } from '@/modules/Roleplay/Map/Dto/MapHandle';
import type { MapPoint } from '@/modules/Roleplay/Map/Dto/MapPoint';

/** Кандидат hit-test области: id, контур и ручки. */
export interface MapAreaHitCandidate {
  id: number;
  /** Прямой контур; используется, когда формы варианта нет. */
  path?: MapPoint[];
  /** Ручки по вершинам; пусто или нет — прямые рёбра. */
  handles?: MapHandle[];
  /**
   * Живая форма варианта. Контур читается при hit-тесте, поэтому правка пути
   * не пересобирает список кандидатов на каждом кадре перетаскивания.
   */
  shape?: MapAreaShape;
  sortOrder?: number;
}
