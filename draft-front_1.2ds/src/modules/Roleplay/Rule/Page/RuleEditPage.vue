<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ isEdit ? 'Редактирование правила' : 'Создание правила' }}</h1>
      <v-spacer />
      <v-chip color="warning" variant="tonal" size="small">
        Черновик
      </v-chip>
      <v-chip v-if="isRevisionContext" class="ml-2" variant="tonal" size="small">
        База: версия {{ route.params.ctx }}
      </v-chip>
    </div>

    <v-card>
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
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
          class="mt-4"
        />

        <CharacteristicEditor
          v-else-if="type === 'characteristic'"
          v-model:name="name"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
          :space-id="spaceId"
        />

        <ResourceEditor
          v-else-if="type === 'resource'"
          v-model:name="name"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
        />

        <AbilityEditor
          v-else-if="type === 'ability'"
          v-model:name="name"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
          :space-id="spaceId"
        />

        <ItemEditor
          v-else-if="type === 'item'"
          v-model:name="name"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          v-model:spec="spec"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
          :space-id="spaceId"
        />

        <DamageTypeEditor
          v-else-if="type === 'damage_type'"
          v-model:name="name"
          v-model:description="description"
          v-model:mechanicId="mechanicId"
          v-model:tagIds="tagIds"
          :mechanic-options="mechanicOptions"
          :tag-options="tagOptions"
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
import { useTagStore } from '@/modules/Roleplay/Rule/Tag/Store/tags'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import SimpleRuleEditor from '../Components/Editors/SimpleRuleEditor.vue'
import CharacteristicEditor from '../Components/Editors/CharacteristicEditor.vue'
import ResourceEditor from '../Components/Editors/ResourceEditor.vue'
import AbilityEditor from '../Components/Editors/AbilityEditor.vue'
import ItemEditor from '../Components/Editors/ItemEditor.vue'
import DamageTypeEditor from '../Components/Editors/DamageTypeEditor.vue'
import type { RuleType, Rule } from '../Interface/types'
import { fetchMechanics } from '../Service/mockMechanics'
import { slugify } from '@/modules/Core/Utils/slugify'

const route = useRoute()
const router = useRouter()
const spaceStore = useSpaceStore()
const store = useRuleStore()
const revisionStore = useSpaceRevisionStore()
const draftStore = useDraftRuleStore()
const tagStore = useTagStore()
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
const description = ref('')
const mechanicId = ref<number | null>(null)
const tagIds = ref<number[]>([])
const spec = ref<any>(null)
const saving = ref(false)

const showConflictDialog = ref(false)
const conflictRuleName = ref('')
const conflictRuleId = ref('')
const baseLoaded = ref<string | null>(null)

const ruleTypes = [
  { title: 'Простое правило', value: 'simple' },
  { title: 'Раса', value: 'race' },
  { title: 'Характеристика', value: 'characteristic' },
  { title: 'Ресурс', value: 'resource' },
  { title: 'Способность', value: 'ability' },
  { title: 'Предмет', value: 'item' },
  { title: 'Тип урона', value: 'damage_type' },
]

const mechanicOptions = ref<{ title: string; value: number }[]>([])
const tagOptions = computed(() =>
  tagStore.tags.map(t => ({ title: t.name, value: t.id }))
)

function buildRule(): Rule {
  const base = revisionStore.effectiveRules.find(r => r.id === ruleId.value)
  return {
    id: isEdit.value ? ruleId.value : `draft-${Date.now()}`,
    code: base?.code ?? slugify(name.value),
    type: type.value,
    name: name.value,
    description: description.value,
    spaceId: spaceId.value,
    spec: spec.value ?? undefined,
    tagIds: tagIds.value,
    mechanicId: mechanicId.value,
    createdAt: new Date().toISOString(),
  }
}

function loadRule(rule: Rule) {
  type.value = rule.type
  name.value = rule.name
  description.value = rule.description
  mechanicId.value = rule.mechanicId ?? null
  tagIds.value = rule.tagIds ?? []
  spec.value = rule.spec ?? null
}

async function resolveRoute(): Promise<void> {
  const key = `${code.value}|${ctx.value}|${ruleId.value}`
  if (baseLoaded.value === key) return
  baseLoaded.value = key

  const mechanics = await fetchMechanics(signal.value)
  mechanicOptions.value = mechanics.map(m => ({ title: `${m.name} (v${m.version})`, value: m.id }))

  await tagStore.fetchTags(signal.value)

  try {
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
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return
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
