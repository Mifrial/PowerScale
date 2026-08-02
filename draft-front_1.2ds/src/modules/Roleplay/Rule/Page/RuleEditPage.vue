<template>
  <v-container>
    <div v-if="!loading && !error" class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ isEdit ? 'Редактирование правила' : 'Создание правила' }}</h1>
      <v-spacer />
      <v-chip color="warning" variant="tonal" size="small">
        Черновик
      </v-chip>
      <v-chip v-if="isRevisionContext" class="ml-2" variant="tonal" size="small">
        База: версия {{ route.params.ctx }}
      </v-chip>
    </div>

    <v-card v-if="!loading && !error">
      <v-card-text>
        <v-select
          v-if="!isEdit"
          v-model="type"
          :items="ruleTypes"
          label="Тип правила"
          :rules="[v => !!v || 'Обязательное поле']"
        />

        <SimpleRuleEditor
          v-if="type === 'simple'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          class="mt-4"
        />

        <CharacteristicEditor
          v-else-if="type === 'characteristic'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          :space-id="spaceId"
        />

        <ResourceEditor
          v-else-if="type === 'resource'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
        />

        <AbilityEditor
          v-else-if="type === 'ability'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          :space-id="spaceId"
        />

        <ItemEditor
          v-else-if="type === 'item'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          :space-id="spaceId"
        />

        <RaceEditor
          v-else-if="type === 'race'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          :space-id="spaceId"
          :rule-id="ruleId"
        />

        <SpeciesEditor
          v-else-if="type === 'species'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
          :space-id="spaceId"
          :rule-id="ruleId"
        />

        <PointsEditor
          v-else-if="type === 'points'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
        />

        <DamageTypeEditor
          v-else-if="type === 'damage_type'"
          v-model:name="name"
          v-model:code="ruleCode"
          :code-disabled="isEdit"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:keywordIds="keywordIds"
          :mechanic-options="mechanicOptions"
          :keyword-options="keywordOptions"
        />

        <div v-else class="text-body-2 text-medium-emphasis text-center pa-8">
          Редактор для типа "{{ type }}" будет реализован позже
        </div>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
        </v-card-actions>
    </v-card>

    <div v-else-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
    <div v-else-if="error" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ error }}</p>
      <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
    </div>

    <!-- Conflict dialog: в черновике уже есть версия этого правила -->
    <v-dialog v-model="showConflictDialog" max-width="500" persistent>
      <v-card>
        <v-card-title>Замещение черновика</v-card-title>
        <v-card-text>
          <div class="text-body-2 mb-4">
            В черновике уже есть версия правила "{{ conflictRuleName }}".
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Её заменит версия v{{ route.params.ctx }}. Продолжить редактирование?
          </div>
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="cancelConflict">Отмена</v-btn>
          <v-btn color="primary" variant="tonal" @click="confirmConflict">
            Заменить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useRuleStore } from '../Store/rules'
import { useDraftRuleStore } from '../Store/draftRules'
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import SimpleRuleEditor from '../Component/Editors/SimpleRuleEditor.vue'
import CharacteristicEditor from '../Component/Editors/CharacteristicEditor.vue'
import ResourceEditor from '../Component/Editors/ResourceEditor.vue'
import AbilityEditor from '../Component/Editors/AbilityEditor.vue'
import ItemEditor from '../Component/Editors/ItemEditor.vue'
import RaceEditor from '../Component/Editors/RaceEditor.vue'
import SpeciesEditor from '../Component/Editors/SpeciesEditor.vue'
import PointsEditor from '../Component/Editors/PointsEditor.vue'
import DamageTypeEditor from '../Component/Editors/DamageTypeEditor.vue'
import type { RuleType } from '../Enum/RuleType'
import type { Rule } from '../Dto/Rule'
import { getRuleApi } from '../init'
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify'

const route = useRoute()
const router = useRouter()
const spaceStore = useSpaceStore()
const store = useRuleStore()
const revisionStore = useSpaceRevisionStore()
const draftStore = useDraftRuleStore()
const keywordStore = useKeywordStore()
const { signal } = useAbortable()

const code = computed(() => route.params.code as string)
const ctx = computed(() => route.params.ctx as string)
const ruleId = computed(() => route.params.ruleId as string)
const isEdit = computed(() => !!ruleId.value && ruleId.value !== 'new')
const isDraftContext = computed(() => ctx.value === 'draft')
const isRevisionContext = computed(() => !!ctx.value && ctx.value !== 'draft')

const spaceId = ref(0)
const type = ref<RuleType>('simple')
const name = ref('')
const ruleCode = ref('')
/** Исходный code загруженного правила — неизменяем после создания. */
const loadedCode = ref('')
const description = ref('')
const mechanicId = ref<number | null>(null)
const keywordIds = ref<number[]>([])
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'

const spec = ref<RuleSpec | null>(null)
const saving = ref(false)
const loading = ref(true)
const error = ref<string | null>(null)

const showConflictDialog = ref(false)
const conflictRuleName = ref('')
const conflictRuleId = ref('')
const baseLoaded = ref<string | null>(null)

const ruleTypes = [
  { title: 'Простое правило', value: 'simple' },
  { title: 'Раса', value: 'race' },
  { title: 'Вид/Подвид', value: 'species' },
  { title: 'Характеристика', value: 'characteristic' },
  { title: 'Ресурс', value: 'resource' },
  { title: 'Очки', value: 'points' },
  { title: 'Способность', value: 'ability' },
  { title: 'Предмет', value: 'item' },
  { title: 'Тип урона', value: 'damage_type' },
]

const mechanicOptions = ref<{ title: string; value: number }[]>([])
const keywordOptions = computed(() =>
  keywordStore.keywords.map(t => ({ title: t.name, value: t.id }))
)

function buildRule(): Rule {
  return {
    id: isEdit.value ? ruleId.value : `draft-${Date.now()}`,
    code: isEdit.value ? loadedCode.value : ruleCode.value.trim() || slugify(name.value),
    type: type.value,
    name: name.value,
    description: description.value,
    spaceId: spaceId.value,
    spec: spec.value ?? undefined,
    keywordIds: keywordIds.value,
    mechanicId: mechanicId.value,
    createdAt: new Date().toISOString(),
  }
}

function loadRule(rule: Rule) {
  type.value = rule.type
  name.value = rule.name
  ruleCode.value = rule.code
  loadedCode.value = rule.code
  description.value = rule.description
  mechanicId.value = rule.mechanicId ?? null
  keywordIds.value = rule.keywordIds ?? []
  spec.value = rule.spec ?? null
}

async function resolveRoute(): Promise<void> {
  const key = `${code.value}|${ctx.value}|${ruleId.value}`
  if (baseLoaded.value === key) return
  baseLoaded.value = key

  loading.value = true
  error.value = null

  try {
    const mechanics = await getRuleApi().getMechanics(signal.value)
    mechanicOptions.value = mechanics.map(m => ({ title: `${m.name} (v${m.version})`, value: m.id }))

    await keywordStore.fetchTags(signal.value)

    const space = await spaceStore.fetchSpaceByCode(code.value, signal.value)
    spaceId.value = space.id

    if (isDraftContext.value) {
      await revisionStore.syncFromContext(space.id, 'draft', space.revision, signal.value)
    } else if (isRevisionContext.value && /^\d+$/.test(ctx.value)) {
      await revisionStore.syncFromContext(space.id, 'rev', Number(ctx.value), signal.value)
    } else {
      router.replace(`/space/${code.value}`)
      return
    }

    if (isEdit.value) {
      // База из контекста (draft или ревизии) через effectiveRules
      const found = revisionStore.effectiveRules.find(r => r.id === ruleId.value)
      if (found) {
        loadRule(found)
      } else {
        const rule = await store.fetchRule(ruleId.value, signal.value)
        loadRule(rule)
      }

      // Конфликт: редактируем на основе ревизии, а в черновике уже есть версия
      if (isRevisionContext.value && draftHasRule(ruleId.value)) {
        conflictRuleName.value = name.value
        conflictRuleId.value = ruleId.value
        showConflictDialog.value = true
      }
    }
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки правила'
  } finally {
    loading.value = false
  }
}

function retry() {
  baseLoaded.value = null
  resolveRoute()
}

function draftHasRule(id: string): boolean {
  if (!spaceId.value) return false
  return draftStore.getDraftRules(spaceId.value).some(r => r.id === id)
}

function confirmConflict() {
  showConflictDialog.value = false
  conflictRuleName.value = ''
  conflictRuleId.value = ''
}

function cancelConflict() {
  showConflictDialog.value = false
  conflictRuleName.value = ''
  conflictRuleId.value = ''
  router.back()
}

onMounted(resolveRoute)
watch(() => [route.params.code, route.params.ctx, route.params.ruleId], resolveRoute)

async function save() {
  if (!name.value.trim()) return
  saving.value = true
  try {
    const rule = buildRule()
    draftStore.saveRule(spaceId.value, rule)
    router.push(`/space/${code.value}/draft/rules/${rule.id}`)
  } catch (e) {
    console.error('save rule failed', e)
  } finally {
    saving.value = false
  }
}
</script>
