<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { LoaderCircle, PlaneTakeoff } from "lucide-vue-next";

const overlay = ref(null);
let previousFocus;
let previousOverflow;
let blockedElements = [];

function blockKeyboard(event) {
    event.preventDefault();
    event.stopImmediatePropagation();
}

onMounted(async () => {
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    blockedElements = Array.from(document.body.children)
        .filter((element) => element !== overlay.value)
        .map((element) => ({ element, inert: element.inert }));
    blockedElements.forEach(({ element }) => { element.inert = true; });
    window.addEventListener("keydown", blockKeyboard, true);
    await nextTick();
    overlay.value?.focus();
});

onBeforeUnmount(() => {
    window.removeEventListener("keydown", blockKeyboard, true);
    blockedElements.forEach(({ element, inert }) => { element.inert = inert; });
    document.body.style.overflow = previousOverflow ?? "";
    if (previousFocus?.isConnected) previousFocus.focus();
});
</script>

<template>
    <Teleport to="body">
        <div ref="overlay" tabindex="-1" role="status" aria-live="polite" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/45 px-4 backdrop-blur-sm focus:outline-none">
            <div class="w-full max-w-sm rounded border border-slate-200 bg-white px-6 py-8 text-center shadow-2xl sm:px-8">
                <div class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 text-primary">
                    <LoaderCircle class="absolute inset-0 h-16 w-16 animate-spin text-primary/35" aria-hidden="true" />
                    <PlaneTakeoff class="h-6 w-6" aria-hidden="true" />
                </div>
                <h2 class="mt-5 text-lg font-bold text-slate-900">Preparing your booking</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">We're confirming your selected fare. Please wait a moment.</p>
            </div>
        </div>
    </Teleport>
</template>
