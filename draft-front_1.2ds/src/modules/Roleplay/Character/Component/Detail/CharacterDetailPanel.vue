<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import { NotesSlider } from '@/modules/Messages/Notes/init';
import type { CharacterDetailNotes } from '@/modules/Roleplay/Character/Dto/CharacterDetailNotes';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import CharacterDetailHeader from '@/modules/Roleplay/Character/Component/Detail/CharacterDetailHeader.vue';
import CharacterSheetBody from '@/modules/Roleplay/Character/Component/Detail/CharacterSheetBody.vue';

/**
 * Слайдер деталки персонажа/НПС: шапка, вкладки листа и действия-ссылки.
 * Данные грузит вызывающий (standalone-страница или игровой контекст), панель только показывает.
 */
const props = withDefaults(
  defineProps<{
    name: string;
    icon?: string;
    raceLabel?: string | null;
    status?: CharacterStatus | null;
    chipLabel?: string | null;
    chipColor?: string | null;
    ownerName?: string | null;
    gameName?: string | null;
    revisionHref?: string | null;
    revisionLabel?: string | null;
    version: CharacterVersion | null;
    spaceId: number | null;
    visibleSections: SheetSection[];
    shortDescription?: string | null;
    fullDescription?: string | null;
    discussionChatId?: number | null;
    showDiscussion?: boolean;
    characterId?: number | null;
    showFavorites?: boolean;
    notes?: CharacterDetailNotes | null;
    editRoute?: string | null;
    migrateRoute?: string | null;
    pageRoute?: string | null;
    loading?: boolean;
    error?: string | null;
    /** Нет доступа или сущность удалена: показываем «Объект скрыт» вместо листа. */
    hidden?: boolean;
  }>(),
  {
    icon: 'mdi-account',
    raceLabel: null,
    status: null,
    chipLabel: null,
    chipColor: null,
    ownerName: null,
    gameName: null,
    revisionHref: null,
    revisionLabel: null,
    shortDescription: null,
    fullDescription: null,
    discussionChatId: null,
    showDiscussion: false,
    characterId: null,
    showFavorites: false,
    notes: null,
    editRoute: null,
    migrateRoute: null,
    pageRoute: null,
    loading: false,
    error: null,
    hidden: false,
  },
);

const emit = defineEmits<{ retry: [] }>();

const open = defineModel<boolean>('open', { default: false });
const notesOpen = ref(false);

const hasContent = computed(() => !props.loading && !props.error && !props.hidden);

watch(open, (value) => {
  if (!value) notesOpen.value = false;
});
</script>

<template>
  <SlidePanel
    v-model="open"
    width="calc(100vw - 120px)"
    max-width="1200px"
    :page-to="pageRoute"
    page-label="Открыть страницу"
  >
    <template #header>
      <div class="detail-panel__header">
        <v-icon size="small">{{ icon }}</v-icon>
        <span class="text-subtitle-1 text-truncate">{{ name }}</span>
        <v-spacer />
        <v-btn v-if="notes" variant="tonal" size="small" prepend-icon="mdi-note-text-outline" @click="notesOpen = true">
          Заметки
        </v-btn>
        <v-btn v-if="editRoute" variant="tonal" size="small" prepend-icon="mdi-pencil" :to="editRoute">
          Редактировать
        </v-btn>
        <v-btn v-if="migrateRoute" variant="tonal" size="small" prepend-icon="mdi-transfer" :to="migrateRoute">
          Перевести
        </v-btn>
      </div>
    </template>

    <div class="pa-4">
      <div v-if="hidden" class="text-center pa-8">
        <v-icon icon="mdi-eye-off-outline" size="64" color="disabled" class="mb-4" />
        <p class="text-body-1 mb-1">Объект скрыт</p>
        <p class="text-body-2 text-medium-emphasis mb-0">Лист недоступен для просмотра.</p>
      </div>

      <div v-else-if="loading" class="d-flex justify-center pa-8">
        <v-progress-circular indeterminate width="2" size="28" color="primary" />
      </div>

      <div v-else-if="error" class="text-center pa-8">
        <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
        <p class="text-body-1 mb-4">{{ error }}</p>
        <v-btn color="primary" @click="emit('retry')">Попробовать снова</v-btn>
      </div>

      <template v-else-if="hasContent">
        <CharacterDetailHeader
          :name="name"
          :race-label="raceLabel"
          :status="status"
          :chip-label="chipLabel"
          :chip-color="chipColor"
          :revision-href="revisionHref"
          :revision-label="revisionLabel"
          :owner-name="ownerName"
          :game-name="gameName"
        />
        <CharacterSheetBody
          :name="name"
          :version="version"
          :space-id="spaceId"
          :visible-sections="visibleSections"
          :short-description="shortDescription"
          :full-description="fullDescription"
          :character-id="characterId"
          :show-favorites="showFavorites"
          :discussion-chat-id="discussionChatId"
          :show-discussion="showDiscussion"
        />
      </template>
    </div>
  </SlidePanel>

  <NotesSlider
    v-if="notes"
    v-model:open="notesOpen"
    :host-type="notes.hostType"
    :host-id="notes.hostId"
    :title="notes.title"
    :inline-context="notes.inlineContext"
    :token-sources="notes.tokenSources"
  />
</template>

<style scoped>
.detail-panel__header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
  width: 100%;
  padding: 10px 12px;
}
</style>
