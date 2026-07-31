import { registerFilterHandler } from './registry'
import StringFilter from './handlers/StringFilter.vue'
import BooleanFilter from './handlers/BooleanFilter.vue'
import SelectFilter from './handlers/SelectFilter.vue'
import DateTimeFilter from './handlers/DateTimeFilter.vue'
import NumberFilter from './handlers/NumberFilter.vue'
import ActiveFilter from './handlers/ActiveFilter.vue'

export function initBaseFilterHandlers() {
  registerFilterHandler('string', { component: StringFilter })
  registerFilterHandler('boolean', { component: BooleanFilter })
  registerFilterHandler('select', { component: SelectFilter })
  registerFilterHandler('datetime', { component: DateTimeFilter })
  registerFilterHandler('date', { component: DateTimeFilter })
  registerFilterHandler('number', { component: NumberFilter })
  registerFilterHandler('active', { component: ActiveFilter })
}
