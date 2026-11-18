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
    inboxTabs.includes(route.query.inbox) ? route.query.inbox : "all"
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
    <section class="space-y-6">
        <!-- Top Navigation Segmented Tabs Bar -->
        <div class="rounded border border-slate-200 bg-white p-2 shadow-xs">
            <div class="flex overflow-x-auto scrollbar-hide gap-1.5" role="tablist" aria-label="Request inbox categories">
                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'all' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    :aria-selected="activeTab === 'all'"
                    role="tab"
                    @click="selectTab('all')"
                >
                    <Inbox class="h-4 w-4" />
                    <span>All Requests</span>
                </button>

                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'void' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    :aria-selected="activeTab === 'void'"
                    role="tab"
                    @click="selectTab('void')"
                >
                    <Ban class="h-4 w-4" />
                    <span>Void Requests</span>
                </button>

                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'refund' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    :aria-selected="activeTab === 'refund'"
                    role="tab"
                    @click="selectTab('refund')"
                >
                    <CircleX class="h-4 w-4" />
                    <span>Refund Requests</span>
                </button>

                <button
                    type="button"
                    class="flex shrink-0 items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                    :class="activeTab === 'modify' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    :aria-selected="activeTab === 'modify'"
                    role="tab"
                    @click="selectTab('modify')"
                >
                    <FilePenLine class="h-4 w-4" />
                    <span>Modify Requests</span>
                </button>
            </div>
        </div>

        <!-- Embedded Content View -->
        <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
            <AllRequests v-if="activeTab === 'all'" />
            <BookingRequests v-else-if="activeTab === 'void'" embedded request-type="void" />
            <BookingRequests v-else-if="activeTab === 'refund'" embedded request-type="refund" />
            <ModifyRequests v-else embedded :highlight-request-id="route.query.modify_request_id" />
        </div>
    </section>
</template>
