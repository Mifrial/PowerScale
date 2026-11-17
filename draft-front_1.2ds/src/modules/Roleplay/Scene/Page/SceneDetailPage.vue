<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SceneBoardTabs from '@/modules/Roleplay/Scene/Component/Scene/SceneBoardTabs.vue';
import SceneCanvas from '@/modules/Roleplay/Scene/Component/Scene/SceneCanvas.vue';
import SceneOverlayControls from '@/modules/Roleplay/Scene/Component/Scene/SceneOverlayControls.vue';
import ScenePreviewActorPanel from '@/modules/Roleplay/Scene/Component/Scene/ScenePreviewActorPanel.vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneOverlay } from '@/modules/Roleplay/Scene/Dto/SceneOverlay';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
import { useSceneFullscreen } from '@/modules/Roleplay/Scene/Composables/useSceneFullscreen';
import { useScenePreviewActors } from '@/modules/Roleplay/Scene/Composables/useScenePreviewActors';
import { getSceneApi } from '@/modules/Roleplay/Scene/init';
import { SceneApiError } from '@/modules/Roleplay/Scene/Service/SceneApiError';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const route = useRoute();
const router = useRouter();
const sceneId = Number(route.params.id);
const scene = ref<SceneDetail | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const activeBoardId = ref<number | null>(null);
const selection = ref<number[]>([]);
const viewport = ref<SceneViewport | null>(null);
const archiveOpen = ref(false);
const archiving = ref(false);
const canvas = ref<InstanceType<typeof SceneCanvas> | null>(null);
const overlayCosts = ref(false);
const overlayVisibility = ref(false);
const roofsHidden = ref(readRoofsHidden());
/** Показывать нижний этаж сквозь полупрозрачную плиту фокуса. */
const slabCutaway = ref(true);
const overlay = computed<SceneOverlay | null>(() =>
  overlayCosts.value || overlayVisibility.value
    ? { costs: overlayCosts.value, visibility: overlayVisibility.value, observer: null, radius: null }
    : null,
);
const {
  full: fullscreen,
  toggle: toggleFullscreen,
  close: closeFullscreen,
} = useSceneFullscreen({
  target: () => document.querySelector<HTMLElement>('.scene-detail'),
  fit: () => canvas.value?.fit(),
});
let requestId = 0;

const board = computed<SceneBoard | null>(
  () => scene.value?.boards.find((item) => item.id === activeBoardId.value) ?? null,
);
const {
  actors: previewActorList,
  selectedId: previewSelectedId,
  selectedActor: previewSelectedActor,
  selectedOnRoof: previewSelectedOnRoof,
  route: previewRoute,
  levelMenu: previewLevelMenu,
  select: selectPreviewActor,
  setSightRadius: setPreviewSightRadius,
  onMapClick: onPreviewClick,
  chooseLevel: previewChooseLevel,
} = useScenePreviewActors({
  enabled: () => scene.value !== null,
  board: () => board.value,
  elements: () => scene.value?.elements ?? [],
  resolution: () => scene.value?.resolution ?? 64,
  boards: () => scene.value?.boards ?? [],
  enclosures: () => scene.value?.enclosures ?? [],
  slabCutaway: () => slabCutaway.value,
});
const model = computed<SceneRenderModel>(() => ({
  activeBoardId: activeBoardId.value ?? 0,
  resolution: scene.value?.resolution ?? 64,
  boards: scene.value?.boards ?? [],
  elements: scene.value?.elements ?? [],
  enclosures: scene.value?.enclosures ?? [],
  selection: selection.value,
  mode: 'preview',
}));
const selectedElements = computed<SceneElement[]>(() =>
  model.value.elements.filter((element) => selection.value.includes(element.id)),
);
const boardElementCount = computed(
  () => model.value.elements.filter((element) => element.boardId === model.value.activeBoardId).length,
);
const boardSize = computed(() => {
  const current = board.value;
  const resolution = scene.value?.resolution ?? 64;
  if (current === null) {
    return '—';
  }

  return (
    `${sceneUnits.formatUnits(current.boundWidth, resolution)} × ` +
    `${sceneUnits.formatUnits(current.boundHeight, resolution)} ипари`
  );
});
const boardGrid = computed(() => {
  const current = board.value;
  if (current === null || current.gridStep <= 0) {
    return 'выключена';
  }

  return `${sceneUnits.formatUnits(current.gridStep, scene.value?.resolution ?? 64)} ипари`;
});

onMounted(load);
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

async function load(): Promise<void> {
  if (!Number.isFinite(sceneId) || sceneId <= 0) {
    error.value = 'Некорректный id локации';

    return;
  }

  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const detail = await getSceneApi().get(sceneId);
    if (current !== requestId) return;
    scene.value = detail;
    activeBoardId.value = detail.boards[0]?.id ?? null;
    selection.value = [];
  } catch (exception) {
    if (current !== requestId) return;
    error.value = messageOf(exception);
  } finally {
    if (current === requestId) {
      loading.value = false;
    }
  }
}

async function archive(): Promise<void> {
  archiving.value = true;
  error.value = null;
  try {
    await getSceneApi().archive(sceneId);
    archiveOpen.value = false;
    await router.push({ name: 'Scenes' });
  } catch (exception) {
    error.value = messageOf(exception);
  } finally {
    archiving.value = false;
  }
}

function onSelection(ids: number[]): void {
  selection.value = ids;
}

const ROOFS_HIDDEN_KEY = 'scene.roofsHidden';

/**
 * Крыши — настройка обзора, общая с редактором.
 *
 * По умолчанию скрыты: видно интерьер и пол. Показываются тумблером или сами,
 * когда выбранный актор стоит на проходимой крыше.
 */
function readRoofsHidden(): boolean {
  if (typeof window === 'undefined') {
    return true;
  }

  return window.localStorage.getItem(ROOFS_HIDDEN_KEY) !== '0';
}

/**
 * Крыши для холста: настройка обзора, но актор на проходимой крыше важнее —
 * иначе он стоит на невидимом настиле.
 */
const effectiveRoofsHidden = computed(() => roofsHidden.value && !previewSelectedOnRoof.value);

function setRoofsShown(value: boolean | null): void {
  roofsHidden.value = value !== true;
  window.localStorage.setItem(ROOFS_HIDDEN_KEY, roofsHidden.value ? '1' : '0');
}

/** Кнопка-иконка: показать или скрыть крыши. */
function toggleRoofs(): void {
  setRoofsShown(roofsHidden.value);
}

function onViewport(next: SceneViewport): void {
  viewport.value = next;
}

/** Esc выходит из полноэкранного просмотра, не уводя со страницы. */
function onKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape' || !fullscreen.value) return;

  event.preventDefault();
  void closeFullscreen();
}

function messageOf(exception: unknown): string {
  return exception instanceof SceneApiError ? exception.message : 'Не удалось загрузить локацию';
}

function zoomLabel(): string {
  return viewport.value === null ? '—' : `${Math.round(viewport.value.zoom * 100)}%`;
}
</script>

<template>
  <v-container fluid class="pa-0 scene-detail" :class="{ 'scene-detail--full': fullscreen }">
    <v-progress-linear v-if="loading" indeterminate />

    <v-alert v-if="error" type="error" variant="tonal" class="ma-4">
      {{ error }}
      <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
    </v-alert>

    <template v-if="scene !== null">
      <div class="d-flex align-center ga-3 pa-4 flex-wrap">
        <v-btn icon="mdi-arrow-left" variant="text" @click="router.push({ name: 'Scenes' })" />
        <div>
          <h1 class="text-h6">{{ scene.name }}</h1>
          <div class="text-caption text-medium-emphasis">
            Версия {{ scene.version }} · {{ scene.isArchived ? 'архив' : 'активна' }}
          </div>
        </div>
        <v-chip size="small" variant="tonal">локация</v-chip>
        <v-spacer />
        <v-btn variant="tonal" prepend-icon="mdi-magnify-minus" @click="canvas?.zoomBy(0.9)"> − </v-btn>
        <v-btn variant="tonal" prepend-icon="mdi-magnify-plus" @click="canvas?.zoomBy(1.1)"> + </v-btn>
        <v-btn variant="tonal" prepend-icon="mdi-fit-to-screen" @click="canvas?.fit()"> Вписать </v-btn>
        <v-tooltip location="bottom" :text="roofsHidden ? 'Показать крыши' : 'Скрыть крыши'">
          <template #activator="{ props: tooltipProps }">
            <v-btn
              v-bind="tooltipProps"
              variant="tonal"
              :icon="roofsHidden ? 'mdi-home-outline' : 'mdi-home-roof'"
              :color="roofsHidden ? undefined : 'primary'"
              @click="toggleRoofs"
            />
          </template>
        </v-tooltip>
        <v-tooltip location="bottom" :text="slabCutaway ? 'Скрыть нижний этаж' : 'Показать нижний этаж'">
          <template #activator="{ props: tooltipProps }">
            <v-btn
              v-bind="tooltipProps"
              variant="tonal"
              :icon="slabCutaway ? 'mdi-layers' : 'mdi-layers-off'"
              :color="slabCutaway ? 'primary' : undefined"
              @click="slabCutaway = !slabCutaway"
            />
          </template>
        </v-tooltip>
        <v-btn
          variant="tonal"
          :prepend-icon="fullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
          @click="toggleFullscreen"
        >
          {{ fullscreen ? 'Выйти' : 'На весь экран' }}
        </v-btn>
        <v-chip size="small" variant="outlined">{{ zoomLabel() }}</v-chip>
        <v-btn
          v-if="scene.canEdit"
          color="primary"
          variant="flat"
          prepend-icon="mdi-pencil"
          @click="router.push({ name: 'SceneEditor', params: { id: scene.id } })"
        >
          Редактировать
        </v-btn>
        <v-btn
          v-if="scene.canEdit"
          color="error"
          variant="tonal"
          prepend-icon="mdi-archive"
          @click="archiveOpen = true"
        >
          В архив
        </v-btn>
      </div>

      <SceneBoardTabs
        v-if="scene.boards.length > 1"
        :model-value="activeBoardId"
        :boards="scene.boards"
        :enclosures="scene.enclosures"
        :show-add="false"
        class="px-4"
        @update:model-value="activeBoardId = $event"
      />

      <div class="scene-workspace">
        <div class="scene-viewer">
          <SceneCanvas
            ref="canvas"
            :model="model"
            :board="board"
            :overlay="overlay"
            :roofs-hidden="effectiveRoofsHidden"
            :preview="true"
            :actors="previewActorList"
            :slab-cutaway="slabCutaway"
            :selected-actor-id="previewSelectedId"
            :observer-on-roof="previewSelectedOnRoof"
            :route="previewRoute"
            :level-menu="previewLevelMenu"
            @point="onPreviewClick"
            @level="previewChooseLevel"
            @selection="onSelection"
            @viewport="onViewport"
          />
          <SceneOverlayControls v-model:costs="overlayCosts" v-model:visibility="overlayVisibility" />
          <ScenePreviewActorPanel
            :actors="previewActorList"
            :selected="previewSelectedActor"
            :resolution="scene.resolution"
            @select="selectPreviewActor"
            @sight="setPreviewSightRadius"
          />
        </div>

        <aside class="scene-panel">
          <v-card variant="outlined" class="mb-4">
            <v-card-title class="text-subtitle-2">Поле</v-card-title>
            <v-card-text v-if="board !== null" class="text-body-2">
              <div class="d-flex justify-space-between">
                <span>Название</span><strong>{{ board.name }}</strong>
              </div>
              <div class="d-flex justify-space-between">
                <span>Границы</span><strong>{{ boardSize }}</strong>
              </div>
              <div class="d-flex justify-space-between">
                <span>Сетка</span><strong>{{ boardGrid }}</strong>
              </div>
              <div class="d-flex justify-space-between">
                <span>Точность</span><strong>{{ scene.resolution }} ед./ипари</strong>
              </div>
              <div class="d-flex justify-space-between">
                <span>Элементов</span><strong>{{ boardElementCount }}</strong>
              </div>
            </v-card-text>
            <v-card-text v-else class="text-medium-emphasis">Поле не выбрано</v-card-text>
          </v-card>

          <v-card variant="outlined">
            <v-card-title class="text-subtitle-2">Выделение</v-card-title>
            <v-card-text v-if="selectedElements.length === 0" class="text-medium-emphasis text-body-2">
              Кликните по элементу на поле.
            </v-card-text>
            <v-card-text v-else class="text-body-2">
              <div v-for="element in selectedElements" :key="element.id" class="mb-2">
                <div class="d-flex justify-space-between">
                  <strong>#{{ element.id }}</strong>
                  <v-chip size="x-small">{{ element.kind }}</v-chip>
                </div>
                <div class="text-medium-emphasis">{{ element.shape.type }}</div>
                <div v-if="element.props.label">{{ element.props.label }}</div>
              </div>
            </v-card-text>
          </v-card>
        </aside>
      </div>
    </template>

    <v-btn
      v-if="fullscreen"
      class="scene-detail__exit"
      icon="mdi-fullscreen-exit"
      size="small"
      variant="flat"
      color="surface-variant"
      title="Выйти из полноэкранного режима (Esc)"
      @click="closeFullscreen"
    />

    <v-dialog v-model="archiveOpen" max-width="440">
      <v-card>
        <v-card-title>Архивировать локацию?</v-card-title>
        <v-card-text> Локация исчезнет из обычного списка. Строки и данные сохранятся. </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="archiveOpen = false">Отмена</v-btn>
          <v-btn color="error" :loading="archiving" @click="archive">Архивировать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<style scoped>
.scene-detail {
  min-height: 100%;
}

.scene-detail--full {
  position: fixed;
  inset: 0;
  z-index: 4000;
  width: auto;
  max-width: none;
  height: 100dvh;
  margin: 0;
  padding: 0 !important;
  overflow: hidden;
  background: #0b1220;
}

.scene-detail--full > .d-flex,
.scene-detail--full > .v-tabs,
.scene-detail--full .scene-panel {
  display: none !important;
}

.scene-detail--full .scene-workspace {
  height: 100dvh;
  padding: 0;
}

.scene-detail--full .scene-viewer {
  height: 100dvh;
  border: 0;
  border-radius: 0;
}

.scene-detail__exit {
  position: fixed;
  top: 12px;
  right: 12px;
  z-index: 4100;
}

.scene-workspace {
  display: flex;
  gap: 16px;
  padding: 0 16px 16px;
  min-height: 0;
}

.scene-viewer {
  position: relative;
  flex: 1 1 auto;
  height: calc(100vh - 230px);
  min-height: 420px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, 0.25);
}

.scene-panel {
  flex: 0 0 280px;
  min-width: 240px;
}

@media (max-width: 900px) {
  .scene-workspace {
    flex-direction: column;
  }

  .scene-panel {
    flex-basis: auto;
  }
}
</style>
