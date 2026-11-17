import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { SceneActor } from '@/modules/Roleplay/Scene/Dto/SceneActor';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneBounds } from '@/modules/Roleplay/Scene/Dto/SceneBounds';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneEnclosure } from '@/modules/Roleplay/Scene/Dto/SceneEnclosure';
import type { SceneLevelMenu } from '@/modules/Roleplay/Scene/Dto/SceneLevelMenu';
import type { SceneNavRoute } from '@/modules/Roleplay/Scene/Dto/SceneNavMesh';
import type { ScenePoint } from '@/modules/Roleplay/Scene/Dto/ScenePoint';
import type { ScenePreviewActor } from '@/modules/Roleplay/Scene/Dto/ScenePreviewActor';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneSurfaceGrid } from '@/modules/Roleplay/Scene/Dto/SceneSurfaceGrid';
import { SCENE_ACTOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ACTOR';
import { SCENE_FLOOR } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_FLOOR';
import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_PREVIEW } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_PREVIEW';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';
import { sceneFloorPlanService } from '@/modules/Roleplay/Scene/Service/Instance/sceneFloorPlanService';
import {
  SceneFloorSightService,
  type SceneFloorSightScene,
} from '@/modules/Roleplay/Scene/Service/SceneFloorSightService';
import { SceneNavigationService } from '@/modules/Roleplay/Scene/Service/SceneNavigationService';
import { ScenePortalService } from '@/modules/Roleplay/Scene/Service/ScenePortalService';
import { SceneRouteService } from '@/modules/Roleplay/Scene/Service/SceneRouteService';
import {
  SceneSurfaceService,
  surfaceIndex,
  surfaceUnderDeck,
} from '@/modules/Roleplay/Scene/Service/SceneSurfaceService';
import { SceneVisibilityService } from '@/modules/Roleplay/Scene/Service/SceneVisibilityService';
import { sceneBodyHeight, sceneBodyIpari, sceneSightEyeIpari } from '@/modules/Roleplay/Scene/Service/SceneSight';

/** Внешние данные предпросмотра. */
export interface ScenePreviewActorOptions {
  enabled: () => boolean;
  board: () => SceneBoard | null;
  elements: () => SceneElement[];
  resolution: () => number;
  /** Все поля локации: маршрут через этажи идёт по нескольким полям. */
  boards: () => SceneBoard[];
  /** Вложения полей: по ним точка переносится между полями. */
  enclosures: () => SceneEnclosure[];
  /** Плита фокуса полупрозрачна: нижний этаж показан планом, а не перекрытием. */
  slabCutaway?: () => boolean;
}

/** Состояние движения по полилинии. */
interface Motion {
  actorId: number;
  points: ScenePoint[];
  lengths: number[];
  /** Уровень каждого отрезка: 0 — земля, больше — настил. */
  levels: number[];
  /** Поле каждого отрезка: на стыке этажей меняется. */
  boardIds: number[];
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
  let menuBoardId: number | null = null;
  const navigation = new SceneNavigationService();
  const routes = new SceneRouteService(navigation);
  const surfaces = new SceneSurfaceService();
  const visibility = new SceneVisibilityService();
  const floorSight = new SceneFloorSightService();
  const portals = new ScenePortalService();
  // Позиции актора по полям: перешедший на этаж манекен не должен пропадать
  // при смене поля и не должен прыгать к маркеру при возврате.
  const placements = new Map<number, Map<number, ScenePreviewActor>>();
  // Где актор сейчас: маркер задаёт стартовое поле, маршрут через этажи
  // переносит его на другое. Актор рисуется только на своём поле, иначе на
  // этаже оставалась бы его копия с прошлого визита.
  const actorBoards = new Map<number, number>();
  let gridKey = '';
  let grid: SceneSurfaceGrid | null = null;
  let sightKey = '';
  let sight: SceneFloorSightScene | null = null;
  let motion: Motion | null = null;
  let frame = 0;
  // Где стоял маркер при прошлой сборке: так видно, переставили ли маркер,
  // а не увели ли актора кликом.
  const markerAnchors = new Map<number, ScenePoint>();

  const selectedActor = computed(() => actors.value.find((actor) => actor.id === selectedId.value) ?? null);

  watch(
    [options.enabled, options.board, options.elements, options.resolution, options.boards, options.enclosures],
    () => {
      syncFromScene();
      prepareNavigation();
    },
  );
  // Тумблер разреза меняет перекрытие плиты: видимость чужих этажей
  // пересчитывается сразу, а не только на следующем клике.
  watch(
    () => options.slabCutaway?.() ?? true,
    () => updateGhosts(),
  );
  syncFromScene();
  prepareNavigation();

  onBeforeUnmount(() => stopMotion());

  /**
   * Строит навигацию заранее, а не в момент клика.
   *
   * Сетка по большому полю строится заметно долго; если делать это на первом
   * клике, актор «зависает» и прыгает с места. Кэш навигации живёт до смены
   * поля или элементов, поэтому подготовка переносит паузу на включение
   * предпросмотра.
   */
  function prepareNavigation(): void {
    const board = options.board();
    if (board === null || !options.enabled()) {
      return;
    }

    // Сетки всех видимых полей: маршрут через этажи идёт с поля актора, а он
    // может стоять на нижнем этаже, пока фокус на верхнем.
    const radius = selectedActor.value?.radius ?? Math.max(1, options.resolution() * SCENE_ACTOR.radiusIpari);
    for (const visible of visibleBoards(board, options.resolution())) {
      navigation.build(visible, options.elements(), options.resolution(), { agentRadius: radius });
    }
  }

  /**
   * Пересобирает акторов из маркеров видимых этажей.
   *
   * Актор принадлежит своему полю: с фокуса на втором этаже видны и манекены
   * двора. Позиция и уровень маркера читаются по поверхности его поля, а не
   * активного.
   */
  function syncFromScene(): void {
    stopMotion();
    route.value = null;
    if (!options.enabled()) {
      actors.value = [];
      selectedId.value = null;
      markerAnchors.clear();
      placements.clear();
      actorBoards.clear();

      return;
    }

    const board = options.board();
    if (board === null) {
      actors.value = [];
      selectedId.value = null;
      markerAnchors.clear();

      return;
    }

    const resolution = Math.max(1, options.resolution());
    const previous = new Map(actors.value.map((actor) => [actor.id, actor]));
    const visible = visibleBoards(board, resolution);
    const next: ScenePreviewActor[] = [];
    const markerIds = new Set<number>();
    for (const visibleBoard of visible) {
      const markers = options
        .elements()
        .filter(
          (element) =>
            element.boardId === visibleBoard.id &&
            element.kind === 'marker' &&
            element.shape.type === 'point' &&
            (element.props.role === 'player_start' || element.props.role === 'npc_spawn'),
        );
      for (const element of markers) {
        // Маркер — точка появления, а не второй экземпляр актора. Если актор
        // уже ушёл на другой этаж, его маркер здесь молчит.
        const currentBoard = actorBoards.get(element.id);
        if (currentBoard === undefined) {
          actorBoards.set(element.id, visibleBoard.id);
        } else if (currentBoard !== visibleBoard.id) {
          continue;
        }

        markerIds.add(element.id);
        const point = element.shape.type === 'point' ? element.shape : { x: 0, y: 0 };
        const role = element.props.role ?? 'player_start';
        const old = previous.get(element.id);
        // Маркер переставили — актор идёт за ним и берёт уровень по новому
        // месту: на настиле он стоит на настиле, а не под ним. Если двигали
        // самого актора, его позиция и уровень остаются прежними.
        const anchor = markerAnchors.get(element.id);
        const markerMoved = anchor === undefined || anchor[0] !== point.x || anchor[1] !== point.y;
        markerAnchors.set(element.id, [point.x, point.y]);
        if (markerMoved) {
          placements.get(element.id)?.delete(visibleBoard.id);
        }
        const placed = markerMoved ? undefined : placements.get(element.id)?.get(visibleBoard.id);

        next.push({
          id: element.id,
          boardId: visibleBoard.id,
          x: placed?.x ?? (markerMoved ? point.x : (old?.x ?? point.x)),
          y: placed?.y ?? (markerMoved ? point.y : (old?.y ?? point.y)),
          facing: placed?.facing ?? old?.facing ?? 0,
          radius: Math.max(1, resolution * SCENE_ACTOR.radiusIpari),
          height: sceneBodyHeight(resolution),
          tint: role === 'player_start' ? 0x8fb4ff : 0xffb38f,
          label: element.props.label || (role === 'player_start' ? 'Манекен' : 'Токен'),
          selected: element.id === selectedId.value,
          ghost: old?.ghost ?? false,
          hidden: false,
          level: placed?.level ?? (markerMoved ? markerLevel(visibleBoard, point) : (old?.level ?? 0)),
          underDeck: old?.underDeck ?? false,
          sightRadius:
            old?.sightRadius ??
            resolution * (role === 'player_start' ? SCENE_PREVIEW.sightIpari : SCENE_PREVIEW.tokenSightIpari),
          role,
        });
      }
    }

    for (const [actorId, byBoard] of placements) {
      const home = actorBoards.get(actorId);
      if (markerIds.has(actorId) || home === undefined) {
        continue;
      }

      const placed = byBoard.get(home);
      if (placed === undefined || !visible.some((item) => item.id === home)) {
        continue;
      }

      next.push({ ...placed, boardId: home, selected: actorId === selectedId.value });
    }

    actors.value = next;
    const onActive = next.find((actor) => actor.boardId === board.id);
    if (!next.some((actor) => actor.id === selectedId.value)) {
      selectedId.value = onActive?.id ?? next[0]?.id ?? null;
    }
    select(selectedId.value);
  }

  /**
   * Поля, видимые при текущем фокусе.
   *
   * @param board Активное поле.
   * @param resolution Разрешение локации.
   *
   * @returns Поля в порядке плана.
   */
  function visibleBoards(board: SceneBoard, resolution: number): SceneBoard[] {
    const plan = sceneFloorPlanService.plan({
      activeBoardId: board.id,
      resolution,
      boards: options.boards(),
      elements: options.elements(),
      enclosures: options.enclosures(),
      selection: [],
      mode: 'preview',
    });
    const byId = new Map(options.boards().map((item) => [item.id, item]));
    const result: SceneBoard[] = [];
    for (const id of plan.visibleBoardIds) {
      const found = byId.get(id);
      if (found !== undefined) {
        result.push(found);
      }
    }

    return result;
  }

  /**
   * Уровень маркера по месту.
   *
   * Настил — верхний уровень: маркер, поставленный на мост, стоит на мосту.
   * Под настил актор попадает только выбором в меню уровня, иначе признак
   * «под настилом» затемнял бы того, кто стоит на досках.
   */
  function markerLevel(board: SceneBoard, point: { x: number; y: number }): number {
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
    const updated = actors.value.find((actor) => actor.id === id);
    if (updated !== undefined && board !== null) {
      setPlacement(updated, board.id);
    }
    updateGhosts();
  }

  /** Запоминает позицию актора на поле: она переживает смену поля. */
  function setPlacement(actor: ScenePreviewActor, boardId: number): void {
    const byBoard = placements.get(actor.id) ?? new Map<number, ScenePreviewActor>();
    byBoard.set(boardId, { ...actor });
    placements.set(actor.id, byBoard);
  }

  /**
   * Актор под точкой; призраки тоже кликабельны.
   *
   * Точка — в координатах того поля, по которому кликнули, поэтому актор
   * ищется только на нём: соседний этаж кликается своей точкой.
   */
  function actorAt(point: ScenePoint, boardId: number): ScenePreviewActor | null {
    let best: ScenePreviewActor | null = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    for (const actor of actors.value) {
      // Скрытый за плитой или стеной актор не ловится кликом: его не видно.
      if (actor.hidden === true) {
        continue;
      }

      if ((actor.boardId ?? boardId) !== boardId) {
        continue;
      }

      const distance = Math.hypot(actor.x - point[0], actor.y - point[1]);
      if (distance <= Math.max(actor.radius * 1.3, options.resolution() * 0.9) && distance < bestDistance) {
        best = actor;
        bestDistance = distance;
      }
    }

    return best;
  }

  /**
   * Клик: выбрать актора, отправить по земле (Alt) или показать выбор уровня.
   *
   * Клик приходит с полем, по которому он сделан: видимые этажи кликаются
   * своими координатами, а маршрут между полями строит `SceneRouteService`.
   */
  function onMapClick(point: ScenePoint, under = false, at?: ScenePoint, boardId?: number): void {
    levelMenu.value = null;
    menuPoint = null;
    menuBoardId = null;

    const active = options.board();
    const targetId = boardId ?? active?.id ?? 0;
    const target = options.boards().find((item) => item.id === targetId) ?? null;
    const actor = actorAt(point, targetId);
    const selected = selectedActor.value;

    // Alt — всегда по земле целевого поля, мимо площадок: дверь на этаж не
    // должна перехватывать обычный проход под ней.
    if (under) {
      if (actor !== null) {
        select(actor.id);

        return;
      }
      if (selected !== null) {
        moveTo(targetId, point, 0);
      }

      return;
    }

    // Площадка лестницы — дверь на другой этаж. Клик по ней только ведёт к
    // ней: переносить актора на другое поле без шага нельзя. Дальше клик по
    // цели на другом этаже строит маршрут через площадку.
    if (selected !== null) {
      const tolerance = Math.max(1, options.resolution() * 0.1);
      const portal = portals.portalAt(options.elements(), targetId, point, tolerance);
      if (portal !== null && (actor === null || actor.id === selected.id)) {
        moveTo(targetId, portal.center);

        return;
      }
    }

    if (actor !== null) {
      select(actor.id);

      return;
    }

    if (selected === null || target === null) {
      return;
    }

    const mesh = navigation.build(target, options.elements(), options.resolution(), {
      agentRadius: selected.radius,
    });
    if (mesh === null) {
      moveTo(targetId, point);

      return;
    }

    const levels = navigation.levelsAt(mesh, point);
    if (levels.length === 0) {
      moveTo(targetId, point);

      return;
    }

    const choices = levels
      .map((level) => ({
        ...level,
        route: buildRoute(targetId, point, level.level),
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
    menuBoardId = targetId;
    levelMenu.value = {
      x: at?.[0] ?? 0,
      y: at?.[1] ?? 0,
      options: choices.map((choice) => ({
        level: choice.level,
        label:
          choice.level === 0
            ? 'Под настилом'
            : choice.elevation > 0
              ? `На настил ${sceneUnits.formatUnits(Math.round(choice.elevation), options.resolution())}`
              : 'На настил',
      })),
    };
  }

  /**
   * Модель рендера для маршрутизатора: поля, вложения и элементы.
   *
   * @returns Модель без выделения: маршруту оно не нужно.
   */
  function routeModel(): SceneRenderModel {
    return {
      activeBoardId: options.board()?.id ?? 0,
      resolution: options.resolution(),
      boards: options.boards(),
      elements: options.elements(),
      enclosures: options.enclosures(),
      selection: [],
      mode: 'preview',
    };
  }

  /**
   * Маршрут выбранного актора до точки на поле.
   *
   * @param boardId Поле цели.
   * @param point Точка в координатах цели.
   * @param level Уровень поверхности; без него — верхний.
   *
   * @returns Маршрут или null, если пути нет.
   */
  function buildRoute(boardId: number, point: ScenePoint, level?: number): SceneNavRoute | null {
    const actor = selectedActor.value;
    if (actor === null) {
      return null;
    }

    return routes.route(
      routeModel(),
      {
        boardId: actor.boardId ?? options.board()?.id ?? boardId,
        point: [actor.x, actor.y],
        level: actor.level ?? 0,
      },
      { boardId, point, level },
      { agentRadius: actor.radius },
    );
  }

  /** Маршрут выбранного актора и анимация движения. */
  function moveTo(boardId: number, point: ScenePoint, level?: number): boolean {
    const actor = selectedActor.value;
    if (actor === null) {
      return false;
    }

    const result = buildRoute(boardId, point, level);
    if (result === null || result.points.length < 2) {
      return false;
    }

    applyRoute(actor.id, result);

    return true;
  }

  /** Выбор уровня из меню. */
  function chooseLevel(level: number): void {
    const point = menuPoint;
    const boardId = menuBoardId;
    levelMenu.value = null;
    menuPoint = null;
    menuBoardId = null;
    if (point !== null && boardId !== null) {
      moveTo(boardId, point, level);
    }
  }

  /** Закрывает меню уровня. */
  function closeLevelMenu(): void {
    levelMenu.value = null;
    menuPoint = null;
    menuBoardId = null;
  }

  /** Ставит маршрут и запускает движение. */
  function applyRoute(actorId: number, result: SceneNavRoute): void {
    route.value = result;
    startMotion(actorId, result);
  }

  /** Запускает движение по маршруту с уровнями. */
  function startMotion(actorId: number, path: SceneNavRoute): void {
    stopMotion();
    const { points, levels, boardIds } = routePath(path);
    const lengths: number[] = [];
    let total = 0;
    for (let index = 1; index < points.length; index += 1) {
      // Стык этажей — одна точка мира в координатах разных полей: путь между
      // ними нулевой, иначе актор проехал бы по выдуманной прямой.
      const length =
        boardIds[index] === boardIds[index - 1]
          ? Math.hypot(points[index][0] - points[index - 1][0], points[index][1] - points[index - 1][1])
          : 0;
      lengths.push(length);
      total += length;
    }
    if (total <= 0) {
      return;
    }

    const now = performance.now();
    motion = { actorId, points, lengths, levels, boardIds, total, traveled: 0, lastTime: now, lastGhostAt: now };
    frame = requestAnimationFrame(step);
  }

  /**
   * Точки маршрута с уровнем каждого отрезка.
   *
   * Участки делят общую точку на стыке уровней: для отрезка, выходящего со
   * стыка, берётся уровень следующего участка.
   */
  function routePath(path: SceneNavRoute): { points: ScenePoint[]; levels: number[]; boardIds: number[] } {
    const points: ScenePoint[] = [];
    const levels: number[] = [];
    const boardIds: number[] = [];
    for (const segment of path.segments) {
      segment.points.forEach((point, index) => {
        const last = points[points.length - 1];
        const sameBoard = boardIds[boardIds.length - 1] === segment.boardId;
        if (
          index === 0 &&
          last !== undefined &&
          sameBoard &&
          Math.hypot(last[0] - point[0], last[1] - point[1]) <= SCENE_NAVIGATION.linkEpsilon
        ) {
          levels[levels.length - 1] = segment.level;

          return;
        }

        points.push(point);
        levels.push(segment.level);
        boardIds.push(segment.boardId);
      });
    }

    return { points, levels, boardIds };
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
    // Поле берётся у отрезка: на стыке этажей актор меняет поле, а не мир.
    const boardId = motion.boardIds[index] ?? options.board()?.id ?? 0;
    const board = options.boards().find((item) => item.id === boardId) ?? null;
    const surface = board === null ? null : surfaceGrid(board);
    const level = motion.levels[index] ?? 0;
    actors.value = actors.value.map((actor) => {
      if (actor.id !== motion?.actorId) {
        return actor;
      }

      const moved: ScenePreviewActor = { ...actor, boardId, x: point[0], y: point[1], facing, level };
      if (board !== null) {
        setPlacement(moved, boardId);
        actorBoards.set(moved.id, boardId);
      }

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
    // Обзор строится по полю наблюдателя: он может стоять на нижнем этаже,
    // пока фокус на верхнем.
    const board =
      observer === null
        ? options.board()
        : (options.boards().find((item) => item.id === observer.boardId) ?? options.board());
    const surface = board === null || !options.enabled() ? null : surfaceGrid(board);
    const observerPoint: ScenePoint | null = observer === null ? null : [observer.x, observer.y];
    const resolution = Math.max(1, options.resolution());
    const body = sceneBodyHeight(resolution);
    const observerBoardId = observer?.boardId ?? board?.id ?? null;
    const scene = observer === null ? null : floorSightScene();
    // В разрезе плита полупрозрачна: нижний этаж виден планом, и его акторы не
    // прячутся за перекрытием. Без разреза плита — настоящая преграда.
    const slabs = !(options.slabCutaway?.() ?? true);
    const cut = slabs ? undefined : cutFloor();
    actors.value = actors.value.map((actor) => {
      // Призраки — только на этаже наблюдателя: взаимного «невидения» между
      // NPC на одном этаже нет, а чужие этажи либо видны через проём, либо
      // скрыты плитой или стеной. Призрак — это «скрыт от наблюдателя, но
      // виден ведущему»; на другом этаже он просто не виден.
      if (observer !== null && observerBoardId !== null && (actor.boardId ?? observerBoardId) !== observerBoardId) {
        const visible =
          scene === null || observerPoint === null
            ? true
            : floorSight.sees(
                scene,
                {
                  boardId: observerBoardId,
                  point: observerPoint,
                  height: observer.height ?? body,
                },
                {
                  boardId: actor.boardId ?? observerBoardId,
                  point: [actor.x, actor.y],
                  height: actor.height ?? body,
                },
                { slabs, cut },
              );

        return { ...actor, ghost: false, hidden: !visible, underDeck: false };
      }

      const under = surface === null ? false : isUnderDeck(surface, actor);
      if (observer === null || observerPoint === null || surface === null || actor.id === observer.id) {
        return { ...actor, ghost: false, hidden: false, underDeck: under };
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
          sceneSightEyeIpari(observer.height ?? body, resolution),
          sceneBodyIpari(actor.height ?? body, resolution),
          observer.level ?? 0,
          actor.level ?? 0,
        );

      return { ...actor, ghost: !visible, hidden: false, underDeck: under };
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
            element.props.depth ?? '',
            element.props.shoreWidth ?? '',
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
            element.props.sill ?? '',
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

  /**
   * Препятствия этажей для межэтажного луча, с кэшем.
   *
   * Ключ — только элементы, которые перекрывают взгляд: террейн, стены и
   * проёмы. Декор и маркеры на видимость между этажами не влияют, поэтому их
   * правки не пересобирают сцену.
   */
  function floorSightScene(): SceneFloorSightScene | null {
    const boards = options.boards();
    if (boards.length === 0) {
      return null;
    }

    const resolution = options.resolution();
    const key = [
      resolution,
      boards.map((board) => `${board.id}:${board.boundWidth}:${board.boundHeight}`).join(','),
      options
        .enclosures()
        .map((item) => `${item.parentBoardId}:${item.childBoardId}:${item.elevation}:${JSON.stringify(item.footprint)}`)
        .join(','),
      options
        .elements()
        .filter((element) => element.kind === 'terrain' || element.kind === 'obstacle' || element.kind === 'opening')
        .map((element) =>
          [
            element.boardId,
            element.id,
            element.kind,
            element.targetBoardId ?? '',
            element.props.kind ?? '',
            element.props.blocksVision ?? '',
            JSON.stringify(element.shape),
          ].join(':'),
        )
        .join(','),
    ].join('|');

    if (sight === null || sightKey !== key) {
      sight = floorSight.scene({
        activeBoardId: options.board()?.id ?? boards[0]?.id ?? 0,
        resolution,
        boards,
        elements: options.elements(),
        enclosures: options.enclosures(),
        selection: [],
        mode: 'preview',
      });
      sightKey = key;
    }

    return sight;
  }

  /**
   * Пятно разреза: этаж под плитой фокуса и его границы в мире корня.
   *
   * Рисунок срезает стены этого этажа под плитой с запасом, поэтому и взгляд
   * сквозь них проходит. Границы нужны, чтобы не срезать забор во дворе:
   * снаружи пятна стены остаются.
   */
  function cutFloor(): { boardId: number; bounds: SceneBounds } | undefined {
    const board = options.board();
    if (board === null) {
      return undefined;
    }

    const resolution = options.resolution();
    const model: SceneRenderModel = {
      activeBoardId: board.id,
      resolution,
      boards: options.boards(),
      elements: options.elements(),
      enclosures: options.enclosures(),
      selection: [],
      mode: 'preview',
    };
    const plan = sceneFloorPlanService.plan(model);
    const below = plan.floors.find((floor) => floor.underCut);
    const active = plan.floors.find((floor) => floor.active);
    if (below === undefined || active === undefined) {
      return undefined;
    }

    // Режется нарисованный пол, а не всё поле: поля обзора вокруг плана — не
    // плита, и стены двора под ними остаются.
    const slab = sceneFloorPlanService.slabBounds(model, active.boardId);
    if (slab === null) {
      return undefined;
    }

    const margin = resolution * SCENE_FLOOR.cutMarginIpari;

    return {
      boardId: below.boardId,
      bounds: {
        x: active.transform.x + slab.x * active.transform.scaleX - margin,
        y: active.transform.y + slab.y * active.transform.scaleY - margin,
        width: slab.width * active.transform.scaleX + margin * 2,
        height: slab.height * active.transform.scaleY + margin * 2,
      },
    };
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
