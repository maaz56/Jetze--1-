<script setup>
import { computed } from "vue";
import { formatFareRuleSections } from "./fareRuleFormatting";

const props = defineProps({ text: { type: String, required: true } });
const sections = computed(() => formatFareRuleSections(props.text));
</script>

<template>
    <div class="space-y-2 py-2">
        <section
            v-for="(section, index) in sections"
            :key="index"
            class="rounded-lg border border-slate-200 bg-slate-50/60 px-3 py-3 sm:px-4"
        >
            <p v-if="section.heading" class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-800">
                {{ section.heading }}
            </p>
            <div class="space-y-2">
                <p v-for="(paragraph, paragraphIndex) in section.paragraphs" :key="paragraphIndex" class="whitespace-pre-line break-words text-sm leading-6 text-slate-700">
                    {{ paragraph }}
                </p>
            </div>
        </section>
    </div>
</template>
