<script setup>
import { Check } from "lucide-vue-next";

defineProps({
  currentStep: {
    type: Number,
    required: true,
    validator: (value) => [1, 2, 3, 4].includes(value),
  },
});

const steps = [
  { id: 1, label: "Select room" },
  { id: 2, label: "Guest details" },
  { id: 3, label: "Payment" },
  { id: 4, label: "Confirmation" },
];
</script>

<template>
  <nav aria-label="Booking progress" class="w-full border-b border-slate-200/90 bg-white shadow-2xs">
    <div class="mx-auto max-w-booking px-4 py-2.5 sm:px-6">
      <ol class="flex items-center justify-between gap-1 sm:justify-center sm:gap-2.5 overflow-x-auto py-0.5">
        <template v-for="(step, index) in steps" :key="step.id">
          <!-- Step Pill Item -->
          <li class="flex shrink-0 items-center">
            <div
              class="flex items-center gap-2 rounded px-3 py-1.5 transition-all text-xs sm:text-sm"
              :class="{
                'bg-primary/10 border border-primary/30 text-primary font-bold shadow-2xs': currentStep === step.id,
                'bg-emerald-50 border border-emerald-200 text-emerald-800 font-semibold': currentStep > step.id,
                'bg-slate-50 border border-slate-200/80 text-slate-500 font-medium': currentStep < step.id,
              }"
            >
              <!-- Step icon / number badge -->
              <span
                v-if="currentStep > step.id"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-600 text-white shadow-2xs"
              >
                <Check class="h-3.5 w-3.5 stroke-[3]" />
              </span>
              <span
                v-else
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[11px] font-black"
                :class="currentStep === step.id
                  ? 'bg-primary text-white shadow-xs'
                  : 'bg-slate-200 text-slate-600'"
              >
                {{ step.id }}
              </span>

              <!-- Step Label -->
              <span class="whitespace-nowrap">{{ step.label }}</span>
            </div>
          </li>

          <!-- Connecting line between steps -->
          <li
            v-if="index < steps.length - 1"
            aria-hidden="true"
            class="flex items-center px-1 sm:px-1.5 shrink-0"
          >
            <div
              class="h-[2px] w-5 sm:w-10 rounded-full transition-colors duration-300"
              :class="currentStep > step.id ? 'bg-emerald-500' : 'bg-slate-200'"
            />
          </li>
        </template>
      </ol>
    </div>
  </nav>
</template>
