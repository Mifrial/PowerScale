<script setup lang="ts">
import { onMounted } from 'vue';
import type { User } from '@/modules/Core/User/Dto/User';
import { useGroupStore } from '@/modules/Core/User/Store/groups';

const props = defineProps<{
  user: User;
}>();

const groupStore = useGroupStore();

onMounted(() => {
  void groupStore.ensureGroups(props.user.groups);
});
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
      <v-chip v-for="groupId in props.user.groups" :key="groupId" size="small" label class="mr-1 mb-1">
        {{ groupStore.getGroupName(groupId) }}
      </v-chip>
      <span v-if="!props.user.groups.length" class="text-caption text-medium-emphasis">Нет групп</span>
    </v-card-text>
  </v-card>
</template>
