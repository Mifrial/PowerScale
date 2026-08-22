<script setup lang="ts">
import { ref, watch } from 'vue';
import type { SheetVisibility, SheetVisibilityRule } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { SheetAudience } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { SheetSection } from '@/modules/Roleplay/Character/Enum/SheetSection';
import {
  SHEET_SECTION_LABELS,
  SHEET_VISIBLE_SECTIONS,
} from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import {
  SHEET_VISIBILITY_PRESETS,
  matchSheetVisibilityPreset,
} from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_VISIBILITY_PRESETS';

const props = defineProps<{
  title: string;
  visibility: SheetVisibility | null;
  members: GameMember[];
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
  save: [visibility: SheetVisibility];
}>();

type AudienceMode = 'all' | 'gm' | 'players';
const rules = ref<SheetVisibilityRule[]>([]);

const newAudienceMode = ref<AudienceMode>('all');
const newPlayerIds = ref<number[]>([]);
const newSections = ref<SheetSection[]>([]);

watch(
  () => props.visibility,
  (visibility) => {
    rules.value = cloneRules(visibility ?? []);
    newAudienceMode.value = 'all';
    newPlayerIds.value = [];
    newSections.value = [];
  },
  { immediate: true },
);

function cloneRules(visibility: SheetVisibility): SheetVisibilityRule[] {
  return visibility.map((rule) => ({ audience: cloneAudience(rule.audience), sections: [...rule.sections] }));
}

function cloneAudience(audience: SheetAudience): SheetAudience {
  return Array.isArray(audience) ? [...audience] : audience;
}

function applyPreset(key: 'full' | 'brief' | 'hidden'): void {
  const preset = SHEET_VISIBILITY_PRESETS.find((p) => p.key === key);
  if (!preset) return;
  rules.value = cloneRules(preset.value);
}

function audienceKey(audience: SheetAudience): string {
  return Array.isArray(audience) ? `users:${[...audience].sort().join(',')}` : audience;
}

function audienceLabel(audience: SheetAudience): string {
  if (audience === 'all') return 'Все участники';
  if (audience === 'gm') return 'Ведущие';
  const names = props.members.filter((member) => audience.includes(member.userId)).map((member) => member.userName);

  return names.length > 0 ? `Выбранные: ${names.join(', ')}` : 'Выбранные игроки';
}

function removeRule(index: number): void {
  rules.value = rules.value.filter((_, i) => i !== index);
}

const newAudienceOptions = [
  { value: 'all', label: 'Все участники' },
  { value: 'gm', label: 'Ведущие' },
  { value: 'players', label: 'Выбранные игроки' },
] as const;

const playerOptions = props.members.map((member) => ({ userId: member.userId, label: member.userName }));

function toggleNewSection(section: SheetSection): void {
  newSections.value = newSections.value.includes(section)
    ? newSections.value.filter((s) => s !== section)
    : [...newSections.value, section];
}

function addRule(): void {
  const audience: SheetAudience = newAudienceMode.value === 'players' ? [...newPlayerIds.value] : newAudienceMode.value;
  const key = audienceKey(audience);
  const withoutSame = rules.value.filter((rule) => audienceKey(rule.audience) !== key);
  rules.value = [...withoutSame, { audience, sections: [...newSections.value] }];
  newSections.value = [];
  newPlayerIds.value = [];
}

function save(): void {
  emit('save', cloneRules(rules.value));
}
</script>

<template>
  <v-dialog v-model="open" max-width="560">
    <v-card>
      <v-card-title>{{ title }}</v-card-title>
      <v-card-text>
        <div class="text-subtitle-2 mb-2">Готовые состояния</div>
        <div class="d-flex ga-2 mb-3">
          <v-btn
            v-for="preset in SHEET_VISIBILITY_PRESETS"
            :key="preset.key"
            size="small"
            :variant="matchSheetVisibilityPreset(rules) === preset.key ? 'tonal' : 'text'"
            :color="matchSheetVisibilityPreset(rules) === preset.key ? 'primary' : undefined"
            @click="applyPreset(preset.key)"
          >
            {{ preset.label }}
          </v-btn>
        </div>

        <v-divider class="my-3" />

        <div class="text-subtitle-2 mb-2">Зоны (правила)</div>
        <div v-if="rules.length === 0" class="text-body-2 text-medium-emphasis mb-2">
          Нет зон — лист скрыт от всех, кроме владельца и ведущих.
        </div>
        <v-list v-else density="compact" class="pa-0 mb-2">
          <v-list-item v-for="(rule, index) in rules" :key="audienceKey(rule.audience)">
            <div class="d-flex align-center ga-2 py-1">
              <span class="text-body-2">{{ audienceLabel(rule.audience) }}</span>
              <div class="d-flex ga-1 flex-wrap">
                <v-chip v-for="section in rule.sections" :key="section" size="x-small" variant="tonal">
                  {{ SHEET_SECTION_LABELS[section] }}
                </v-chip>
              </div>
              <v-spacer />
              <v-btn icon variant="text" size="x-small" @click="removeRule(index)">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </div>
          </v-list-item>
        </v-list>

        <v-divider class="my-3" />

        <div class="text-subtitle-2 mb-2">Добавить зону</div>
        <v-select
          v-model="newAudienceMode"
          :items="newAudienceOptions"
          item-title="label"
          item-value="value"
          label="Аудитория"
          density="compact"
        />
        <v-select
          v-if="newAudienceMode === 'players'"
          v-model="newPlayerIds"
          :items="playerOptions"
          item-title="label"
          item-value="userId"
          multiple
          chips
          label="Игроки"
          density="compact"
        />
        <div class="d-flex flex-column ga-1">
          <v-checkbox
            v-for="section in SHEET_VISIBLE_SECTIONS"
            :key="section"
            :model-value="newSections.includes(section)"
            :label="SHEET_SECTION_LABELS[section]"
            density="compact"
            hide-details
            @update:model-value="toggleNewSection(section)"
          />
        </div>
        <v-btn
          color="primary"
          variant="tonal"
          size="small"
          class="mt-2"
          :disabled="newAudienceMode === 'players' && newPlayerIds.length === 0"
          @click="addRule"
        >
          Добавить зону
        </v-btn>
        <div class="text-caption text-medium-emphasis mt-2">Имя видно всегда, когда лист видим. Ведущие видят всё.</div>
      </v-card-text>
      <v-card-actions>
        <v-btn color="primary" @click="save">Сохранить</v-btn>
        <v-btn variant="text" @click="open = false">Отмена</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
