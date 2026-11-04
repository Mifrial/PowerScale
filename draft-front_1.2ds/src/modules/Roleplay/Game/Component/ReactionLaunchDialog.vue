<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ReactionOffer } from '@/modules/Roleplay/Game/Dto/ReactionOffer';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

const props = defineProps<{
  open: boolean;
  offers: ReactionOffer[];
  busy: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  submit: [payload: { offer: ReactionOffer; side: SupportSide; itemInventoryId: number }];
  refuse: [];
}>();

const actorIndex = ref(0);
const offerIndex = ref(0);
const side = ref<SupportSide>('attacker');
const weaponId = ref<number | null>(null);

const actorGroups = computed(() => {
  const groups = new Map<string, { actor: ReactionOffer['actor']; offers: ReactionOffer[] }>();
  for (const offer of props.offers) {
    const group = groups.get(offer.actor.key) ?? { actor: offer.actor, offers: [] };
    group.offers.push(offer);
    groups.set(offer.actor.key, group);
  }

  return [...groups.values()];
});

const currentGroup = computed(() => actorGroups.value[actorIndex.value] ?? null);
const current = computed<ReactionOffer | null>(() => currentGroup.value?.offers[offerIndex.value] ?? null);

const actorKindLabel = computed(() => (currentGroup.value?.actor.kind === 'npc' ? 'НПС' : 'персонаж'));

const sideItems = computed(
  () =>
    current.value?.sides.map((entry) => ({
      title: entry.value === 'attacker' ? `Помочь атакующему: ${entry.name}` : `Помочь защищающемуся: ${entry.name}`,
      value: entry.value,
    })) ?? [],
);

const weaponItems = computed(
  () => current.value?.weapons.map((weapon) => ({ title: weapon.name, value: weapon.itemInventoryId })) ?? [],
);

const triggerText = computed(() => {
  const reaction = current.value?.reaction;
  if (!reaction) return '';
  if (reaction.trigger === 'strike' && reaction.window === 'before_defense') {
    return 'открытый удар, до ответа цели';
  }

  return 'сейчас';
});

const sourceLabel = computed(() =>
  current.value?.source === 'circumstances' ? 'обстоятельства' : current.value?.source,
);

watch(
  () => props.open,
  (open) => {
    if (open) {
      actorIndex.value = 0;
      offerIndex.value = 0;
    }
  },
);

watch(actorGroups, (groups) => {
  if (actorIndex.value >= groups.length) actorIndex.value = 0;
});

watch(actorIndex, () => {
  offerIndex.value = 0;
});

watch(
  () => [props.open, current.value] as const,
  () => {
    if (!current.value) return;
    side.value = current.value.sides[0]?.value ?? 'attacker';
    weaponId.value = current.value.weapons[0]?.itemInventoryId ?? null;
  },
  { immediate: true },
);

function close(): void {
  emit('update:open', false);
}

function refuse(): void {
  emit('refuse');
}

function submit(): void {
  if (!current.value || weaponId.value === null) return;
  emit('submit', {
    offer: current.value,
    side: side.value,
    itemInventoryId: weaponId.value,
  });
}
</script>

<template>
  <v-dialog :model-value="open" max-width="520" scrollable @update:model-value="emit('update:open', $event)">
    <v-card>
      <v-card-title class="text-body-1 py-2 px-4">
        Реакция — {{ currentGroup?.actor.name ?? '…' }}
        <div class="text-caption text-medium-emphasis">{{ actorKindLabel }}</div>
      </v-card-title>
      <v-card-text class="pt-0">
        <div v-if="current?.rule.description" class="text-body-2 mb-3">{{ current.rule.description }}</div>
        <v-alert v-if="current" type="info" variant="tonal" density="compact" class="mb-3">
          <div><strong>Когда:</strong> {{ triggerText }}</div>
          <div>
            <strong>Эффект:</strong> +{{ current.advantage }} преимущества выбранной стороне; источник —
            {{ sourceLabel }}.
          </div>
          <div><strong>Стоимость:</strong> {{ current.cost }} ОД.</div>
        </v-alert>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <v-select
          v-if="actorGroups.length > 1"
          v-model="actorIndex"
          :items="
            actorGroups.map((group, index) => ({
              title: `${group.actor.name} · ${group.actor.kind === 'npc' ? 'НПС' : 'персонаж'}`,
              value: index,
            }))
          "
          label="Кто реагирует"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <v-select
          v-if="currentGroup && currentGroup.offers.length > 1"
          v-model="offerIndex"
          :items="currentGroup.offers.map((offer, index) => ({ title: offer.rule.name, value: index }))"
          label="Реакция"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <div v-else-if="current" class="text-caption text-medium-emphasis mb-3">Карточка: {{ current.rule.name }}</div>
        <v-select
          v-if="current"
          v-model="side"
          :items="sideItems"
          label="Кому помочь"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <v-select
          v-if="current"
          v-model="weaponId"
          :items="weaponItems"
          label="Оружие"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <div class="text-caption text-medium-emphasis">
          Стоимость списывается при объявлении реакции — до ответа цели, как у остальных реакций. Один участник
          реагирует на удар один раз. Мастерство выбранного оружия проверит сервер.
        </div>
      </v-card-text>
      <v-card-actions class="flex-wrap ga-2 px-4 pb-3">
        <v-btn variant="text" color="error" :disabled="busy" @click="refuse">Отказаться</v-btn>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="close">Свернуть</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!current || weaponId === null" @click="submit">
          Применить
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
