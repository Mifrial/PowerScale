<script setup lang="ts">
import { computed } from 'vue';
import { membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import MembershipDiffView from '@/modules/Roleplay/Game/Component/Detail/MembershipDiffView.vue';

const props = defineProps<{
  membership: GameCharacterMembership | null;
  actual: CharacterVersion | null;
  /** Имена правил (ruleCode → name) из ревизии игры. */
  names: Record<string, string>;
}>();

const open = defineModel<boolean>('open', { default: false });

/** Первая подача (нет approved): весь лист как «добавленные» секции; иначе — дельта approved → actual. */
const diff = computed(() => {
  const membership = props.membership;
  if (!membership || !props.actual) return null;

  return membershipDiff(
    membership.approvedCharacterVersion,
    props.actual,
    (ruleCode) => props.names[ruleCode] ?? `Удалённое правило (id: ${ruleCode})`,
  );
});
</script>

<template>
  <v-navigation-drawer temporary v-model="open" width="560">
    <div v-if="membership" class="d-flex flex-column h-100">
      <div class="drawer-head">
        <v-avatar color="primary" size="36" variant="tonal" class="flex-shrink-0">
          <span class="text-body-1">{{ membership.characterName.charAt(0) }}</span>
        </v-avatar>
        <div class="d-flex flex-column">
          <span class="text-subtitle-1">{{ membership.characterName }}</span>
          <span class="text-caption text-medium-emphasis"
            >владелец: {{ membership.characterOwnerName }} ·
            {{ membership.approvedCharacterVersion ? 'изменения' : 'первая подача' }}</span
          >
        </div>
        <v-spacer />
        <v-btn icon variant="text" size="small" @click="open = false"><v-icon>mdi-close</v-icon></v-btn>
      </div>
      <div class="drawer-body">
        <MembershipDiffView v-if="diff" :diff="diff" :names="names" />
      </div>
    </div>
  </v-navigation-drawer>
</template>

<style scoped>
.drawer-head {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.drawer-body {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 16px;
}
</style>
