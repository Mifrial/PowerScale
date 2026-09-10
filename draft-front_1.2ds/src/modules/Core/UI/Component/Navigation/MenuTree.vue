<script setup lang="ts">
import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';
import MenuTreeNodeView from '@/modules/Core/UI/Component/Navigation/MenuTreeNode.vue';

defineProps<{
  nodes: MenuTreeNode[];
}>();
</script>

<template>
  <v-list density="compact" nav>
    <MenuTreeNodeView v-for="node in nodes" :key="node.id" :node="node" />
  </v-list>
</template>
