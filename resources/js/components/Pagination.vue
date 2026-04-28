<script setup>
import { computed, ref, watch, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import Button from "@/components/ui/button/Button.vue";

const router = useRouter();
const route = useRoute();

const props = defineProps({
    meta: {
        type: Object,
        required: true,
        validator: (value) => {
            return ["current_page", "total", "per_page"].every(
                (key) => key in value
            );
        },
    },
    siblingCount: {
        type: Number,
        default: 1,
    },
    showEdges: {
        type: Boolean,
        default: true,
    },
    routeQueryKey: {
        type: String,
        default: "page",
    },
});

const emit = defineEmits(["change"]);

const currentPage = computed(() => {
    return Number(route.query[props.routeQueryKey]) || 1;
});

const totalPages = computed(() => {
    return Math.ceil(props.meta.total / props.meta.per_page);
});

const items = computed(() => {
    const result = [];
    const total = totalPages.value;
    const page = currentPage.value;

    const range = 2 * props.siblingCount + 3;
    const rangeWithDots = range + 2;

    if (total <= rangeWithDots) {
        for (let i = 1; i <= total; i++) {
            result.push({ type: "page", value: i });
        }
    } else {
        let start = Math.max(2, page - props.siblingCount);
        let end = Math.min(total - 1, page + props.siblingCount);

        if (page - props.siblingCount <= 2) {
            end = range - 2;
        }
        if (page + props.siblingCount >= total - 1) {
            start = total - range + 3;
        }

        result.push({ type: "page", value: 1 });
        if (start > 2) {
            result.push({ type: "ellipsis" });
        }

        for (let i = start; i <= end; i++) {
            result.push({ type: "page", value: i });
        }

        if (end < total - 1) {
            result.push({ type: "ellipsis" });
        }
        result.push({ type: "page", value: total });
    }

    return result;
});

const updatePage = async (page) => {
    if (page === currentPage.value) return;

    // Update route query
    await router.push({
        query: {
            ...route.query,
            [props.routeQueryKey]: page.toString(),
        },
    });

    // Emit change event
    emit("change", {
        page,
        perPage: props.meta.per_page,
        total: props.meta.total,
    });
};

const goToFirst = () => updatePage(1);
const goToLast = () => updatePage(totalPages.value);
const goToPrev = () =>
    currentPage.value > 1 && updatePage(currentPage.value - 1);
const goToNext = () =>
    currentPage.value < totalPages.value && updatePage(currentPage.value + 1);
</script>

<template>
    <nav role="navigation" aria-label="pagination">
        <ul class="flex items-center gap-1">
            <li>
                <Button
                    :disabled="currentPage === 1"
                    @click="goToFirst"
                    variant="outline"
                    size="sm"
                >
                    ««
                </Button>
            </li>
            <li>
                <Button
                    :disabled="currentPage === 1"
                    @click="goToPrev"
                    variant="outline"
                    size="sm"
                >
                    «
                </Button>
            </li>
            <template v-for="(item, index) in items" :key="index">
                <li v-if="item.type === 'page'">
                    <Button
                        :class="
                            item.value === currentPage
                                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                : 'hover:bg-accent hover:text-accent-foreground'
                        "
                        @click="updatePage(item.value)"
                        variant="outline"
                        size="sm"
                    >
                        {{ item.value }}
                    </Button>
                </li>
                <li v-else>
                    <span class="flex h-9 w-9 items-center justify-center"
                        >...</span
                    >
                </li>
            </template>
            <li>
                <Button
                    :disabled="currentPage === totalPages"
                    @click="goToNext"
                    variant="outline"
                    size="sm"
                >
                    »
                </Button>
            </li>
            <li>
                <Button
                    :disabled="currentPage === totalPages"
                    @click="goToLast"
                    variant="outline"
                    size="sm"
                >
                    »»
                </Button>
            </li>
        </ul>
    </nav>
</template>
