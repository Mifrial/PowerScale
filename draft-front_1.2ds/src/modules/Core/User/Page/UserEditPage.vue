<template>
  <div>
    <div class="d-flex align-center mb-4 ga-3">
      <v-btn icon variant="text" @click="$router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
      <h1 class="text-h4 font-weight-bold">{{ isNew ? 'Новый пользователь' : 'Редактирование' }}</h1>
    </div>

    <v-card variant="outlined" max-width="480">
      <v-card-text>
        <UserForm
          :mode="isNew ? 'create' : 'edit'"
          :user="user ?? undefined"
          :saving="saving"
          @submit="handleSubmit"
          @cancel="$router.back()"
        />
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/modules/Core/User/Store/users'
import type { User } from '@/modules/Core/User/Interface/types'
import type { CreateUserData, UpdateUserData } from '@/modules/Core/User/Interface/IUserApi'
import UserForm from '@/modules/Core/User/Components/UserForm.vue'

const route = useRoute()
const router = useRouter()
const store = useUserStore()

const isNew = computed(() => route.name === 'UserNew')
const user = ref<User | null>(null)
const saving = ref(false)

const snackbar = ref({ show: false, text: '', color: '' })

async function handleSubmit(data: CreateUserData | UpdateUserData) {
  saving.value = true
  try {
    if (isNew.value) {
      await store.createUser(data as CreateUserData)
      snackbar.value = { show: true, text: 'Пользователь создан', color: 'success' }
    } else {
      const id = Number(route.params.id)
      await store.updateUser(id, data as UpdateUserData)
      snackbar.value = { show: true, text: 'Пользователь обновлён', color: 'success' }
    }
    router.push('/users')
  } catch {
    snackbar.value = { show: true, text: 'Ошибка сохранения', color: 'error' }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!isNew.value) {
    user.value = await store.getUser(Number(route.params.id))
  }
})
</script>
