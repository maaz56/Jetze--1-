<script setup>
defineProps({
  text: {
    type: String,
    default: "Finding best Flights For You..."
  }
})
</script>

<template>
  <teleport to="body">
    <div class="fixed inset-0 z-[100] bg-white flex flex-col items-center justify-center">
      
      <!-- Logo -->
      <img
        src="/public/assets/logo.png"
        alt="Logo"
        class="loader-logo"
      />

      <!-- Beating dots -->
      <div class="dots">
        <span></span>
        <span></span>
        <span></span>
      </div>

      <!-- Optional text -->
      <p v-if="text" class="mt-4 text-sm text-gray-500">
        {{ text }}
      </p>
    </div>
  </teleport>
</template>

<style scoped>
.loader-logo {
  width: 200px;        /* moderate size */
  height: auto;
  object-fit: contain;
  margin-bottom: 16px;
}

/* Dots */
.dots {
  display: flex;
  gap: 6px;
}

.dots span {
  width: 8px;
  height: 8px;
  background: #9ca3af; /* neutral gray */
  border-radius: 50%;
  animation: beat 1.4s infinite ease-in-out both;
}

.dots span:nth-child(1) {
  animation-delay: 0s;
}

.dots span:nth-child(2) {
  animation-delay: .15s;
}

.dots span:nth-child(3) {
  animation-delay: .3s;
}

/* Animation */
@keyframes beat {
  0%, 80%, 100% {
    transform: scale(0.6);
    opacity: .4;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
