<template>
  <div class="flex flex-col h-[400px] bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm animate-pulse">
    <!-- Image placeholder -->
    <div class="w-full aspect-[4/3] bg-slate-200"></div>

    <!-- Content placeholder -->
    <div class="p-5 flex flex-col justify-between flex-1 space-y-4">
      <div class="space-y-3">
        <!-- Route title -->
        <div class="h-5 bg-slate-200 rounded w-3/4"></div>

        <!-- IATA & Airport -->
        <div class="h-3.5 bg-slate-200 rounded w-1/2"></div>

        <!-- Airline -->
        <div class="flex items-center gap-2 pt-1">
          <div class="w-5 h-5 rounded-full bg-slate-200 shrink-0"></div>
          <div class="h-3.5 bg-slate-200 rounded w-1/3"></div>
        </div>

        <!-- Dates -->
        <div class="h-3.5 bg-slate-200 rounded w-2/3"></div>
      </div>

      <!-- Price & CTA -->
      <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
        <div class="space-y-1">
          <div class="h-3 bg-slate-200 rounded w-10"></div>
          <div class="h-6 bg-slate-200 rounded w-24"></div>
        </div>
        <div class="h-9 w-20 bg-slate-200 rounded-xl"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
