import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneLevelMenu } from '@/modules/Roleplay/Scene/Dto/SceneLevelMenu';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { ScenePreviewActor } from '@/modules/Roleplay/Scene/Dto/ScenePreviewActor';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import { SCENE_ACTOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ACTOR';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_PREVIEW } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_PREVIEW';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';
import {
  SceneSurfaceService,
  surfaceIndex,
  surfaceUnderDeck,
} from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
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
  /** Уровень каждого отрезка: 0 — земля, больше — настил. */
  levels: number[];
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
  const levelMenu = ref<SceneLevelMenu | null>(null);
  let menuPoint: ScenePoint | null = null;
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
      // Маркер переставили — уровень берём по новому месту: на настиле он
      // стоит на настиле, а не под ним.
      const moved = old === undefined || old.x !== point.x || old.y !== point.y;

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
        level: moved ? markerLevel(point) : (old?.level ?? 0),
        underDeck: old?.underDeck ?? false,
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

  /**
   * Уровень маркера по месту.
   *
   * Настил — верхний уровень: маркер, поставленный на мост, стоит на мосту.
   * Под настил актор попадает только выбором в меню уровня, иначе признак
   * «под настилом» затемнял бы того, кто стоит на досках.
   */
  function markerLevel(point: { x: number; y: number }): number {
    const board = options.board();
    if (board === null) {
      return 0;
    }

    const surface = surfaceGrid(board);
    const cell = surfaceIndex(surface, point.x, point.y);

    return cell >= 0 && surface.kinds[cell] === 'bridge' ? 1 : 0;
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

  /** Клик: выбрать актора, отправить по земле (Alt) или показать выбор уровня. */
  function onMapClick(point: ScenePoint, under = false, at?: ScenePoint): void {
    levelMenu.value = null;
    menuPoint = null;

    const actor = actorAt(point);
    if (actor !== null) {
      select(actor.id);

      return;
    }

    const selected = selectedActor.value;
    const board = options.board();
    if (selected === null || board === null) {
      return;
    }

    if (under) {
      moveTo(point, 0);

      return;
    }

    const mesh = navigation.build(board, options.elements(), options.resolution(), { agentRadius: selected.radius });
    if (mesh === null) {
      moveTo(point);

      return;
    }

    const levels = navigation.levelsAt(mesh, point);
    if (levels.length === 0) {
      moveTo(point);

      return;
    }

    const choices = levels
      .map((level) => ({
        ...level,
        route: navigation.routeOn(mesh, [selected.x, selected.y], point, {
          agentRadius: selected.radius,
          fromLevel: selected.level ?? 0,
          toLevel: level.level,
        }),
      }))
      .filter((choice) => choice.route !== null);
    if (choices.length === 0) {
      return;
    }
    if (choices.length === 1) {
      const only = choices[0]?.route;
      if (only !== null && only !== undefined) {
        applyRoute(selected.id, only);
      }

      return;
    }

    menuPoint = point;
    levelMenu.value = {
      x: at?.[0] ?? 0,
      y: at?.[1] ?? 0,
      options: choices.map((choice) => ({
        level: choice.level,
        label:
          choice.level === 0
            ? 'Под настилом'
            : `На настил ${sceneUnits.formatUnits(Math.round(choice.elevation), options.resolution())}`,
      })),
    };
  }

  /** Маршрут выбранного актора и анимация движения. */
  function moveTo(point: ScenePoint, level?: number): boolean {
    const actor = selectedActor.value;
    const board = options.board();
    if (actor === null || board === null) {
      return false;
    }

    const result = navigation.route(board, options.elements(), options.resolution(), [actor.x, actor.y], point, {
      agentRadius: actor.radius,
      fromLevel: actor.level ?? 0,
      toLevel: level,
    });
    if (result === null || result.points.length < 2) {
      return false;
    }

    applyRoute(actor.id, result);

    return true;
  }

  /** Выбор уровня из меню. */
  function chooseLevel(level: number): void {
    const point = menuPoint;
    levelMenu.value = null;
    menuPoint = null;
    if (point !== null) {
      moveTo(point, level);
    }
  }

  /** Закрывает меню уровня. */
  function closeLevelMenu(): void {
    levelMenu.value = null;
    menuPoint = null;
  }

  /** Ставит маршрут и запускает движение. */
  function applyRoute(actorId: number, result: SceneNavRoute): void {
    route.value = result;
    startMotion(actorId, result);
  }

  /** Запускает движение по маршруту с уровнями. */
  function startMotion(actorId: number, path: SceneNavRoute): void {
    stopMotion();
    const { points, levels } = routePath(path);
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
    motion = { actorId, points, lengths, levels, total, traveled: 0, lastTime: now, lastGhostAt: now };
    frame = requestAnimationFrame(step);
  }

  /**
   * Точки маршрута с уровнем каждого отрезка.
   *
   * Участки делят общую точку на стыке уровней: для отрезка, выходящего со
   * стыка, берётся уровень следующего участка.
   */
  function routePath(path: SceneNavRoute): { points: ScenePoint[]; levels: number[] } {
    const points: ScenePoint[] = [];
    const levels: number[] = [];
    for (const segment of path.segments) {
      segment.points.forEach((point, index) => {
        const last = points[points.length - 1];
        if (
          index === 0 &&
          last !== undefined &&
          Math.hypot(last[0] - point[0], last[1] - point[1]) <= SCENE_NAVIGATION.linkEpsilon
        ) {
          levels[levels.length - 1] = segment.level;

          return;
        }

        points.push(point);
        levels.push(segment.level);
      });
    }

    return { points, levels };
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

    const { point, facing, index } = sampled;
    const board = options.board();
    const surface = board === null ? null : surfaceGrid(board);
    const level = motion.levels[index] ?? 0;
    actors.value = actors.value.map((actor) => {
      if (actor.id !== motion?.actorId) {
        return actor;
      }

      const moved: ScenePreviewActor = { ...actor, x: point[0], y: point[1], facing, level };

      return { ...moved, underDeck: surface === null ? false : isUnderDeck(surface, moved) };
    });

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

  /** Точка, facing и индекс отрезка на текущей длине маршрута. */
  function sampleMotion(current: Motion): { point: ScenePoint; facing: number; index: number } | null {
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

      return { point: [from[0] + dx * t, from[1] + dy * t], facing, index };
    }

    return null;
  }

  /** Обновляет призраков по обзору выбранного актора и признак «под настилом». */
  function updateGhosts(): void {
    const observer = selectedActor.value;
    const board = options.board();
    const surface = board === null || !options.enabled() ? null : surfaceGrid(board);
    const observerPoint: ScenePoint | null = observer === null ? null : [observer.x, observer.y];
    actors.value = actors.value.map((actor) => {
      const under = surface === null ? false : isUnderDeck(surface, actor);
      if (observer === null || observerPoint === null || surface === null || actor.id === observer.id) {
        return { ...actor, ghost: false, underDeck: under };
      }

      const distance = Math.hypot(actor.x - observer.x, actor.y - observer.y);
      // Уровни важны настилу: тот, кто на мосту, смотрит над настилом, тот,
      // кто под ним, — под настилом, и друг друга сквозь доски не видят.
      const visible =
        distance <= observer.sightRadius &&
        visibility.sees(
          surface,
          observerPoint,
          [actor.x, actor.y],
          undefined,
          undefined,
          observer.level ?? 0,
          actor.level ?? 0,
        );

      return { ...actor, ghost: !visible, underDeck: under };
    });
  }

  /**
   * Актор стоит под более высокой поверхностью.
   *
   * Клетка под настилом несёт конечный зазор, но на самом настиле актор тоже
   * стоит в такой клетке. Поэтому признак включается только на базовом уровне.
   */
  function isUnderDeck(surface: SceneSurfaceGrid, actor: SceneActor): boolean {
    if ((actor.level ?? 0) > 0) {
      return false;
    }

    return surfaceUnderDeck(surface, actor.x, actor.y);
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
            element.props.speed ?? '',
            element.props.height ?? '',
            element.props.elevation ?? '',
            element.props.rotation ?? '',
            element.props.scale ?? '',
            element.props.baseRadius ?? '',
            element.props.sightRadius ?? '',
            element.props.sightBottom ?? '',
            JSON.stringify(element.props.footprint ?? null),
            JSON.stringify(element.props.silhouette ?? null),
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
    levelMenu,
    select,
    setSightRadius,
    actorAt,
    onMapClick,
    moveTo,
    chooseLevel,
    closeLevelMenu,
  };
}
