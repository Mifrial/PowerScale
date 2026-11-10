<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SceneCanvas from '@/modules/Roleplay/Scene/Component/Scene/SceneCanvas.vue';
import type { SceneBoard } from '@/modules/Roleplay/Scene/Dto/SceneBoard';
import type { SceneDetail } from '@/modules/Roleplay/Scene/Dto/SceneDetail';
import type { SceneElement } from '@/modules/Roleplay/Scene/Dto/SceneElement';
import type { SceneRenderModel } from '@/modules/Roleplay/Scene/Dto/SceneRenderModel';
import type { SceneViewport } from '@/modules/Roleplay/Scene/Dto/SceneViewport';
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
let requestId = 0;

const board = computed<SceneBoard | null>(
  () => scene.value?.boards.find((item) => item.id === activeBoardId.value) ?? null,
);
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

function onViewport(next: SceneViewport): void {
  viewport.value = next;
}

function messageOf(exception: unknown): string {
  return exception instanceof SceneApiError ? exception.message : 'Не удалось загрузить локацию';
}

function zoomLabel(): string {
  return viewport.value === null ? '—' : `${Math.round(viewport.value.zoom * 100)}%`;
}
</script>

<template>
  <v-container fluid class="pa-0 scene-detail">
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

      <v-tabs v-if="scene.boards.length > 1" v-model="activeBoardId" density="compact" class="px-4">
        <v-tab v-for="item in scene.boards" :key="item.id" :value="item.id">
          {{ item.name }}
        </v-tab>
      </v-tabs>

      <div class="scene-workspace">
        <div class="scene-viewer">
          <SceneCanvas ref="canvas" :model="model" :board="board" @selection="onSelection" @viewport="onViewport" />
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

.scene-workspace {
  display: flex;
  gap: 16px;
  padding: 0 16px 16px;
  min-height: 0;
}

.scene-viewer {
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
