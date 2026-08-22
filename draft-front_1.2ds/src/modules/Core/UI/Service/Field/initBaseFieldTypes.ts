import { fieldTypeRegistry } from '@/modules/Core/UI/Service/Instance/fieldTypeRegistry';
import { baseFieldTypeInterpreter } from '@/modules/Core/UI/Service/Instance/baseFieldTypeInterpreter';
import StringCell from '@/modules/Core/UI/Component/Grid/cells/StringCell.vue';
import NumberCell from '@/modules/Core/UI/Component/Grid/cells/NumberCell.vue';
import DateCell from '@/modules/Core/UI/Component/Grid/cells/DateCell.vue';
import BooleanCell from '@/modules/Core/UI/Component/Grid/cells/BooleanCell.vue';
import ActiveCell from '@/modules/Core/UI/Component/Grid/cells/ActiveCell.vue';
import StringFilter from '@/modules/Core/UI/Component/FilterBar/handlers/StringFilter.vue';
import NumberFilter from '@/modules/Core/UI/Component/FilterBar/handlers/NumberFilter.vue';
import BooleanFilter from '@/modules/Core/UI/Component/FilterBar/handlers/BooleanFilter.vue';
import SelectFilter from '@/modules/Core/UI/Component/FilterBar/handlers/SelectFilter.vue';
import DateTimeFilter from '@/modules/Core/UI/Component/FilterBar/handlers/DateTimeFilter.vue';

export function initBaseFieldTypes(): void {
  fieldTypeRegistry.register('string', {
    interpreter: baseFieldTypeInterpreter,
    cell: StringCell,
    filterWidget: StringFilter,
  });
  fieldTypeRegistry.register('number', {
    interpreter: baseFieldTypeInterpreter,
    cell: NumberCell,
    filterWidget: NumberFilter,
  });
  fieldTypeRegistry.register('boolean', {
    interpreter: baseFieldTypeInterpreter,
    cell: BooleanCell,
    filterWidget: BooleanFilter,
  });
  fieldTypeRegistry.register('select', {
    interpreter: baseFieldTypeInterpreter,
    cell: StringCell,
    filterWidget: SelectFilter,
  });
  fieldTypeRegistry.register('active', {
    interpreter: baseFieldTypeInterpreter,
    cell: ActiveCell,
    filterWidget: SelectFilter,
  });
  fieldTypeRegistry.register('date', {
    interpreter: baseFieldTypeInterpreter,
    cell: DateCell,
    filterWidget: DateTimeFilter,
  });
  fieldTypeRegistry.register('datetime', {
    interpreter: baseFieldTypeInterpreter,
    cell: DateCell,
    filterWidget: DateTimeFilter,
  });
}
