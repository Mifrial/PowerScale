<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { User } from '@/modules/Core/User/Dto/User';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import { visibleSheetSections } from '@/modules/Roleplay/Character/Utils/sheetAccess';
import SheetVisibilityDialog from '@/modules/Roleplay/Game/Component/Detail/SheetVisibilityDialog.vue';
import SheetCard from '@/modules/Roleplay/Character/Component/SheetCard.vue';

const router = useRouter();

export interface NpcCardData {
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  tags: string[];
  visibility: SheetVisibility;
}

const props = defineProps<{
  npc: GameNpc;
  isGm: boolean;
  user: User | null;
  members: GameMember[];
  spaceId: number | null;
  rulesRevision: number | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  save: [npcId: number, data: NpcCardData];
  delete: [npcId: number];
}>();

const visibilityOpen = ref(false);
const confirmDelete = ref(false);

const name = ref(props.npc.name);
const shortDescription = ref(props.npc.shortDescription ?? '');
const fullDescription = ref(props.npc.fullDescription ?? '');
const tags = ref<string[]>([...props.npc.tags]);

watch(
  () => props.npc,
  (npc) => {
    name.value = npc.name;
    shortDescription.value = npc.shortDescription ?? '';
    fullDescription.value = npc.fullDescription ?? '';
    tags.value = [...npc.tags];
    confirmDelete.value = false;
  },
  { immediate: true },
);

// У НПС нет владельца-игрока (ownerId null): видимость для игроков — по зонам, ведущие — через роль 'gm'.
const ctx = computed<SheetAccessContext | null>(() => {
  if (!props.user) return null;

  return { user: props.user, ownerId: null, characterId: props.npc.id, gameId: props.npc.gameId };
});

const visibleSections = computed(() => {
  if (!ctx.value) return [];

  return visibleSheetSections(props.user, props.npc.visibility, ctx.value);
});

function saveData(visibility: SheetVisibility = props.npc.visibility): NpcCardData {
  return {
    name: name.value.trim(),
    shortDescription: shortDescription.value.trim() || null,
    fullDescription: fullDescription.value.trim() || null,
    tags: tags.value,
    visibility,
  };
}

function save(): void {
  emit('save', props.npc.id, saveData());
}

function onVisibilitySave(visibility: SheetVisibility): void {
  emit('save', props.npc.id, saveData(visibility));
  visibilityOpen.value = false;
}

function confirmDeleteNpc(): void {
  emit('delete', props.npc.id);
}
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title class="d-flex align-center">
        <span class="text-h6">{{ npc.name }}</span>
        <v-chip v-if="npc.status === 'proposed'" color="warning" variant="tonal" size="x-small" class="ml-2">
          Предложен: {{ npc.proposedBy?.userName }}
        </v-chip>
        <v-spacer />
        <v-btn icon variant="text" size="small" @click="open = false"><v-icon>mdi-close</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <!-- Ведущий: редактирование полей + видимость -->
        <template v-if="isGm">
          <v-text-field v-model="name" label="Имя" />
          <v-text-field v-model="shortDescription" label="Краткое описание" />
          <v-textarea v-model="fullDescription" label="Полное описание" rows="2" />
          <v-combobox
            v-model="tags"
            label="Теги"
            hint="Описательные теги для поиска (роль, тип)"
            persistent-hint
            multiple
            chips
            small-chips
          />
          <v-alert v-if="npc.version === null" type="info" variant="tonal" density="compact" class="mt-2">
            Полный лист персонажа появится при редактировании как персонажа (следующий шаг).
          </v-alert>
        </template>

        <!-- Игрок: просмотр по видимости -->
        <template v-else>
          <div v-if="npc.tags.length > 0" class="mb-2">
            <div class="text-caption text-medium-emphasis mb-1">Теги</div>
            <div class="d-flex ga-1 flex-wrap">
              <v-chip v-for="tag in npc.tags" :key="tag" size="x-small" variant="tonal">#{{ tag }}</v-chip>
            </div>
          </div>
          <SheetCard
            :name="npc.name"
            :version="npc.version"
            :visible-sections="visibleSections"
            :space-id="spaceId"
            :rules-revision="rulesRevision"
            :short-description="npc.shortDescription"
            :full-description="npc.fullDescription"
          />
          <div v-if="visibleSections.length === 0" class="text-medium-emphasis text-body-2 mt-2">
            Информация о НПС скрыта ведущим.
          </div>
        </template>
      </v-card-text>
      <v-card-actions>
        <template v-if="isGm">
          <v-btn color="primary" @click="save">Сохранить</v-btn>
          <v-btn variant="tonal" @click="visibilityOpen = true">Видимость</v-btn>
          <v-btn
            variant="tonal"
            prepend-icon="mdi-book-open-page-variant-outline"
            @click="router.push(`/games/${npc.gameId}/npcs/${npc.id}/edit`)"
          >
            Редактировать лист
          </v-btn>
          <v-btn v-if="!confirmDelete" variant="text" color="error" @click="confirmDelete = true">Удалить</v-btn>
          <v-btn v-else variant="tonal" color="error" @click="confirmDeleteNpc">Подтвердить удаление</v-btn>
        </template>
        <v-spacer />
        <v-btn variant="text" @click="open = false">Закрыть</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <SheetVisibilityDialog
    v-model:open="visibilityOpen"
    :title="`Видимость НПС — ${npc.name}`"
    :visibility="npc.visibility"
    :members="members"
    @save="onVisibilitySave"
  />
</template>
