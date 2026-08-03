import { registerFilterHandler } from '@/modules/Core/UI/Component/FilterBar/registry'
import StringFilter from '@/modules/Core/UI/Component/FilterBar/handlers/StringFilter.vue'
import BooleanFilter from '@/modules/Core/UI/Component/FilterBar/handlers/BooleanFilter.vue'
import SelectFilter from '@/modules/Core/UI/Component/FilterBar/handlers/SelectFilter.vue'
import DateTimeFilter from '@/modules/Core/UI/Component/FilterBar/handlers/DateTimeFilter.vue'
import NumberFilter from '@/modules/Core/UI/Component/FilterBar/handlers/NumberFilter.vue'
import ActiveFilter from '@/modules/Core/UI/Component/FilterBar/handlers/ActiveFilter.vue'

export function initBaseFilterHandlers() {
  registerFilterHandler('string', { component: StringFilter })
  registerFilterHandler('boolean', { component: BooleanFilter })
  registerFilterHandler('select', { component: SelectFilter })
  registerFilterHandler('datetime', { component: DateTimeFilter })
  registerFilterHandler('date', { component: DateTimeFilter })
  registerFilterHandler('number', { component: NumberFilter })
  registerFilterHandler('active', { component: ActiveFilter })
}
