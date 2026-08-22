<script setup lang="ts">
import { computed } from 'vue';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import CombatCharacteristicPopup from '@/modules/Roleplay/Game/Component/Detail/CombatCharacteristicPopup.vue';

const props = withDefaults(
  defineProps<{
    characteristic: CharacteristicOverview;
    rules: Rule[];
    senses?: CharacterSenseValue[];
    proficiencyLevels?: Map<string, number>;
    rollable?: boolean;
    /** Имя броска и title кубика; по умолчанию — подпись тайла. */
    rollName?: string;
    /** Запись в быстрых бросках (CD-8). */
    starred?: boolean;
    /** Звёздочка доступна (CD-6: ГМ или владелец своего approved-персонажа). */
    starEnabled?: boolean;
  }>(),
  {
    senses: () => [],
    proficiencyLevels: () => new Map<string, number>(),
    rollable: false,
    rollName: '',
    starred: false,
    starEnabled: false,
  },
);

const emit = defineEmits<{
  roll: [characteristic: CharacteristicOverview, name: string];
  /** Переключить звёздочку (добавить/убрать макрос быстрого броска). */
  'star-toggle': [characteristic: CharacteristicOverview];
}>();

const label = computed(() => props.characteristic.shortName ?? props.characteristic.name);

const rollName = computed(() => props.rollName || label.value);

function onPopupRoll(characteristic: CharacteristicOverview): void {
  emit('roll', characteristic, characteristic.shortName ?? characteristic.name);
}
</script>

<template>
  <v-menu location="right top" :close-on-content-click="false" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-characteristic" role="button" tabindex="0">
        <button
          v-if="rollable"
          type="button"
          class="combat-card-characteristic__roll"
          :title="`Бросок «${rollName}»`"
          @click.stop="emit('roll', characteristic, rollName)"
        >
          <v-icon size="small">mdi-dice-d6-outline</v-icon>
        </button>
        <span class="combat-card-characteristic__label text-truncate">{{ label }}</span>
        <span class="combat-card-characteristic__value">{{ characteristic.valueLabel }}</span>
        <button
          v-if="starEnabled"
          type="button"
          class="combat-card-characteristic__star"
          :class="{ 'combat-card-characteristic__star--active': starred }"
          :title="starred ? 'Убрать из быстрых бросков' : 'В быстрые броски'"
          @click.stop="emit('star-toggle', characteristic)"
        >
          <v-icon size="small">{{ starred ? 'mdi-star' : 'mdi-star-outline' }}</v-icon>
        </button>
      </div>
    </template>
    <CombatCharacteristicPopup
      :characteristic="characteristic"
      :rules="rules"
      :senses="senses"
      :proficiency-levels="proficiencyLevels"
      :rollable="rollable"
      :on-roll="onPopupRoll"
    />
  </v-menu>
</template>

<style scoped>
.combat-card-characteristic {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 3px 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  border-radius: 8px;
  cursor: pointer;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}
.combat-card-characteristic:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-card-characteristic__label {
  flex: 1;
  font-size: 13px;
}
.combat-card-characteristic__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
.combat-card-characteristic__roll {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.combat-card-characteristic__roll:hover {
  color: rgb(var(--v-theme-primary));
}
.combat-card-characteristic__star {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.combat-card-characteristic__star:hover,
.combat-card-characteristic__star--active {
  color: rgb(var(--v-theme-primary));
}
</style>
