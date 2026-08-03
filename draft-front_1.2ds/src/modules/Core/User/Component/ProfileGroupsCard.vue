<script setup lang="ts">
import type { User } from '@/modules/Core/User/Dto/User'

const props = defineProps<{
  user: User
}>()
</script>

<template>
  <v-card>
    <v-card-item>
      <v-card-title class="text-body-1 font-weight-bold">
        <v-icon start size="small" class="mb-1">mdi-account-group-outline</v-icon>
        Группы
      </v-card-title>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <v-chip
        v-for="g in props.user.groups"
        :key="g"
        size="small"
        label
        class="mr-1 mb-1"
      >{{ g }}</v-chip>
      <span v-if="!props.user.groups.length" class="text-caption text-medium-emphasis">Нет групп</span>
    </v-card-text>
  </v-card>
</template>
