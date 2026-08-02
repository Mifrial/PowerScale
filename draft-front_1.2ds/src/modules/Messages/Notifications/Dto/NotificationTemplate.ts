import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton'

export interface NotificationTemplate {
  id: number
  key: string
  titleTemplate: string
  bodyTemplate: string
  buttonsJson?: NotificationButton[]
  active: boolean
}
