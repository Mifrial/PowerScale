<script setup lang="ts">
import { computed } from 'vue';
import LightChip from '@/modules/Core/UI/Component/light/LightChip.vue';
import LightButton from '@/modules/Core/UI/Component/light/LightButton.vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { itemStorageService } from '@/modules/Roleplay/Character/Service/Instance/itemStorageService';
import type { RecordedSpellEntry } from '@/modules/Roleplay/Character/Service/ItemStorageService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemStorage } from '@/modules/Roleplay/Rule/Dto/Item/ItemStorage';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

const props = withDefaults(
  defineProps<{
    payload?: Record<string, unknown>;
    storage: ItemStorage;
    rules: Rule[];
    /** Предельная мощь экземпляра: полный каст за ход тратит её целиком. */
    maxPower?: DimensionalNumberValue;
    /** Словарь признаков: ограничение записей и подписи. */
    keywords?: Keyword[];
    /** Можно менять состав записей: редактор персонажа, не карточка. */
    canEdit?: boolean;
  }>(),
  { keywords: () => [], canEdit: false },
);

const emit = defineEmits<{
  'update:recorded': [recorded: RecordedSpellEntry[]];
}>();

const keywordCodeById = computed(() => new Map(props.keywords.map((keyword) => [keyword.id, keyword.code])));

/** Признаки, которые принимает хранилище. */
const allowedKeywords = computed(() =>
  (props.storage.recorded_keywords ?? []).map((code) => ({
    code,
    name: props.keywords.find((keyword) => keyword.code === code)?.name ?? code,
  })),
);

const volume = computed(() => {
  const raw = props.payload?.[props.storage.volume_parameter];

  return typeof raw === 'number' ? raw : 0;
});

const recorded = computed(() => itemStorageService.recordedOf(props.payload, props.storage));

const occupied = computed(() => itemStorageService.occupied(recorded.value, props.rules));

const manaLimit = computed(() => itemStorageService.manaLimit(volume.value, occupied.value));

/** Мощь целым числом или null, если экземпляр её не задал. */
const powerValue = computed(() =>
  props.maxPower ? DimensionalNumber.from(props.maxPower).toNumber() : null,
);

/** Свободного Объёма не хватит на каст полной мощью за один ход. */
const manaLow = computed(
  () => recorded.value.length > 0 && powerValue.value !== null && manaLimit.value < powerValue.value,
);

/** Подсказка, почему запись не подходит; пусто — подходит. */
function fitHint(occupiedAfter: number): string {
  if (occupiedAfter > volume.value) return ' · не влезает';
  if (powerValue.value !== null && volume.value - occupiedAfter < powerValue.value) return ' · мало маны';

  return '';
}

const rows = computed(() =>
  recorded.value.map((entry) => {
    const upgrades = (entry.upgrades ?? []).map((code) => ({
      code,
      name: props.rules.find((rule) => rule.code === code)?.name ?? code,
      volume: itemStorageService.cardVolume(props.rules, code),
    }));

    return {
      ...entry,
      upgrades,
      name: props.rules.find((rule) => rule.code === entry.ruleCode)?.name ?? entry.ruleCode,
      volume: itemStorageService.cardVolume(props.rules, entry.ruleCode),
      /** Улучшения заклинания, которых ещё нет в записи. */
      upgradeCandidates: itemStorageService
        .applicableUpgrades(entry.ruleCode, props.rules)
        .filter((rule) => !upgrades.some((upgrade) => upgrade.code === rule.code))
        .map((rule) => ({
          ruleCode: rule.code,
          name: rule.name,
          volume: itemStorageService.cardVolume(props.rules, rule.code),
          hint: fitHint(occupied.value + itemStorageService.cardVolume(props.rules, rule.code)),
        })),
    };
  }),
);

/** Свободные карточки-заклинания: не записаны и помещаются целиком. */
const candidates = computed(() => {
  const codes = new Set(recorded.value.map((entry) => entry.ruleCode));

  return itemStorageService
    .recordableSpells(props.rules, props.storage, keywordCodeById.value)
    .filter((rule) => !codes.has(rule.code))
    .map((rule) => ({
      ruleCode: rule.code,
      name: rule.name,
      volume: itemStorageService.cardVolume(props.rules, rule.code),
      hint: fitHint(occupied.value + itemStorageService.cardVolume(props.rules, rule.code)),
    }));
});

function addSpell(code: string | null): void {
  if (!code) return;
  emit('update:recorded', itemStorageService.withRecorded(recorded.value, code, volume.value, props.rules));
}

function removeSpell(code: string): void {
  emit('update:recorded', itemStorageService.withoutRecorded(recorded.value, code));
}

function addUpgrade(spellCode: string, upgradeCode: string | null): void {
  if (!upgradeCode) return;
  emit(
    'update:recorded',
    itemStorageService.withUpgrade(recorded.value, spellCode, upgradeCode, volume.value, props.rules),
  );
}

function removeUpgrade(spellCode: string, upgradeCode: string): void {
  emit('update:recorded', itemStorageService.withoutUpgrade(recorded.value, spellCode, upgradeCode));
}
</script>

<template>
  <div class="item-storage d-flex flex-column ga-2">
    <div class="d-flex align-center ga-2 flex-wrap">
      <span class="text-caption text-medium-emphasis">Записи кристалла</span>
      <LightChip variant="outlined">объём {{ occupied }} / {{ volume }}</LightChip>
      <LightChip variant="outlined">мана до {{ manaLimit }}</LightChip>
      <LightChip v-for="keyword in allowedKeywords" :key="keyword.code" variant="outlined" color="secondary">
        только {{ keyword.name }}
      </LightChip>
    </div>
    <p v-if="canEdit && recorded.length === 0" class="text-caption text-warning">
      Кристалл без записей сохранить нельзя — запишите хотя бы одно заклинание.
    </p>
    <p v-if="canEdit && manaLow" class="text-caption text-warning">
      Свободной маны {{ manaLimit }} меньше мощи {{ powerValue }} — на полный каст за ход её не хватит.
    </p>
    <div v-if="rows.length" class="d-flex flex-column ga-2">
      <div v-for="row in rows" :key="row.ruleCode" class="d-flex flex-column ga-1">
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">{{ row.name }}</span>
          <span class="text-caption text-medium-emphasis">{{ row.volume }}</span>
          <span class="flex-grow-1" />
          <LightButton v-if="canEdit" title="Убрать запись" @click.stop="removeSpell(row.ruleCode)">
            <i class="mdi mdi-close" aria-hidden="true" />
          </LightButton>
        </div>
        <div v-if="row.upgrades.length" class="d-flex align-center ga-2 flex-wrap ps-3">
          <template v-for="upgrade in row.upgrades" :key="upgrade.code">
            <LightChip variant="outlined">{{ upgrade.name }} · {{ upgrade.volume }}</LightChip>
            <LightButton
              v-if="canEdit"
              title="Убрать улучшение"
              @click.stop="removeUpgrade(row.ruleCode, upgrade.code)"
            >
              <i class="mdi mdi-close" aria-hidden="true" />
            </LightButton>
          </template>
        </div>
        <v-autocomplete
          v-if="canEdit && row.upgradeCandidates.length"
          :items="row.upgradeCandidates"
          item-title="name"
          item-value="ruleCode"
          label="Записать улучшение"
          density="compact"
          hide-details
          clearable
          class="ps-3"
          :model-value="null"
          @update:model-value="(code) => addUpgrade(row.ruleCode, code)"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps" :disabled="item.raw.hint !== ''">
              <template #append>
                <span class="text-caption text-medium-emphasis">
                  {{ item.raw.volume }}{{ item.raw.hint }}
                </span>
              </template>
            </v-list-item>
          </template>
        </v-autocomplete>
      </div>
    </div>
    <p v-else class="text-caption text-medium-emphasis">Записей нет.</p>
    <v-autocomplete
      v-if="canEdit"
      :items="candidates"
      item-title="name"
      item-value="ruleCode"
      label="Записать заклинание"
      density="compact"
      hide-details
      clearable
      :model-value="null"
      @update:model-value="addSpell"
    >
      <template #item="{ props: itemProps, item }">
        <v-list-item v-bind="itemProps" :disabled="item.raw.hint !== ''">
          <template #append>
            <span class="text-caption text-medium-emphasis">
              {{ item.raw.volume }}{{ item.raw.hint }}
            </span>
          </template>
        </v-list-item>
      </template>
    </v-autocomplete>
  </div>
</template>
