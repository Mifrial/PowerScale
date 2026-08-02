import { registerRenderer } from './cells/registry'
import StringCell from './cells/StringCell.vue'
import NumberCell from './cells/NumberCell.vue'
import DateCell from './cells/DateCell.vue'
import BooleanCell from './cells/BooleanCell.vue'

export function initBaseRenderers() {
  registerRenderer('string', StringCell)
  registerRenderer('number', NumberCell)
  registerRenderer('date', DateCell)
  registerRenderer('boolean', BooleanCell)
}
