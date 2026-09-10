<script setup lang="ts">
import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';
import MenuTreeNodeView from '@/modules/Core/UI/Component/Navigation/MenuTreeNode.vue';

defineProps<{
  node: MenuTreeNode;
}>();
</script>

<template>
  <v-list-item
    v-if="node.kind === 'menuItem'"
    :prepend-icon="node.icon"
    :title="node.title"
    :to="node.to"
    :exact="node.exact"
    color="primary"
  />
  <v-list-group v-else :value="node.id">
    <template #activator="{ props: activatorProps }">
      <v-list-item v-bind="activatorProps" :prepend-icon="node.icon" :title="node.title" />
    </template>
    <MenuTreeNodeView v-for="child in node.children" :key="child.id" :node="child" />
  </v-list-group>
</template>
