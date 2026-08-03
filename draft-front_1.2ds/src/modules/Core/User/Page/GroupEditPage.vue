<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGroupStore } from '@/modules/Core/User/Store/groups'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import PermissionMatrix from '@/modules/Core/User/Component/PermissionMatrix.vue'

const route = useRoute()
const router = useRouter()
const store = useGroupStore()
const { signal } = useAbortable()

const isEdit = computed(() => !!route.params.id)
const groupId = computed(() => Number(route.params.id))

const name = ref('')
const permissions = ref<string[]>([])
const saving = ref(false)

onMounted(async () => {
  if (isEdit.value) {
    const group = await store.fetchGroup(groupId.value, signal.value)
    name.value = group.name
    permissions.value = [...group.permissions]
  }
})

async function save() {
  if (!name.value.trim()) return
  saving.value = true
  try {
    if (isEdit.value) {
      await store.updateGroup(groupId.value, { name: name.value, permissions: permissions.value }, signal.value)
    } else {
      await store.createGroup({ name: name.value, permissions: permissions.value }, signal.value)
    }
    router.push('/admin/groups')
  } catch (e) {
    console.error('save group failed', e)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование группы' : 'Создание группы' }}</h1>

    <v-card>
      <v-card-text>
        <v-text-field
          v-model="name"
          label="Название группы"
          :rules="[v => !!v || 'Обязательное поле']"
        />

        <div class="text-subtitle-1 mb-2">Права доступа</div>
        <PermissionMatrix v-model="permissions" />
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>
