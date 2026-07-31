export interface NotificationAction {
  label: string
  key: string
  color: 'primary' | 'outlined' | 'error'
}

export interface Notification {
  id: number
  title: string
  preview: string
  createdAt: string
  icon: string
  read: boolean
  actions: NotificationAction[]
}

export interface NotificationTemplate {
  id: number
  key: string
  titleTemplate: string
  bodyTemplate: string
  buttonsJson?: NotificationButton[]
}

export interface NotificationButton {
  label: string
  actionType: 'event' | 'url' | 'action'
  action: string
  payload?: Record<string, any>
}
