<script setup>
import { computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Ban, CircleX, FilePenLine, Inbox } from "lucide-vue-next";
import AllRequests from "@/pages/admin/AllRequests.vue";
import BookingRequests from "@/pages/admin/BookingRequests.vue";
import ModifyRequests from "@/pages/admin/ModifyRequests.vue";

const route = useRoute();
const router = useRouter();
const inboxTabs = ["all", "void", "refund", "modify"];

const activeTab = computed(() =>
    inboxTabs.includes(route.query.inbox) ? route.query.inbox : "all",
);

function selectTab(tab) {
    const query = { ...route.query };

    if (tab === "all") {
        delete query.inbox;
    } else {
        query.inbox = tab;
    }

    router.replace({ name: "BookingRequests", query });
}
</script>

<template>
    <section class="mx-auto max-w-7xl">
        <div class="border-b border-gray-200 bg-white">
            <div class="flex overflow-x-auto scrollbar-hide" role="tablist" aria-label="Request inbox section">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-xs font-medium transition-colors sm:px-6 sm:py-4 sm:text-sm"
                    :class="activeTab === 'all' ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    :aria-selected="activeTab === 'all'"
                    role="tab"
                    @click="selectTab('all')"
                >
                    <Inbox class="h-4 w-4" aria-hidden="true" />
                    All Requests
                </button>
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-xs font-medium transition-colors sm:px-6 sm:py-4 sm:text-sm"
                    :class="activeTab === 'void' ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    :aria-selected="activeTab === 'void'"
                    role="tab"
                    @click="selectTab('void')"
                >
                    <Ban class="h-4 w-4" aria-hidden="true" />
                    Void Requests
                </button>
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-xs font-medium transition-colors sm:px-6 sm:py-4 sm:text-sm"
                    :class="activeTab === 'refund' ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    :aria-selected="activeTab === 'refund'"
                    role="tab"
                    @click="selectTab('refund')"
                >
                    <CircleX class="h-4 w-4" aria-hidden="true" />
                    Refund Requests
                </button>
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-xs font-medium transition-colors sm:px-6 sm:py-4 sm:text-sm"
                    :class="activeTab === 'modify' ? 'border-primary text-primary' : 'border-transparent text-gray-600 hover:text-gray-900'"
                    :aria-selected="activeTab === 'modify'"
                    role="tab"
                    @click="selectTab('modify')"
                >
                    <FilePenLine class="h-4 w-4" aria-hidden="true" />
                    Modify Requests
                </button>
            </div>
        </div>

        <div class="rounded-b-lg bg-white shadow-sm">
            <AllRequests v-if="activeTab === 'all'" />
            <BookingRequests v-else-if="activeTab === 'void'" embedded request-type="void" />
            <BookingRequests v-else-if="activeTab === 'refund'" embedded request-type="refund" />
            <ModifyRequests v-else embedded :highlight-request-id="route.query.modify_request_id" />
        </div>
    </section>
</template>
