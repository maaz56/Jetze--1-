<template>
  <div
    :class="[
      fullscreen ? 'fixed inset-0 z-50' : 'min-h-[50vh]',
      'loader-shell flex items-center justify-center'
    ]"
    role="status"
    aria-live="polite"
  >
    <div class="loader-container">
      <!-- Main Spinner -->
      <div class="loader-spinner">
        <div class="spinner-ring"></div>
        <div class="spinner-ring spinner-ring-secondary"></div>
        <div class="spinner-center">
          <div class="spinner-dot"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  fullscreen: {
    type: Boolean,
    default: true,
  },
});
</script>

<style scoped>
.loader-shell {
  background: rgba(248, 250, 252, 0.85);
  backdrop-filter: blur(12px);
}

.loader-container {
  display: flex;
  align-items: center;
  justify-content: center;
}

.loader-spinner {
  position: relative;
  width: 72px;
  height: 72px;
}

/* Primary Ring */
.spinner-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 3px solid transparent;
  border-top-color: var(--primary, #0e7490);
  animation: spin 1s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}

/* Secondary Ring */
.spinner-ring-secondary {
  inset: 6px;
  border-top-color: var(--primary-light, #22d3ee);
  animation-duration: 1.4s;
  animation-direction: reverse;
  opacity: 0.6;
}

/* Center Dot */
.spinner-center {
  position: absolute;
  inset: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--primary, #0e7490);
  animation: pulse 1.4s ease-in-out infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(0.8);
    opacity: 0.5;
  }
  50% {
    transform: scale(1.2);
    opacity: 1;
  }
}

/* Optional: Add a subtle shadow glow */
.loader-spinner::before {
  content: '';
  position: absolute;
  inset: -8px;
  border-radius: 50%;
  background: radial-gradient(circle, var(--primary, #0e7490) 0%, transparent 70%);
  opacity: 0.08;
  animation: pulse 2s ease-in-out infinite;
}
</style>