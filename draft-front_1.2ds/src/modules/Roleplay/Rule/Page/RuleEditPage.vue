<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules'
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules'
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import SimpleRuleEditor from '@/modules/Roleplay/Rule/Component/Editors/SimpleRuleEditor.vue'
import CharacteristicEditor from '@/modules/Roleplay/Rule/Component/Editors/CharacteristicEditor.vue'
import ResourceEditor from '@/modules/Roleplay/Rule/Component/Editors/ResourceEditor.vue'
import AbilityEditor from '@/modules/Roleplay/Rule/Component/Editors/AbilityEditor.vue'
import ItemEditor from '@/modules/Roleplay/Rule/Component/Editors/ItemEditor.vue'
import RaceEditor from '@/modules/Roleplay/Rule/Component/Editors/RaceEditor.vue'
import SpeciesEditor from '@/modules/Roleplay/Rule/Component/Editors/SpeciesEditor.vue'
import PointsEditor from '@/modules/Roleplay/Rule/Component/Editors/PointsEditor.vue'
import DamageTypeEditor from '@/modules/Roleplay/Rule/Component/Editors/DamageTypeEditor.vue'
import RuleConflictDialog from '@/modules/Roleplay/Rule/Component/RuleConflictDialog.vue'
import { RULE_TYPES } from '@/modules/Roleplay/Rule/Constant/RULE_TYPES'
import { ruleDraftService } from '@/modules/Roleplay/Rule/Service/RuleDraftService'
import { ruleToForm } from '@/modules/Roleplay/Rule/Utils/Rule/formMapper'
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec'
import type { RuleFormState } from '@/modules/Roleplay/Rule/Dto/RuleFormState'
import { getRuleApi } from '@/modules/Roleplay/Rule/init'

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
const loadedCode = ref('')
const description = ref('')
const mechanicId = ref<number | null>(null)
const keywordIds = ref<number[]>([])

const spec = ref<RuleSpec | null>(null)
const saving = ref(false)
const loading = ref(true)
const error = ref<string | null>(null)

const showConflictDialog = ref(false)
const conflictRuleName = ref('')
const conflictRuleId = ref('')
const baseLoaded = ref<string | null>(null)

const mechanicOptions = ref<{ title: string; value: number }[]>([])
const keywordOptions = computed(() =>
  keywordStore.keywords.map(t => ({ title: t.name, value: t.id }))
)

function applyForm(form: RuleFormState) {
  type.value = form.type
  name.value = form.name
  ruleCode.value = form.code
  loadedCode.value = form.loadedCode
  description.value = form.description
  mechanicId.value = form.mechanicId
  keywordIds.value = form.keywordIds
  spec.value = form.spec
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
      const found = revisionStore.effectiveRules.find(r => r.id === ruleId.value)
      if (found) {
        applyForm(ruleToForm(found))
      } else {
        const rule = await store.fetchRule(ruleId.value, signal.value)
        applyForm(ruleToForm(rule))
      }

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
    const rule = ruleDraftService.createDraft({
      isEdit: isEdit.value,
      id: ruleId.value,
      type: type.value,
      name: name.value,
      code: ruleCode.value,
      loadedCode: loadedCode.value,
      description: description.value,
      spaceId: spaceId.value,
      spec: spec.value,
      keywordIds: keywordIds.value,
      mechanicId: mechanicId.value,
    })
    draftStore.saveRule(spaceId.value, rule)
    router.push(`/space/${code.value}/draft/rules/${rule.id}`)
  } catch (e) {
    console.error('save rule failed', e)
  } finally {
    saving.value = false
  }
}
</script>

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
          :items="RULE_TYPES"
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

    <RuleConflictDialog
      v-model:open="showConflictDialog"
      :rule-name="conflictRuleName"
      :base-version="ctx"
      @confirm="confirmConflict"
      @cancel="cancelConflict"
    />
  </v-container>
</template>
