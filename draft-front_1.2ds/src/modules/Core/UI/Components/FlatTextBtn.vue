<template>
  <v-btn variant="text" class="flat-text-btn" v-bind="$attrs">
    <slot />
  </v-btn>
</template>

<style scoped>
.flat-text-btn {
  text-transform: none;
  letter-spacing: normal;
  font-weight: 500;
  transition: background 0.15s ease;
}
.flat-text-btn:hover {
  background: rgb(var(--v-theme-primaryLight));
}
</style>
