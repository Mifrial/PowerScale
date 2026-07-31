<template>
  <v-table hover density="compact" class="user-table">
    <thead>
      <tr>
        <th>Имя</th>
        <th>Логин</th>
        <th>Email</th>
        <th>Группы</th>
        <th>Статус</th>
        <th>Последний вход</th>
        <th />
      </tr>
    </thead>
    <tbody>
      <tr v-for="u in users" :key="u.id">
        <td class="font-weight-medium">{{ u.name }}</td>
        <td>{{ u.login }}</td>
        <td>{{ u.email }}</td>
        <td>
          <v-chip v-for="g in u.groups" :key="g" size="x-small" label class="mr-1">{{ g }}</v-chip>
          <span v-if="!u.groups.length" class="text-caption text-medium-emphasis">—</span>
        </td>
        <td>
          <v-icon :color="u.active ? 'success' : 'disabled'" size="small" class="mr-1">
            {{ u.active ? 'mdi-check-circle' : 'mdi-cancel' }}
          </v-icon>
          {{ u.active ? 'Активен' : 'Отключён' }}
        </td>
        <td class="text-medium-emphasis text-caption">{{ u.lastLogin || '—' }}</td>
        <td class="text-right">
          <v-btn variant="text" size="small" :to="`/users/${u.id}`">Открыть</v-btn>
        </td>
      </tr>
    </tbody>
  </v-table>
</template>

<script setup lang="ts">
import type { User } from '@/modules/Core/User/Interface/types'

defineProps<{
  users: User[]
}>()

</script>

<style scoped>
.user-table :deep(th) {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
}
</style>
