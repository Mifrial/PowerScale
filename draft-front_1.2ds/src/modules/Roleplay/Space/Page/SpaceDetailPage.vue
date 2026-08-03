<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/RULE_TYPE_LABELS'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { Space } from '@/modules/Roleplay/Space/Dto/Space'
import PublishDialog from '@/modules/Roleplay/Space/Component/PublishDialog.vue'

const route = useRoute()
const router = useRouter()
const spaceStore = useSpaceStore()
const revisionStore = useSpaceRevisionStore()
const draftStore = useDraftRuleStore()
const { signal } = useAbortable()

const space = ref<Space | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const loadedCode = ref('')
const activeTab = ref<string>('all')
const searchQuery = ref('')
const showPublishDialog = ref(false)
const showDiscardDialog = ref(false)
const ruleToDiscard = ref<Rule | null>(null)
const snackbar = ref({ show: false, text: '', color: '' })

const ctx = computed(() => route.params.ctx as string | undefined)
const isDraftContext = computed(() => ctx.value === 'draft')

function formatPublished(iso: string): string {
  const d = new Date(iso)
  const date = d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' })
  const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  return `${date}, ${time}`
}

const revisionsList = computed(() => {
  const meta = revisionStore.revisionsMeta.get(space.value?.id ?? 0) ?? []
  const items = meta.map(m => ({
    label: `v${m.revision}: ${formatPublished(m.publishedAt)}`,
    value: m.revision,
  }))
  if (draftStore.hasDraft(space.value?.id ?? 0)) {
    items.push({ label: 'Черновик', value: -1 })
  }
  return items.reverse()
})

const selectedRevision = computed<number | null>({
  get() {
    const c = ctx.value
    if (c === 'draft') return -1
    if (c && /^\d+$/.test(c)) return Number(c)
    return null
  },
  set(v) {
    if (!space.value || v === null) return
    router.push(`/space/${space.value.code}/${v === -1 ? 'draft' : v}`)
  },
})

const filteredRules = computed(() => {
  let result = revisionStore.effectiveRules
  const tab = activeTab.value
  if (tab !== 'all') {
    result = result.filter(r => r.type === tab)
  }
  const q = searchQuery.value?.toLowerCase()
  if (q) {
    result = result.filter(r =>
      r.name.toLowerCase().includes(q) ||
      r.description.toLowerCase().includes(q)
    )
  }
  return result
})

function ruleLink(ruleId: string): string {
  if (!space.value) return ''
  return `/space/${space.value.code}/${ctx.value ?? ''}/rules/${ruleId}`
}

async function loadSpace(code: string): Promise<void> {
  space.value = await spaceStore.fetchSpaceByCode(code, signal.value)
  loadedCode.value = code
  await revisionStore.fetchRevisionsMeta(space.value.id, signal.value)
}

async function redirectPortal(): Promise<void> {
  if (!space.value) return
  if (draftStore.hasDraft(space.value.id)) {
    router.replace(`/space/${space.value.code}/draft`)
  } else {
    router.replace(`/space/${space.value.code}/${space.value.revision}`)
  }
}

async function applyContext(): Promise<void> {
  if (!space.value) return
  const id = space.value.id
  const c = ctx.value
  if (c === 'draft') {
    await revisionStore.syncFromContext(id, 'draft', space.value.revision, signal.value)
  } else if (c && /^\d+$/.test(c)) {
    await revisionStore.syncFromContext(id, 'rev', Number(c), signal.value)
  }
}

async function resolveRoute(): Promise<void> {
  const code = route.params.code as string
  const c = ctx.value
  loading.value = true
  error.value = null
  try {
    if (code !== loadedCode.value) {
      await loadSpace(code)
    }
    if (!space.value) return

    if (c === undefined) {
      await redirectPortal()
      return
    }
    if (c !== 'draft' && !/^\d+$/.test(c)) {
      router.replace(`/space/${code}`)
      return
    }
    await applyContext()
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return
    space.value = null
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки пространства'
  } finally {
    loading.value = false
  }
}

function retry() {
  loadedCode.value = ''
  resolveRoute()
}

onMounted(resolveRoute)

watch(() => [route.params.code, route.params.ctx], resolveRoute)

function openPublishDialog() {
  showPublishDialog.value = true
}

function onPublished(revision: number) {
  if (!space.value) return
  space.value = { ...space.value, revision }
  router.push(`/space/${space.value.code}/${revision}`)
}

function isRuleInDraft(ruleId: string): boolean {
  if (!space.value) return false
  const draftRules = draftStore.getDraftRules(space.value.id)
  return draftRules.some(r => r.id === ruleId)
}

function showDiscardRuleDialog(rule: Rule) {
  ruleToDiscard.value = rule
  showDiscardDialog.value = true
}

function discardRule() {
  if (!space.value || !ruleToDiscard.value) return
  draftStore.removeRule(space.value.id, ruleToDiscard.value.id)
  showDiscardDialog.value = false
  ruleToDiscard.value = null

  // Если черновиков больше нет, переходим на последнюю ревизию
  if (!draftStore.hasDraft(space.value.id)) {
    router.replace(`/space/${space.value.code}/${space.value.revision}`)
  }
}
</script>

<template>
  <v-container v-if="space">
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">{{ space.name }}</h1>
      <v-spacer />
      <v-btn variant="text" prepend-icon="mdi-cog" @click="router.push(`/space/${space.code}/settings`)">
        Настройки
      </v-btn>
    </div>

    <v-card-subtitle class="mb-4">{{ space.description }}</v-card-subtitle>

    <!-- Контекст просмотра -->
    <div class="d-flex align-center mb-4 gap-2">
      <v-select
        v-model="selectedRevision"
        :items="revisionsList"
        item-title="label"
        item-value="value"
        label="Версия"
        density="compact"
        hide-details
        style="max-width: 220px;"
      />

      <v-chip
        v-if="draftStore.hasDraft(space.id)"
        color="primary"
        variant="tonal"
        size="small"
      >
        Есть черновик
      </v-chip>

      <v-spacer />

      <template v-if="draftStore.hasDraft(space.id) && isDraftContext">
        <v-btn
          variant="tonal"
          color="success"
          size="small"
          prepend-icon="mdi-source-branch"
          @click="openPublishDialog"
        >
          Опубликовать
        </v-btn>
      </template>
    </div>

    <div class="d-flex align-center mb-4">
      <div class="text-h6">Правила ({{ filteredRules.length }})</div>
      <v-spacer />
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        @click="router.push(`/space/${space.code}/draft/rules/new`)"
      >
        Создать правило
      </v-btn>
    </div>

    <v-tabs v-model="activeTab" class="mb-4">
      <v-tab value="all">Все</v-tab>
      <v-tab value="simple">Простые</v-tab>
      <v-tab value="race">Расы</v-tab>
      <v-tab value="species">Виды</v-tab>
      <v-tab value="characteristic">Характеристики</v-tab>
      <v-tab value="resource">Ресурсы</v-tab>
      <v-tab value="points">Очки</v-tab>
      <v-tab value="ability">Способности</v-tab>
      <v-tab value="item">Предметы</v-tab>
      <v-tab value="damage_type">Типы урона</v-tab>
    </v-tabs>

    <v-text-field
      v-model="searchQuery"
      label="Поиск"
      prepend-inner-icon="mdi-magnify"
      clearable
      class="mb-4"
    />

    <v-list v-if="filteredRules.length > 0">
      <v-list-item
        v-for="rule in filteredRules"
        :key="rule.id"
        :to="ruleLink(rule.id)"
      >
        <v-list-item-title>
          {{ rule.name }}
          <v-chip
            v-if="isRuleInDraft(rule.id)"
            size="x-small"
            color="warning"
            variant="tonal"
            class="ml-2"
          >
            Изменено
          </v-chip>
        </v-list-item-title>
        <v-list-item-subtitle>{{ rule.description }}</v-list-item-subtitle>
        <template #append>
          <div class="d-flex align-center gap-2">
            <v-chip size="x-small" variant="tonal">
              {{ RULE_TYPE_LABELS[rule.type] }}
            </v-chip>
            <v-btn
              v-if="isDraftContext && isRuleInDraft(rule.id)"
              icon
              size="x-small"
              color="error"
              variant="text"
              @click.prevent="showDiscardRuleDialog(rule)"
            >
              <v-icon size="small">mdi-undo</v-icon>
            </v-btn>
          </div>
        </template>
      </v-list-item>
    </v-list>

    <div v-else class="text-body-2 text-medium-emphasis text-center pa-8">
      Правила не найдены
    </div>

    <!-- Publish dialog -->
    <PublishDialog
      v-model="showPublishDialog"
      :space="space"
      @published="onPublished"
      @error="(m) => snackbar = { show: true, text: m, color: 'error' }"
    />

    <!-- Discard rule dialog -->
    <v-dialog v-model="showDiscardDialog" max-width="500">
      <v-card>
        <v-card-title>Откатить изменения</v-card-title>
        <v-card-text>
          <div class="text-body-2 mb-4">
            Вы уверены, что хотите откатить изменения в правиле "{{ ruleToDiscard?.name }}"?
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Правило вернётся к состоянию из последней опубликованной версии.
          </div>
        </v-card-text>
        <v-card-actions>
          <v-btn variant="text" @click="showDiscardDialog = false">Отмена</v-btn>
          <v-btn color="error" variant="tonal" @click="discardRule">
            Откатить
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
  <div v-else-if="loading" class="d-flex justify-center pa-8">
    <v-progress-circular indeterminate width="2" size="28" color="primary" />
  </div>
  <div v-else-if="error" class="text-center pa-8">
    <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
    <p class="text-body-1 mb-4">{{ error }}</p>
    <v-btn color="primary" @click="retry">Попробовать снова</v-btn>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
.cursor-pointer {
  cursor: pointer;
}
</style>
