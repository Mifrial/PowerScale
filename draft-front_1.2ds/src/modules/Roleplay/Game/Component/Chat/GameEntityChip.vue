<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import { useRoute } from 'vue-router';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';
import type { GameEntityChipContext } from '@/modules/Roleplay/Game/Dto/GameEntityChipContext';
import { GAME_ID_ROUTE_NAMES } from '@/modules/Roleplay/Game/Constant/Navigation/GAME_ID_ROUTE_NAMES';
import { CharacterDetailSlider } from '@/modules/Roleplay/Character/init';

const GameCharacterDetailSlider = defineAsyncComponent(
  () => import('@/modules/Roleplay/Game/Component/Detail/GameCharacterDetailSlider.vue'),
);
const NpcDetailSlider = defineAsyncComponent(
  () => import('@/modules/Roleplay/Game/Component/Detail/NpcDetailSlider.vue'),
);

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
  context?: GameEntityChipContext;
}>();

const route = useRoute();

const sliderOpen = ref(false);

const entityId = computed(() => Number(props.segment.params[0]));
const label = computed(() => {
  const rest = props.segment.params.slice(1).join(',').trim();

  return rest || props.segment.params[0] || '';
});
const entityKey = computed(() => {
  if (!Number.isInteger(entityId.value) || entityId.value <= 0) return null;
  if (props.segment.type === 'npc') return `npc:${entityId.value}`;

  return `character:${entityId.value}`;
});

/** Игра для деталки: из контекста заметки/чата, иначе — текущий маршрут игры. */
const gameId = computed<number | null>(() => {
  const fromContext = props.context?.gameId;
  if (typeof fromContext === 'number' && Number.isInteger(fromContext) && fromContext > 0) return fromContext;
  if (typeof route.name !== 'string' || !GAME_ID_ROUTE_NAMES.has(route.name)) return null;
  const raw = route.params.id;
  const id = typeof raw === 'string' ? Number(raw) : Number.NaN;

  return Number.isFinite(id) && id > 0 ? id : null;
});

const isGameCharacter = computed(
  () => props.segment.type === 'character' && props.context?.entityContext === 'game' && gameId.value !== null,
);

function open(): void {
  if (!entityKey.value) return;
  if (props.context?.openEntity) {
    props.context.openEntity(entityKey.value);

    return;
  }
  sliderOpen.value = true;
}
</script>

<template>
  <span
    class="game-entity-chip"
    role="button"
    tabindex="0"
    @click="open"
    @keydown.enter.prevent="open"
    @keydown.space.prevent="open"
    >{{ label }}</span
  >

  <GameCharacterDetailSlider
    v-if="sliderOpen && isGameCharacter"
    v-model:open="sliderOpen"
    :game-id="gameId"
    :character-id="entityId"
  />
  <CharacterDetailSlider
    v-else-if="sliderOpen && segment.type === 'character'"
    v-model:open="sliderOpen"
    :character-id="entityId"
  />
  <NpcDetailSlider
    v-else-if="sliderOpen && segment.type === 'npc'"
    v-model:open="sliderOpen"
    :game-id="gameId"
    :npc-id="entityId"
  />
</template>

<style scoped>
.game-entity-chip {
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
