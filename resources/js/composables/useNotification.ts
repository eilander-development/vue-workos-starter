import { ref } from 'vue'

export type NotificationType = 'success' | 'error' | 'info'

export interface Notification {
  type: NotificationType
  message: string
}

const notification = ref<Notification | null>(null)
let timeoutId: ReturnType<typeof setTimeout> | null = null

export function useNotification() {

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

  const showSuccess = (message: string, duration = 5000) =>
    showNotification(message, 'success', duration)

  const showError = (message: string, duration = 5000) =>
    showNotification(message, 'error', duration)

  const showInfo = (message: string, duration = 5000) =>
    showNotification(message, 'info', duration)

  return { notification, showNotification, showSuccess, showError, showInfo, clearNotification }
}
