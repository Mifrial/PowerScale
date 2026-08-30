<script setup lang="ts">
import { computed } from 'vue';
import { parseChronicleContent } from '@/modules/Roleplay/Game/Utils/chronicleInline';
import type { ChronicleEntry } from '@/modules/Roleplay/Game/Dto/ChronicleEntry';
import type { ChronicleRef } from '@/modules/Roleplay/Game/Dto/ChronicleRef';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

const props = defineProps<{
  entry: ChronicleEntry;
  memberships: GameCharacterMembership[];
  npcs: GameNpc[];
}>();

const emit = defineEmits<{
  'open-ref': [ref: ChronicleRef];
}>();

// Ссылки — инлайн-токенами в content (как в чате); токены рендерятся чипами, текст — как есть.
const segments = computed(() => parseChronicleContent(props.entry.content));

function refName(ref: ChronicleRef): string {
  if (ref.kind === 'character') {
    return (
      props.memberships.find((membership) => membership.characterId === ref.id)?.characterName ?? `Персонаж #${ref.id}`
    );
  }

  return props.npcs.find((npc) => npc.id === ref.id)?.name ?? `НПС #${ref.id}`;
}

/** Клик по чипу открывает просмотр листа: approved-персонаж или активный НПС. */
function resolvableRef(ref: ChronicleRef): boolean {
  if (ref.kind === 'character') {
    return props.memberships.some(
      (membership) => membership.characterId === ref.id && membership.membershipStatus === 'active',
    );
  }

  return props.npcs.some((npc) => npc.id === ref.id && npc.status === 'active');
}
</script>

<template>
  <template v-for="(segment, index) in segments" :key="index">
    <template v-if="segment.kind === 'text'">{{ segment.text }}</template>
    <v-chip
      v-else
      size="x-small"
      variant="tonal"
      class="mr-1"
      :prepend-icon="segment.ref.kind === 'character' ? 'mdi-account' : 'mdi-account-question'"
      :disabled="!resolvableRef(segment.ref)"
      @click="emit('open-ref', segment.ref)"
    >
      {{ refName(segment.ref) }}
    </v-chip>
  </template>
</template>
