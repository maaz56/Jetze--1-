<script setup>
import { Skeleton } from "@/components/ui/skeleton";
</script>

<template>
  <div class="overflow-hidden rounded border border-slate-200/90 bg-white shadow-sm flex flex-col lg:flex-row">
    <!-- Left: Image skeleton -->
    <div class="h-52 w-full shrink-0 bg-slate-100 lg:h-auto lg:w-[260px]">
      <Skeleton class="h-full w-full rounded-none" />
    </div>

    <!-- Middle: Details skeleton -->
    <div class="flex flex-1 flex-col justify-between p-4 sm:p-5 space-y-4">
      <div>
        <div class="flex items-start justify-between gap-4">
          <Skeleton class="h-6 w-3/5 rounded" />
          <Skeleton class="h-7 w-9 rounded-sm" />
        </div>
        <div class="mt-2 flex items-center gap-2">
          <Skeleton class="h-4 w-24 rounded" />
          <Skeleton class="h-4 w-16 rounded" />
        </div>
        <Skeleton class="mt-2.5 h-3.5 w-2/5 rounded" />

        <div class="mt-4 flex gap-2">
          <Skeleton class="h-5 w-20 rounded-sm" />
          <Skeleton class="h-5 w-24 rounded-sm" />
          <Skeleton class="h-5 w-20 rounded-sm" />
        </div>
      </div>

      <div class="flex items-center justify-between border-t border-border/50 pt-3">
        <Skeleton class="h-5 w-32 rounded-sm" />
        <Skeleton class="h-4 w-20 rounded" />
      </div>
    </div>

    <!-- Right: Price block skeleton -->
    <div class="flex w-full lg:w-[230px] shrink-0 flex-col justify-between p-4 sm:p-5 bg-muted/15 border-t lg:border-t-0 lg:border-l border-border">
      <div class="space-y-1.5 lg:text-right flex flex-col lg:items-end">
        <Skeleton class="h-3 w-20 rounded" />
        <Skeleton class="h-4 w-24 rounded" />
        <Skeleton class="h-7 w-32 rounded" />
        <Skeleton class="h-3 w-28 rounded" />
      </div>
      <div class="mt-4 space-y-2">
        <Skeleton class="h-11 w-full rounded" />
        <Skeleton class="h-3 w-24 mx-auto rounded" />
      </div>
    </div>
  </div>
</template>
