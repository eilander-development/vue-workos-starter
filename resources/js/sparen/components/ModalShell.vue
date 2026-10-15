<script setup lang="ts">
import { ref, useAttrs } from "vue";
import { useModalStack } from "../composables/useModalStack";

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
  onClose: () => void;
  dismissible?: boolean;
  closeOnBackdrop?: boolean;
}>(), {
  dismissible: true,
  closeOnBackdrop: true,
});

const attrs = useAttrs();
const overlay = ref<HTMLElement | null>(null);
const { isTop, zIndex, requestClose } = useModalStack(
  overlay,
  () => props.onClose(),
  () => props.dismissible,
);

function handleBackdrop(): void {
  if (props.closeOnBackdrop) {
    requestClose();
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-bind="attrs"
      ref="overlay"
      class="modal-shell fixed inset-0 flex items-center justify-center bg-black/75 backdrop-blur-sm sm:p-4"
      :style="{ zIndex }"
      :inert="!isTop"
      :aria-hidden="isTop ? undefined : true"
      tabindex="-1"
      @click.self="handleBackdrop"
    >
      <slot />
    </div>
  </Teleport>
</template>

<style scoped>
@media (max-width: 639px) {
  .modal-shell > :deep(*) {
    width: 95vw !important;
    max-width: 95vw !important;
    height: 95vh !important;
    height: 95dvh !important;
    max-height: 95vh !important;
    max-height: 95dvh !important;
    min-height: 0;
    margin: 0 !important;
    border-radius: 1rem !important;
    overflow-y: auto;
    overscroll-behavior: contain;
  }
}
</style>
