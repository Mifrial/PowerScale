<template>
  <v-card class="profile-card">
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">
        <v-icon start size="small" class="mb-1">mdi-script-text-outline</v-icon>
        Макросы
      </v-card-title>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <div v-if="!macros.length && !formOpen" class="text-caption text-medium-emphasis mb-2">
        Макросов пока нет — добавьте преднастроенное сообщение, чтобы быстро отправлять его в чат.
      </div>

      <div v-for="m in macros" :key="m.id" class="macro-row">
        <div class="macro-info">
          <div class="font-weight-medium">{{ m.name }}</div>
          <div class="text-caption text-medium-emphasis">
            <span v-if="m.textTemplate">{{ m.textTemplate }}</span>
            <template v-for="(r, ri) in m.rolls" :key="ri">
              <span class="macro-sep">+</span>
              <span>{{ macroSpecText(r) }}</span>
            </template>
          </div>
        </div>
        <div class="macro-actions">
          <v-btn icon variant="text" size="x-small" aria-label="Редактировать" @click="startEdit(m)">
            <v-icon size="16">mdi-pencil</v-icon>
          </v-btn>
          <v-btn icon variant="text" size="x-small" color="error" aria-label="Удалить" @click="remove(m)">
            <v-icon size="16">mdi-delete</v-icon>
          </v-btn>
        </div>
      </div>

      <div v-if="formOpen" class="macro-form mt-2">
        <v-text-field v-model="form.name" label="Название" density="compact" variant="outlined" hide-details class="mb-2" />
        <v-text-field v-model="form.textTemplate" label="Текст сообщения (опционально)" density="compact" variant="outlined" hide-details class="mb-2" />

        <div class="text-caption font-weight-medium mb-1">Броски</div>
        <div v-for="(roll, ri) in form.rolls" :key="ri" class="roll-editor mb-2">
          <div class="roll-editor-header">
            <span class="text-caption font-weight-medium">Бросок {{ ri + 1 }}</span>
            <v-btn icon variant="text" size="x-small" aria-label="Удалить бросок" @click="removeRoll(ri)">
              <v-icon size="16">mdi-close</v-icon>
            </v-btn>
          </div>

          <div class="d-flex align-center ga-2 mb-2">
            <v-text-field
              v-model.number="roll.diceCount"
              label="Кубы"
              type="number"
              min="1"
              max="30"
              density="compact"
              variant="outlined"
              hide-details
              class="macro-num"
            />
            <span class="text-caption">d</span>
            <v-text-field
              v-model.number="roll.dieFaces"
              label="Грань"
              type="number"
              min="2"
              max="100"
              density="compact"
              variant="outlined"
              hide-details
              class="macro-num"
            />
            <span class="text-caption">при</span>
            <v-text-field
              v-model.number="roll.efficiency"
              label="Эффективность"
              type="number"
              min="1"
              max="20"
              density="compact"
              variant="outlined"
              hide-details
              class="macro-num"
            />
          </div>

          <div class="d-flex align-center ga-2 mb-2">
            <v-text-field
              v-model.number="roll.adv"
              label="Преимущества"
              type="number"
              min="-10"
              max="10"
              density="compact"
              variant="outlined"
              hide-details
              class="macro-num"
            />
            <span class="text-caption">отрицательное = помеха</span>
            <v-text-field
              v-model.number="roll.dieSize"
              label="Размерность"
              type="number"
              min="-10"
              max="10"
              density="compact"
              variant="outlined"
              hide-details
              class="macro-num"
            />
          </div>

          <v-text-field
            v-model="roll.rollLabel"
            label="Подпись броска (опционально)"
            placeholder="например: 1 удар, уклонение"
            density="compact"
            variant="outlined"
            hide-details
            class="mb-1"
          />

          <v-checkbox
            v-model="roll.variableAdvantages"
            label="Переменные преимущества — спрашивать число при отправке"
            density="compact"
            hide-details
          />
        </div>

        <v-btn variant="tonal" size="small" class="mb-2" @click="addRoll">
          <v-icon start size="small">mdi-plus</v-icon>Добавить бросок
        </v-btn>

        <div class="macro-preview mb-2">
          <span v-if="form.textTemplate.trim()" class="macro-preview-text">{{ form.textTemplate.trim() }}</span>
          <template v-for="(roll, ri) in form.rolls" :key="ri">
            <span class="macro-sep">+</span>
            <v-icon icon="mdi-dice-d6" size="14" />
            <span>{{ rollPreviewText(roll) }}</span>
          </template>
          <span v-if="!form.textTemplate.trim() && !form.rolls.length" class="text-caption text-medium-emphasis">макрос пуст — задайте текст или добавьте бросок</span>
        </div>

        <div class="d-flex ga-2">
          <v-btn variant="tonal" color="primary" size="small" :loading="saving" :disabled="!formValid" @click="save">
            <v-icon start size="small">mdi-check</v-icon>Сохранить
          </v-btn>
          <v-btn variant="text" size="small" @click="closeForm">Отмена</v-btn>
        </div>
      </div>

      <v-btn v-if="!formOpen" variant="tonal" size="small" class="mt-2" @click="openCreate">
        <v-icon start size="small">mdi-plus</v-icon>Добавить макрос
      </v-btn>
    </v-card-text>
  </v-card>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import type { MacroRollSpec } from '@/modules/Roleplay/Game/Dto/MacroRollSpec'
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro'
import { useMacrosStore } from '@/modules/Roleplay/Game/Store/macros'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'

interface RollForm {
  diceCount: number
  dieFaces: number
  efficiency: number
  dieSize: number
  adv: number
  rollLabel: string
  variableAdvantages: boolean
}

const store = useMacrosStore()

const macros = computed(() => store.macros)
const formOpen = ref(false)
const saving = ref(false)
const form = ref<{ id?: number; name: string; textTemplate: string; rolls: RollForm[] }>({
  name: '',
  textTemplate: '',
  rolls: [],
})

const formValid = computed(() => {
  if (!form.value.name.trim()) return false
  const hasContent = form.value.textTemplate.trim().length > 0 || form.value.rolls.length > 0
  if (!hasContent) return false
  return form.value.rolls.every(rollValid)
})

function rollValid(r: RollForm): boolean {
  const diceCount = Number(r.diceCount)
  const dieFaces = Number(r.dieFaces)
  const eff = Number(r.efficiency)
  const adv = Number(r.adv)
  const dieSize = Number(r.dieSize)
  if (!Number.isInteger(diceCount) || diceCount < 1 || diceCount > 30) return false
  if (!Number.isInteger(dieFaces) || dieFaces < 2 || dieFaces > 100) return false
  if (!Number.isInteger(eff) || eff < 1 || eff > 20) return false
  if (!Number.isInteger(adv) || Math.abs(adv) > 10) return false
  if (!Number.isInteger(dieSize) || Math.abs(dieSize) > 10) return false
  return true
}

function emptyRoll(): RollForm {
  return { diceCount: 5, dieFaces: 6, efficiency: 3, dieSize: 0, adv: 0, rollLabel: '', variableAdvantages: false }
}

function addRoll() {
  form.value.rolls.push(emptyRoll())
}

function removeRoll(index: number) {
  form.value.rolls.splice(index, 1)
}

function specText(r: { rollFormula: string; efficiency: number; adv: number; dieSize: number; rollLabel?: string; variableAdvantages: boolean }): string {
  const adv = r.adv || 0
  const advPart = adv ? (adv > 0 ? ` +${adv}` : ` ${adv}`) : ''
  const size = rollService.formatRollSize(r.dieSize || 0)
  const label = r.rollLabel?.trim()
  return `${r.rollFormula}${advPart}${size ? ` ${size}` : ''} · сл:${r.efficiency}${label ? ` (${label})` : ''}${r.variableAdvantages ? ' · преим. ?' : ''}`
}

function macroSpecText(r: MacroRollSpec): string {
  return specText(r)
}

function rollPreviewText(r: RollForm): string {
  return specText({
    rollFormula: `${r.diceCount}d${r.dieFaces}`,
    efficiency: Number(r.efficiency),
    adv: Number(r.adv),
    dieSize: Number(r.dieSize),
    rollLabel: r.rollLabel,
    variableAdvantages: r.variableAdvantages,
  })
}

function openCreate() {
  form.value = { name: '', textTemplate: '', rolls: [emptyRoll()] }
  formOpen.value = true
}

function startEdit(m: UserMacro) {
  form.value = {
    id: m.id,
    name: m.name,
    textTemplate: m.textTemplate,
    rolls: m.rolls.map(r => {
      const parsed = r.rollFormula ? rollService.parseRollFormula(r.rollFormula) : null
      return {
        diceCount: parsed?.diceCount ?? 5,
        dieFaces: parsed?.dieFaces ?? 6,
        efficiency: r.efficiency,
        dieSize: r.dieSize ?? 0,
        adv: r.adv ?? 0,
        rollLabel: r.rollLabel ?? '',
        variableAdvantages: r.variableAdvantages ?? false,
      }
    }),
  }
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
}

async function save() {
  if (!formValid.value) return
  saving.value = true
  try {
    const data = {
      name: form.value.name.trim(),
      textTemplate: form.value.textTemplate.trim(),
      rolls: form.value.rolls.map(r => ({
        rollFormula: `${r.diceCount}d${r.dieFaces}`,
        efficiency: Number(r.efficiency),
        adv: Number(r.adv),
        dieSize: Number(r.dieSize),
        rollLabel: r.rollLabel.trim() || undefined,
        variableAdvantages: r.variableAdvantages,
      })),
    }
    if (form.value.id != null) {
      await store.updateMacro(form.value.id, data)
    } else {
      await store.createMacro(data)
    }
    closeForm()
  } finally {
    saving.value = false
  }
}

async function remove(m: UserMacro) {
  await store.removeMacro(m.id)
}

onMounted(() => store.fetchMacros())
</script>

<style scoped>
.profile-card {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.macro-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}
.macro-row:last-child {
  border-bottom: none;
}
.macro-info {
  min-width: 0;
}
.macro-actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.macro-num {
  max-width: 100px;
}
.macro-sep {
  margin: 0 4px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
.roll-editor {
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  border-radius: 8px;
  padding: 6px 8px;
}
.roll-editor-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.macro-preview {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  background: rgba(var(--v-theme-primaryLight), 0.5);
  border-radius: 16px;
  padding: 3px 12px;
  font-size: 12px;
}
.macro-preview-text {
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
