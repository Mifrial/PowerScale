<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ReactionOffer } from '@/modules/Roleplay/Game/Dto/ReactionOffer';
import type { ReactionSubmit } from '@/modules/Roleplay/Game/Service/StrikeReactionService';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';

const props = defineProps<{
  open: boolean;
  offers: ReactionOffer[];
  busy: boolean;
  error: string | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  submit: [payload: { offer: ReactionOffer; submit: ReactionSubmit }];
  refuse: [];
}>();

const actorIndex = ref(0);
const offerIndex = ref(0);
const side = ref<SupportSide>('attacker');
const weaponId = ref<number | null>(null);
const coverReaction = ref<string>('');
const blockItemId = ref<number | null>(null);

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

const sideItems = computed(() =>
  (current.value?.effect.kind === 'support' ? current.value.effect.sides : []).map((entry) => ({
    title: entry.value === 'attacker' ? `Помочь атакующему: ${entry.name}` : `Помочь защищающемуся: ${entry.name}`,
    value: entry.value,
  })),
);

const weaponItems = computed(() =>
  (current.value?.effect.kind === 'support' ? current.value.effect.weapons : []).map((weapon) => ({
    title: weapon.name,
    value: weapon.itemInventoryId,
  })),
);

const coverReactionItems = computed(() =>
  (current.value?.effect.kind === 'cover' ? current.value.effect.reactions : []).map((code) => ({
    title: coverReactionLabel(code),
    value: code,
  })),
);

const blockItems = computed(() =>
  (current.value?.effect.kind === 'cover' ? current.value.effect.blockItems : []).map((item) => ({
    title: item.name,
    value: item.itemInventoryId,
  })),
);

const defenseAdvantageText = computed(() => {
  if (current.value?.effect.kind !== 'cover') return '';
  const rows = current.value.effect.defenseAdvantages;
  if (rows.length === 0) return 'без помех';

  return rows.map((row) => `${row.source_label ?? row.source_code} ${row.delta > 0 ? '+' : ''}${row.delta}`).join(', ');
});

const triggerText = computed(() => {
  const reaction = current.value?.reaction;
  if (!reaction) return '';
  if (reaction.trigger === 'strike' && reaction.window === 'before_defense') {
    return 'открытый удар, до ответа цели';
  }

  return 'сейчас';
});

const sourceLabel = computed(() =>
  current.value?.effect.kind === 'support' && current.value.effect.source === 'circumstances'
    ? 'обстоятельства'
    : current.value?.effect.kind === 'support'
      ? current.value.effect.source
      : '',
);

const canSubmit = computed(() => {
  if (!current.value) return false;
  if (current.value.effect.kind === 'support') return weaponId.value !== null;
  if (!coverReaction.value) return false;

  return coverReaction.value !== 'block' || blockItemId.value !== null;
});

function coverReactionLabel(code: string): string {
  if (code === 'block') return 'Блокирование';
  if (code === 'dodge') return 'Уклонение';
  if (code === 'parry') return 'Парирование';

  return code;
}

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
    if (current.value.effect.kind === 'support') {
      side.value = current.value.effect.sides[0]?.value ?? 'attacker';
      weaponId.value = current.value.effect.weapons[0]?.itemInventoryId ?? null;

      return;
    }
    coverReaction.value = current.value.effect.reactions[0] ?? '';
    blockItemId.value = current.value.effect.blockItems[0]?.itemInventoryId ?? null;
  },
  { immediate: true },
);

watch(coverReaction, (code) => {
  if (current.value?.effect.kind !== 'cover' || code === 'block') return;
  blockItemId.value = null;
});

function close(): void {
  emit('update:open', false);
}

function refuse(): void {
  emit('refuse');
}

function submit(): void {
  if (!current.value || !canSubmit.value) return;
  if (current.value.effect.kind === 'support') {
    if (weaponId.value === null) return;
    emit('submit', {
      offer: current.value,
      submit: { kind: 'support', side: side.value, itemInventoryId: weaponId.value },
    });

    return;
  }

  if (blockItemId.value === null) return;
  emit('submit', {
    offer: current.value,
    submit: {
      kind: 'cover',
      reaction: coverReaction.value,
      itemInventoryId: blockItemId.value,
      itemRuleCode:
        current.value.effect.blockItems.find((item) => item.itemInventoryId === blockItemId.value)?.itemRuleCode ?? '',
    },
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
          <template v-if="current.effect.kind === 'support'">
            <div>
              <strong>Эффект:</strong> +{{ current.effect.advantage }} преимущества выбранной стороне; источник —
              {{ sourceLabel }}.
            </div>
          </template>
          <template v-else>
            <div>
              <strong>Эффект:</strong> вы становитесь альтернативным защитником; помеха защите —
              {{ defenseAdvantageText }}.
            </div>
          </template>
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
          :items="currentGroup.offers.map((offer, index) => ({ title: offer.label ?? offer.rule.name, value: index }))"
          label="Реакция"
          density="compact"
          hide-details="auto"
          class="mb-3"
        />
        <div v-else-if="current" class="text-caption text-medium-emphasis mb-3">Карточка: {{ current.rule.name }}</div>
        <template v-if="current?.effect.kind === 'support'">
          <v-select
            v-model="side"
            :items="sideItems"
            label="Кому помочь"
            density="compact"
            hide-details="auto"
            class="mb-3"
          />
          <v-select
            v-model="weaponId"
            :items="weaponItems"
            label="Оружие"
            density="compact"
            hide-details="auto"
            class="mb-3"
          />
        </template>
        <template v-else-if="current?.effect.kind === 'cover'">
          <v-select
            v-model="coverReaction"
            :items="coverReactionItems"
            label="Защита"
            density="compact"
            hide-details="auto"
            class="mb-3"
          />
          <v-select
            v-if="coverReaction === 'block'"
            v-model="blockItemId"
            :items="blockItems"
            label="Чем блокировать"
            density="compact"
            hide-details="auto"
            class="mb-3"
          />
        </template>
        <div class="text-caption text-medium-emphasis">
          Стоимость списывается при старте реакции, а не при объявлении. Один участник реагирует на удар один раз.
          Мастерство выбранного предмета проверит сервер.
        </div>
      </v-card-text>
      <v-card-actions class="flex-wrap ga-2 px-4 pb-3">
        <v-btn variant="text" color="error" :disabled="busy" @click="refuse">Отказаться</v-btn>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="close">Свернуть</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="!canSubmit" @click="submit">Применить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
