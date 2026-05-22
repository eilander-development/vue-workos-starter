import { ref } from 'vue'

export type NotificationType = 'success' | 'error' | 'info'

export interface Notification {
  type: NotificationType
  message: string
}

export function useNotification() {
  const notification = ref<Notification | null>(null)
  let timeoutId: ReturnType<typeof setTimeout> | null = null

  const clearNotification = () => {
    notification.value = null
    if (timeoutId) {
      clearTimeout(timeoutId)
      timeoutId = null
    }
  }

  const showNotification = (
    message: string,
    type: NotificationType = 'success',
    duration = 5000,
  ) => {
    notification.value = { type, message }

    if (timeoutId) {
      clearTimeout(timeoutId)
    }

    timeoutId = setTimeout(() => {
      notification.value = null
      timeoutId = null
    }, duration)
  }

  return {
    notification,
    showNotification,
    clearNotification,
  }
}
