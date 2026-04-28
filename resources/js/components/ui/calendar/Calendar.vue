<script setup>
import { cn } from "@/lib/utils";
import { CalendarRoot, useForwardPropsEmits, VisuallyHidden } from "radix-vue";
import { computed } from "vue";
import {
    CalendarCell,
    CalendarCellTrigger,
    CalendarGrid,
    CalendarGridBody,
    CalendarGridHead,
    CalendarGridRow,
    CalendarHeadCell,
    CalendarHeader,
    CalendarHeading,
    CalendarNextButton,
    CalendarPrevButton,
} from ".";

const props = defineProps({
    modelValue: { type: null, required: false },
    multiple: { type: Boolean, required: false },
    defaultValue: { type: null, required: false },
    defaultPlaceholder: { type: null, required: false },
    placeholder: { type: null, required: false },
    pagedNavigation: { type: Boolean, required: false },
    preventDeselect: { type: Boolean, required: false },
    weekStartsOn: { type: Number, required: false },
    weekdayFormat: { type: String, required: false },
    calendarLabel: { type: String, required: false },
    fixedWeeks: { type: Boolean, required: false },
    maxValue: { type: null, required: false },
    minValue: { type: null, required: false },
    locale: { type: String, required: false },
    numberOfMonths: { type: Number, required: false },
    disabled: { type: Boolean, required: false },
    readonly: { type: Boolean, required: false },
    initialFocus: { type: Boolean, required: false },
    isDateDisabled: { type: Function, required: false },
    isDateUnavailable: { type: Function, required: false },
    dir: { type: String, required: false },
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: { type: null, required: false },
    prices: { type: Object, required: false, default: () => ({}) },
});

const emits = defineEmits(["update:modelValue", "update:placeholder"]);

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;

    return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);

function getPrice(date) {
    const key = date.toString(); // radix-vue uses CalendarDate
    return props.prices[key] ?? null;
}
</script>

<template>
    <CalendarRoot
        v-slot="{ grid, weekDays }"
        :class="cn(' p-0 border-none', props.class)"
        v-bind="forwarded"
    >
        <CalendarHeader>
            <VisuallyHidden>
                <CalendarPrevButton />
                <CalendarHeading />
                <CalendarNextButton />
            </VisuallyHidden>
        </CalendarHeader>

        <div class="flex flex-col gap-y-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
            <CalendarGrid v-for="month in grid" :key="month.value.toString()">
                <CalendarGridHead>
                    <CalendarGridRow>
                        <CalendarHeadCell v-for="day in weekDays" :key="day">
                            {{ day }}
                        </CalendarHeadCell>
                    </CalendarGridRow>
                </CalendarGridHead>
                <CalendarGridBody>
                    <CalendarGridRow
                        v-for="(weekDates, index) in month.rows"
                        :key="`weekDate-${index}`"
                        class="mt-2 w-full"
                    >
                        <CalendarCell
                            v-for="weekDate in weekDates"
                            :key="weekDate.toString()"
                            :date="weekDate"
                        >
                            <CalendarCellTrigger
                                :day="weekDate"
                                :month="month.value"
                                class="flex flex-col items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-md"
                            >
                                <!-- DAY NUMBER -->
                                <span class="text-sm font-medium leading-none">
                                    {{ weekDate.day }}
                                </span>

                                <!-- PRICE -->
                                <span
                                    v-if="getPrice(weekDate)"
                                    class="text-[10px] leading-none mt-1 opacity-70"
                                >
                                    {{ getPrice(weekDate) }}
                                </span>
                            </CalendarCellTrigger>
                        </CalendarCell>
                    </CalendarGridRow>
                </CalendarGridBody>
            </CalendarGrid>
        </div>
    </CalendarRoot>
</template>
