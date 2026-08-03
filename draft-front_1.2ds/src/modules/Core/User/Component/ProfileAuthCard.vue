<script setup lang="ts">
import type { User } from '@/modules/Core/User/Dto/User'

const props = defineProps<{
  user: User
  editing: boolean
}>()

const formEmail = defineModel<string>('formEmail', { required: true })
</script>

<template>
  <v-card>
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">
        <v-icon start size="small" class="mb-1">mdi-shield-lock-outline</v-icon>
        Авторизация
      </v-card-title>
    </v-card-item>
    <v-divider />
    <v-list>
      <v-list-item>
        <template #title>Логин</template>
        <template #subtitle>{{ props.user.login }}</template>
      </v-list-item>
      <v-list-item>
        <template #title>Email</template>
        <template v-if="editing" #subtitle>
          <v-text-field v-model="formEmail" density="compact" hide-details variant="outlined" />
        </template>
        <template v-else #subtitle>{{ props.user.email }}</template>
      </v-list-item>
      <v-divider />
      <v-list-item>
        <template #title>Зарегистрирован</template>
        <template #subtitle>{{ props.user.registered || '—' }}</template>
      </v-list-item>
      <v-list-item>
        <template #title>Последний вход</template>
        <template #subtitle>{{ props.user.lastLogin || '—' }}</template>
      </v-list-item>
    </v-list>
  </v-card>
</template>
