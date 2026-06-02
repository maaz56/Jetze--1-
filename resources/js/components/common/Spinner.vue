<template>
  <div v-show="loading" class="flex items-center justify-center space-x-2">
    <div class="w-4 h-4 rounded-md bg-primary animate-beat"></div>
    <div class="w-4 h-4 rounded-md bg-primary animate-beat delay-150"></div>
    <div class="w-4 h-4 rounded-md bg-primary animate-beat"></div>
  </div>
</template>

<script>
export default {
  name: 'BeatLoader',
  data() {
    return {
      loading: true,
    };
  },
};
</script>

<style>
@keyframes beat {
  50% {
    transform: scale(0.75);
    opacity: 0.3;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}

.animate-beat {
  animation: beat 0.7s infinite linear;
}

.delay-150 {
  animation-delay: 0.35s;
}
</style>
