<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useCharacterCatalog } from '@/modules/Roleplay/Character/init';
import { useCurrentUser } from '@/modules/Core/User/init';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import SheetVisibilityDialog from '@/modules/Roleplay/Game/Component/Detail/SheetVisibilityDialog.vue';

const props = defineProps<{
  characterId: number;
}>();

const characterStore = useCharacterCatalog();
const { userId } = useCurrentUser();

const gameContexts = ref<CharacterGameContext[]>([]);
const visibilityOpen = ref(false);
const error = ref<string | null>(null);

const isOwner = computed(() => characterStore.currentCharacter?.character.ownerId === userId.value);

// Члены всех игр персонажа — для аудитории «выбранные игроки» в диалоге видимости.
const members = computed<GameMember[]>(() => {
  const seen = new Set<number>();
  const result: GameMember[] = [];
  for (const context of gameContexts.value) {
    for (const member of context.members) {
      if (seen.has(member.userId)) continue;
      seen.add(member.userId);
      result.push(member);
    }
  }

  return result;
});

const currentVisibility = computed<SheetVisibility | null>(() => {
  const character = characterStore.currentCharacter?.character;

  return character?.visibility ?? null;
});

async function load(): Promise<void> {
  try {
    gameContexts.value = await getGameApi().getCharacterGameContexts(props.characterId);
  } catch {
    gameContexts.value = [];
  }
}

async function saveVisibility(visibility: SheetVisibility): Promise<void> {
  error.value = null;
  try {
    const updated = await getCharacterApi().updateVisibility(props.characterId, visibility);
    if (characterStore.currentCharacter) {
      characterStore.currentCharacter.character.visibility = updated.visibility;
    }
    visibilityOpen.value = false;
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Не удалось сохранить видимость';
  }
}

onMounted(load);
</script>

<template>
  <v-card v-if="isOwner">
    <v-card-title class="text-subtitle-1 d-flex align-center">
      <span>Видимость листа</span>
      <v-spacer />
      <v-btn
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="mdi-eye-settings-outline"
        @click="visibilityOpen = true"
      >
        Настроить
      </v-btn>
    </v-card-title>
    <v-card-text>
      <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
      <div v-if="gameContexts.length === 0" class="text-caption text-medium-emphasis">
        Персонаж пока не в играх — видимость действует на странице персонажа.
      </div>
      <div v-else class="text-caption text-medium-emphasis">
        Видимость общая и действует во всех играх персонажа:
        <v-chip v-for="context in gameContexts" :key="context.gameId" size="x-small" variant="tonal" class="ml-1">
          {{ context.gameName }}
        </v-chip>
      </div>
    </v-card-text>
  </v-card>

  <SheetVisibilityDialog
    v-model:open="visibilityOpen"
    title="Видимость листа персонажа"
    :visibility="currentVisibility"
    :members="members"
    @save="saveVisibility"
  />
</template>
