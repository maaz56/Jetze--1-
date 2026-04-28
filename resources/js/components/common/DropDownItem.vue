<script setup>
import { defineEmits, onMounted, ref } from "vue";

const checked = ref(false);

const props = defineProps({
    title: {
        type: Object,
        required: true,
    },
    itemId: {
        type: Object,
        required: true,
    },
    groupName: {
        type: Object,
        required: true,
    },
    isChecked: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["dropDownItemClicked"]);

onMounted(() => {
    checked.value = props.isChecked;
});
</script>

<template>
    <div @click="$emit('dropDownItemClicked', itemId)">
        <li>
            <div class="flex items-center rounded hover:bg-gray-100 px-3">
                <input
                    :id="'filter-radio-example-' + itemId"
                    type="radio"
                    value=""
                    :name="groupName"
                    :checked="checked"
                    class="w-4 h-4 text-wn-primary bg-gray-100 border-gray-300 focus:ring-wn-primary dark:focus:ring-wn-primary dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2"
                />
                <label
                    :for="'filter-radio-example-' + itemId"
                    class="w-full ml-2 text-sm font-medium text-gray-900 rounded py-2 px-2 dark:text-gray-300 hover:bg-gray-100 dark:hover-bg-gray-600"
                >
                    {{ title }}
                </label>
            </div>
        </li>
    </div>
</template>
