<script setup>
import { computed, onMounted, ref } from "vue";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import { useStore } from "vuex";
import {
    FETCH_AIRLINES,
    FETCH_COUNTRIES,
    SAVE_HOLIDAY,
} from "@/services/store/actions.type";
import Button from "@/components/ui/button/Button.vue";
import { Check, ChevronsUpDown } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import FileUploader from "@/components/common/FileUploader.vue";
import { useRouter } from "vue-router";

const store = useStore();
const router = useRouter();

const airlines = computed(() => {
    const airlines = store.getters["airline/airlines"] || [];
    return airlines.map((airline) => ({
        value: airline.id,
        label: airline.name,
    }));
});

function fetchAirlines() {
    store.dispatch("airline/" + FETCH_AIRLINES);
}

const open = ref(false);
const value = ref("");

onMounted(() => {
    fetchAirlines();
});
</script>

<template>
    <div class="my-8">
        <div>
            <Button
                @click="$router.back()"
                variant="ghost"
                class="p-0 text-muted-foreground"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="mr-2 lucide lucide-arrow-left"
                >
                    <path d="m12 19-7-7 7-7" />
                    <path d="M19 12H5" />
                </svg>
                Back
            </Button>
        </div>
        <span class="text-3xl font-medium">New Group Ticket</span>
    </div>
    <div class="bg-white rounded-lg p-12">
        <form @submit.prevent="saveHoliday" class="max-w-lg mx-auto">
            <div class="mb-3">
                <Label for="logo">Title</Label>
                <Popover v-model:open="open">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            role="combobox"
                            :aria-expanded="open"
                            class="w-full justify-between"
                        >
                            {{
                                value
                                    ? airlines.find(
                                          (airline) => airline.value === value
                                      )?.label
                                    : "Select airline..."
                            }}
                            <ChevronsUpDown
                                class="ml-2 h-4 w-4 shrink-0 opacity-50"
                            />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="p-0">
                        <Command>
                            <CommandInput
                                class="h-9"
                                placeholder="Search framework..."
                            />
                            <CommandEmpty>No framework found.</CommandEmpty>
                            <CommandList>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="airline in airlines"
                                        :key="airline.value"
                                        :value="airline.value"
                                        @select="
                                            (ev) => {
                                                if (
                                                    typeof ev.detail.value ===
                                                    'string'
                                                ) {
                                                    value = ev.detail.value;
                                                }
                                                open = false;
                                            }
                                        "
                                    >
                                        {{ airline.label }}
                                        <Check
                                            :class="
                                                cn(
                                                    'ml-auto h-4 w-4',
                                                    value === airline.value
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                )
                                            "
                                        />
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>
            <div class="mb-3">
                <Label for="starting-price">Starting price</Label>
                <Input
                    type="text"
                    v-model="startingPrice"
                    placeholder="Enter starting price"
                    id="starting-price"
                />
            </div>
            <Button type="submit">Save</Button>
        </form>
    </div>
</template>
