<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';
import ItemStat from '@/modules/Roleplay/Rule/Component/Cards/Item/ItemStat.vue';

const props = defineProps<{
  profile: WeaponProfile;
  rules: Rule[];
}>();

const typeLabel = computed(() => ruleViewLabelService.profileType(props.profile.type));
const fields = computed(() => ruleViewLabelService.weaponProfileFields(props.profile, props.rules));
</script>

<template>
  <fieldset class="weapon-profile-card">
    <legend class="weapon-profile-card__type text-caption text-medium-emphasis">{{ typeLabel }}</legend>
    <div class="weapon-profile-card__grid">
      <ItemStat v-for="(field, index) in fields" :key="index" :label="field.label" :value="field.value" />
    </div>
  </fieldset>
</template>

<style scoped>
.weapon-profile-card {
  min-inline-size: 0;
  margin: 0;
  padding: 8px 12px 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}

.weapon-profile-card__type {
  margin: 0 auto;
  padding: 0 8px;
  font-weight: 500;
  line-height: 1;
}

.weapon-profile-card__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 8px 16px;
}
</style>
