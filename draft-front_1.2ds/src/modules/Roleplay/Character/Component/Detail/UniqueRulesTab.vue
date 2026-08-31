<script setup lang="ts">
import { useSpaceRevision } from '@/modules/Roleplay/Space/init';
import { computed, onMounted, ref } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CustomRuleEntry } from '@/modules/Roleplay/Character/Dto/CustomRuleEntry';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { useRouter } from 'vue-router';

const props = defineProps<{
  version: CharacterVersion;
  characterId: number;
  /** Управление записями (оформить/заменить): доступно ведущему игры в контексте игры. */
  canManage?: boolean;
  spaceId?: number;
  rulesRevision?: number;
}>();

const emit = defineEmits<{ updated: [] }>();

const router = useRouter();
const spaceRevision = useSpaceRevision();

const entries = computed<CustomRuleEntry[]>(() => props.version.customRules ?? []);
const rules = ref<Record<string, { name: string }>>({});
const activeEntries = computed(() => entries.value.filter((entry) => entry.status === 'active'));
const deprecatedEntries = computed(() => entries.value.filter((entry) => entry.status === 'deprecated'));

const replaceTarget = ref<CustomRuleEntry | null>(null);
const replaceOpen = ref(false);
const replacing = ref(false);
const replaceRuleId = ref<string | null>(null);

// Имена правил замены резолвим из ревизии (для устаревших записей), если задан контекст.
const replaceNames = computed(() => new Map(Object.entries(rules.value).map(([id, rule]) => [id, rule.name])));

async function loadRules(): Promise<void> {
  if (!props.spaceId || !props.rulesRevision) return;
  try {
    const revision = await spaceRevision.fetchRevision(props.spaceId, props.rulesRevision);
    rules.value = Object.fromEntries(revision.rules.map((rule) => [rule.code, { name: rule.name }]));
  } catch {
    rules.value = {};
  }
}

onMounted(loadRules);

function openReplace(entry: CustomRuleEntry): void {
  replaceTarget.value = entry;
  replaceRuleId.value = null;
  replaceOpen.value = true;
}

function ruleOptions(): { title: string; value: string }[] {
  return Array.from(replaceNames.value, ([id, name]) => ({ title: name, value: id }));
}

async function confirmReplace(): Promise<void> {
  const entry = replaceTarget.value;
  if (!entry || !replaceRuleId.value) return;
  replacing.value = true;
  try {
    await getCharacterApi().updateCustomRule(props.characterId, entry.id, {
      status: 'deprecated',
      replacedWithRuleCode: replaceRuleId.value,
    });
    replaceOpen.value = false;
    emit('updated');
  } finally {
    replacing.value = false;
  }
}

function openFormAsRule(entry: CustomRuleEntry): void {
  const query: Record<string, string> = { name: entry.name, type: entry.kind === 'item' ? 'item' : 'ability' };
  if (entry.description) query.description = entry.description;
  router.push({ path: `/space/${props.version.spaceCode}/draft/rules/new`, query });
}

function replaceLabel(entry: CustomRuleEntry): string {
  return entry.replacedWithRuleCode
    ? (replaceNames.value.get(entry.replacedWithRuleCode) ?? entry.replacedWithRuleCode)
    : '';
}
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-alert v-if="entries.length === 0" type="info" variant="tonal">
      У персонажа пока нет уникальных правил. Ведущий может выдать запись «на ходу» из карточки игры.
    </v-alert>

    <v-card v-if="activeEntries.length > 0">
      <v-card-title>Активные</v-card-title>
      <v-list>
        <v-list-item v-for="entry in activeEntries" :key="entry.id">
          <template #prepend>
            <v-icon>{{ entry.kind === 'item' ? 'mdi-package-variant' : 'mdi-lightning-bolt' }}</v-icon>
          </template>
          <v-list-item-title>{{ entry.name }}</v-list-item-title>
          <v-list-item-subtitle>{{ entry.description ?? '—' }}</v-list-item-subtitle>
          <template v-if="canManage" #append>
            <v-btn size="small" variant="tonal" class="mr-1" @click="openFormAsRule(entry)">
              Оформить как правило
            </v-btn>
            <v-btn size="small" variant="tonal" @click="openReplace(entry)">Заменить на правило</v-btn>
          </template>
        </v-list-item>
      </v-list>
    </v-card>

    <v-card v-if="deprecatedEntries.length > 0">
      <v-card-title>Устаревшие</v-card-title>
      <v-list>
        <v-list-item v-for="entry in deprecatedEntries" :key="entry.id">
          <template #prepend>
            <v-icon>mdi-history</v-icon>
          </template>
          <v-list-item-title>
            {{ entry.name }}
            <v-chip size="small" variant="tonal" color="warning" class="ml-2"
              >заменено: {{ replaceLabel(entry) }}</v-chip
            >
          </v-list-item-title>
          <v-list-item-subtitle>{{ entry.description ?? '—' }}</v-list-item-subtitle>
        </v-list-item>
      </v-list>
    </v-card>

    <v-dialog v-model="replaceOpen" max-width="480">
      <v-card>
        <v-card-title>Заменить на правило</v-card-title>
        <v-card-text>
          <v-autocomplete
            v-model="replaceRuleId"
            label="Правило"
            :items="ruleOptions()"
            item-title="title"
            item-value="value"
            hint="Правила ревизии игры"
            persistent-hint
            density="compact"
            clearable
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="replaceOpen = false">Отмена</v-btn>
          <v-btn color="primary" :loading="replacing" :disabled="!replaceRuleId" @click="confirmReplace">
            Заменить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
