<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import type { UserMacro } from '@/modules/Roleplay/Game/Dto/UserMacro'
import { useMacrosStore } from '@/modules/Roleplay/Game/Store/macros'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'
import MacroForm from '@/modules/Roleplay/Game/Component/Macros/MacroForm.vue'

const store = useMacrosStore()

const macros = computed(() => store.macros)
const formOpen = ref(false)
const editingMacro = ref<UserMacro | null>(null)

function openCreate() {
  editingMacro.value = null
  formOpen.value = true
}

function startEdit(m: UserMacro) {
  editingMacro.value = m
  formOpen.value = true
}

function closeForm() {
  formOpen.value = false
}

async function remove(m: UserMacro) {
  await store.removeMacro(m.id)
}

onMounted(() => store.fetchMacros())
</script>

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
              <span>{{ rollService.formatRollSpecText(r) }}</span>
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

      <MacroForm v-if="formOpen" :macro="editingMacro" @close="closeForm" />

      <v-btn v-if="!formOpen" variant="tonal" size="small" class="mt-2" @click="openCreate">
        <v-icon start size="small">mdi-plus</v-icon>Добавить макрос
      </v-btn>
    </v-card-text>
  </v-card>
</template>

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
.macro-sep {
  margin: 0 4px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
}
</style>
