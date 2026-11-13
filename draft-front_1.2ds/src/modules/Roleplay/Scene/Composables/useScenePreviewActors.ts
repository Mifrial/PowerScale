import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { ScenePreviewActor } from '@/modules/Roleplay/Scene/Dto/ScenePreviewActor';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import { SCENE_ACTOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ACTOR';
import { SCENE_PREVIEW } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_PREVIEW';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';
import { SceneSurfaceService } from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';

/** Внешние данные предпросмотра. */
export interface ScenePreviewActorOptions {
  enabled: () => boolean;
  board: () => SceneBoard | null;
  elements: () => SceneElement[];
  resolution: () => number;
}

/** Состояние движения по полилинии. */
interface Motion {
  actorId: number;
  points: ScenePoint[];
  lengths: number[];
  total: number;
  traveled: number;
  lastTime: number;
  lastGhostAt: number;
}

/**
 * Акторы предпросмотра.
 *
 * Пока Game не кормит акторов, манекены создаются из маркеров поля: `player_start`
 * и `npc_spawn`. Позиции, обзор и движение — состояние предпросмотра, в сцену не
 * сохраняются. Скрытый токен остаётся видимым призраком: ведущий должен иметь
 * возможность переключить наблюдателя.
 */
export function useScenePreviewActors(options: ScenePreviewActorOptions) {
  const actors = ref<ScenePreviewActor[]>([]);
  const selectedId = ref<number | null>(null);
  const route = ref<SceneNavRoute | null>(null);
  const navigation = new SceneNavigationService();
  const surfaces = new SceneSurfaceService();
  const visibility = new SceneVisibilityService();
  let gridKey = '';
  let grid: SceneSurfaceGrid | null = null;
  let motion: Motion | null = null;
  let frame = 0;

  const selectedActor = computed(() => actors.value.find((actor) => actor.id === selectedId.value) ?? null);

  watch([options.enabled, options.board, options.elements, options.resolution], () => syncFromScene());
  syncFromScene();

  onBeforeUnmount(() => stopMotion());

  /** Пересобирает акторов из маркеров поля. */
  function syncFromScene(): void {
    stopMotion();
    route.value = null;
    if (!options.enabled()) {
      actors.value = [];
      selectedId.value = null;

      return;
    }

    const board = options.board();
    if (board === null) {
      actors.value = [];
      selectedId.value = null;

      return;
    }

    const resolution = Math.max(1, options.resolution());
    const previous = new Map(actors.value.map((actor) => [actor.id, actor]));
    const markers = options
      .elements()
      .filter(
        (element) =>
          element.boardId === board.id &&
          element.kind === 'marker' &&
          element.shape.type === 'point' &&
          (element.props.role === 'player_start' || element.props.role === 'npc_spawn'),
      );
    actors.value = markers.map((element) => {
      const point = element.shape.type === 'point' ? element.shape : { x: 0, y: 0 };
      const role = element.props.role ?? 'player_start';
      const old = previous.get(element.id);

      return {
        id: element.id,
        x: old?.x ?? point.x,
        y: old?.y ?? point.y,
        facing: old?.facing ?? 0,
        radius: Math.max(1, resolution * SCENE_ACTOR.radiusIpari),
        tint: role === 'player_start' ? 0x8fb4ff : 0xffb38f,
        label: element.props.label || (role === 'player_start' ? 'Манекен' : 'Токен'),
        selected: element.id === selectedId.value,
        ghost: old?.ghost ?? false,
        sightRadius:
          old?.sightRadius ??
          resolution * (role === 'player_start' ? SCENE_PREVIEW.sightIpari : SCENE_PREVIEW.tokenSightIpari),
        role,
      };
    });

    if (!actors.value.some((actor) => actor.id === selectedId.value)) {
      selectedId.value = actors.value[0]?.id ?? null;
    }
    select(selectedId.value);
  }

  /** Выбирает актора и сбрасывает маршрут. */
  function select(id: number | null): void {
    if (id !== null && !actors.value.some((actor) => actor.id === id)) {
      return;
    }

    selectedId.value = id;
    stopMotion();
    route.value = null;
    actors.value = actors.value.map((actor) => ({
      ...actor,
      selected: actor.id === id,
      ghost: actor.id === id ? false : actor.ghost,
    }));
    updateGhosts();
  }

  /** Радиус обзора выбранного актора. */
  function setSightRadius(id: number, radius: number): void {
    const board = options.board();
    const resolution = Math.max(1, options.resolution());
    const max = board === null ? resolution * 40 : Math.hypot(board.boundWidth, board.boundHeight);
    const sightRadius = Math.min(max, Math.max(resolution, radius));
    actors.value = actors.value.map((actor) => (actor.id === id ? { ...actor, sightRadius } : actor));
    updateGhosts();
  }

  /** Актор под точкой; призраки тоже кликабельны. */
  function actorAt(point: ScenePoint): ScenePreviewActor | null {
    let best: ScenePreviewActor | null = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (const actor of actors.value) {
      const distance = Math.hypot(actor.x - point[0], actor.y - point[1]);
      if (distance <= Math.max(actor.radius * 1.3, options.resolution() * 0.9) && distance < bestDistance) {
        best = actor;
        bestDistance = distance;
      }
    }

    return best;
  }

  /** Клик: выбрать актора или отправить выбранного в точку. */
  function onMapClick(point: ScenePoint): void {
    const actor = actorAt(point);
    if (actor !== null) {
      select(actor.id);

      return;
    }

    moveTo(point);
  }

  /** Маршрут выбранного актора и анимация движения. */
  function moveTo(point: ScenePoint): void {
    const actor = selectedActor.value;
    const board = options.board();
    if (actor === null || board === null) {
      return;
    }

    const result = navigation.route(board, options.elements(), options.resolution(), [actor.x, actor.y], point, {
      agentRadius: actor.radius,
    });
    if (result === null || result.points.length < 2) {
      return;
    }

    route.value = result;
    startMotion(actor.id, result.points);
  }

  /** Запускает движение по полилинии. */
  function startMotion(actorId: number, points: ScenePoint[]): void {
    stopMotion();
    const lengths: number[] = [];
    let total = 0;
    for (let index = 1; index < points.length; index += 1) {
      const length = Math.hypot(points[index][0] - points[index - 1][0], points[index][1] - points[index - 1][1]);
      lengths.push(length);
      total += length;
    }
    if (total <= 0) {
      return;
    }

    const now = performance.now();
    motion = { actorId, points, lengths, total, traveled: 0, lastTime: now, lastGhostAt: now };
    frame = requestAnimationFrame(step);
  }

  /** Кадр движения. */
  function step(now: number): void {
    if (motion === null) {
      return;
    }

    const delta = Math.min(0.1, (now - motion.lastTime) / 1000);
    motion.lastTime = now;
    motion.traveled = Math.min(
      motion.total,
      motion.traveled + options.resolution() * SCENE_PREVIEW.walkIpariPerSecond * delta,
    );
    const sampled = sampleMotion(motion);
    if (sampled === null) {
      stopMotion();

      return;
    }

    const { point, facing } = sampled;
    actors.value = actors.value.map((actor) =>
      actor.id === motion?.actorId ? { ...actor, x: point[0], y: point[1], facing } : actor,
    );

    if (now - motion.lastGhostAt >= SCENE_PREVIEW.ghostInterval) {
      motion.lastGhostAt = now;
      updateGhosts();
    }

    if (motion.traveled >= motion.total) {
      stopMotion();

      return;
    }

    frame = requestAnimationFrame(step);
  }

  /** Точка и facing на текущей длине маршрута. */
  function sampleMotion(current: Motion): { point: ScenePoint; facing: number } | null {
    let left = current.traveled;
    for (let index = 0; index < current.lengths.length; index += 1) {
      const length = current.lengths[index];
      if (left > length) {
        left -= length;
        continue;
      }

      const from = current.points[index];
      const to = current.points[index + 1];
      const t = length <= 0 ? 0 : left / length;
      const dx = to[0] - from[0];
      const dy = to[1] - from[1];
      const facing = ((Math.atan2(dx, -dy) * 180) / Math.PI + 360) % 360;

      return { point: [from[0] + dx * t, from[1] + dy * t], facing };
    }

    return null;
  }

  /** Обновляет призраков по обзору выбранного актора. */
  function updateGhosts(): void {
    const observer = selectedActor.value;
    if (observer === null || !options.enabled()) {
      actors.value = actors.value.map((actor) => ({ ...actor, ghost: false }));

      return;
    }

    const board = options.board();
    if (board === null) {
      return;
    }

    const surface = surfaceGrid(board);
    const observerPoint: ScenePoint = [observer.x, observer.y];
    actors.value = actors.value.map((actor) => {
      if (actor.id === observer.id) {
        return { ...actor, ghost: false };
      }

      const distance = Math.hypot(actor.x - observer.x, actor.y - observer.y);
      const visible = distance <= observer.sightRadius && visibility.sees(surface, observerPoint, [actor.x, actor.y]);

      return { ...actor, ghost: !visible };
    });
  }

  /** Сетка поверхностей с кэшем: обзор не пересобирает её на каждый клик. */
  function surfaceGrid(board: SceneBoard): SceneSurfaceGrid {
    const elements = options.elements();
    const resolution = options.resolution();
    const key = [
      board.id,
      board.boundWidth,
      board.boundHeight,
      board.occupancyCell,
      resolution,
      ...elements
        .filter((element) => element.boardId === board.id)
        .map((element) =>
          [
            element.id,
            element.kind,
            element.sortOrder,
            element.props.kind ?? '',
            element.props.scale ?? '',
            element.props.baseRadius ?? '',
            element.props.sightRadius ?? '',
            element.props.sightBottom ?? '',
            element.props.blocksMovement ?? '',
            element.props.blocksVision ?? '',
            element.props.state ?? '',
            JSON.stringify(element.shape),
          ].join(':'),
        ),
    ].join('|');
    if (grid === null || gridKey !== key) {
      grid = surfaces.build(board, elements, resolution);
      gridKey = key;
    }

    return grid;
  }

  /** Останавливает анимацию. */
  function stopMotion(): void {
    if (frame !== 0) {
      cancelAnimationFrame(frame);
      frame = 0;
    }
    motion = null;
  }

  return {
    actors,
    selectedId,
    selectedActor,
    route,
    select,
    setSightRadius,
    actorAt,
    onMapClick,
    moveTo,
  };
}
