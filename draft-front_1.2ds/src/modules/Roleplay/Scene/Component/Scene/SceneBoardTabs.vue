<script setup lang="ts">
import { computed } from 'vue';

/** Минимум поля для вкладки: id, имя и порядок. */
interface FloorTabBoard {
  id: number;
  name: string;
  sortOrder: number;
}

/** Минимум вложения: связь, отметка и подпись. */
interface FloorTabEnclosure {
  parentBoardId: number;
  childBoardId: number;
  elevation: number;
  label: string | null;
}

const props = withDefaults(
  defineProps<{
    boards: FloorTabBoard[];
    enclosures?: FloorTabEnclosure[];
    modelValue: number | null;
    /** Кнопка «добавить поле»: в предпросмотре локации не нужна. */
    showAdd?: boolean;
  }>(),
  { enclosures: () => [], showAdd: true },
);

const emit = defineEmits<{
  'update:modelValue': [boardId: number];
  add: [];
}>();

/** Вкладка этажа: поле, подпись, строение и отметка. */
interface FloorTab {
  board: FloorTabBoard;
  /** Подпись вложения или имя поля. */
  label: string;
  /** Имя корневого поля стопки: к какому строению относится этаж. */
  building: string;
  /** Абсолютная отметка относительно корня. */
  elevation: number;
  /** Корень стопки: общая земля строения. */
  root: boolean;
}

/**
 * Вкладки этажей, сгруппированные по строениям.
 *
 * Стопка — корневое поле и цепочка вложений. Внутри стопки корень идёт первым,
 * этажи — по возрастанию отметки: подвал ниже земли, верхние этажи выше.
 */
const tabs = computed<FloorTab[]>(() => {
  const byId = new Map(props.boards.map((board) => [board.id, board]));
  const byChild = new Map(props.enclosures.map((item) => [item.childBoardId, item]));

  const rootOf = (boardId: number): number => {
    let current = boardId;
    const seen = new Set<number>();
    while (!seen.has(current)) {
      seen.add(current);
      const enclosure = byChild.get(current);
      if (enclosure === undefined) {
        return current;
      }

      current = enclosure.parentBoardId;
    }

    return current;
  };

  const elevationOf = (boardId: number): number => {
    let total = 0;
    let current = boardId;
    const seen = new Set<number>();
    while (!seen.has(current)) {
      seen.add(current);
      const enclosure = byChild.get(current);
      if (enclosure === undefined) {
        break;
      }

      total += enclosure.elevation;
      current = enclosure.parentBoardId;
    }

    return total;
  };

  const groups = new Map<number, FloorTab[]>();
  for (const board of props.boards) {
    const root = rootOf(board.id);
    const rootBoard = byId.get(root) ?? board;
    const enclosure = byChild.get(board.id);
    const tab: FloorTab = {
      board,
      label: enclosure?.label?.trim() || board.name,
      building: rootBoard.name,
      elevation: elevationOf(board.id),
      root: board.id === root,
    };
    const list = groups.get(root) ?? [];
    list.push(tab);
    groups.set(root, list);
  }

  return [...groups.values()]
    .sort((left, right) => left[0].board.sortOrder - right[0].board.sortOrder)
    .flatMap((list) =>
      list.sort((left, right) => (left.root === right.root ? left.elevation - right.elevation : left.root ? -1 : 1)),
    );
});
</script>

<template>
  <div class="scene-board-tabs">
    <v-tabs
      :model-value="props.modelValue"
      density="compact"
      show-arrows
      @update:model-value="emit('update:modelValue', Number($event))"
    >
      <v-tab v-for="tab in tabs" :key="tab.board.id" :value="tab.board.id" :title="`Строение: ${tab.building}`">
        <v-icon v-if="!tab.root" size="16" class="mr-1">
          {{ tab.elevation < 0 ? 'mdi-arrow-down' : 'mdi-arrow-up' }}
        </v-icon>
        {{ tab.label }}
      </v-tab>
    </v-tabs>
    <v-btn
      v-if="props.showAdd"
      icon="mdi-plus"
      variant="text"
      size="small"
      title="Добавить поле"
      @click="emit('add')"
    />
  </div>
</template>

<style scoped>
.scene-board-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.scene-board-tabs :deep(.v-tabs) {
  min-width: 0;
}
</style>
