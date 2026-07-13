<script setup>
import { useVModel } from "@vueuse/core";
import { cn } from "@/lib/utils";
import { EyeIcon, EyeOffIcon } from "lucide-vue-next";
import { computed, ref } from "vue";
import Button from "../button/Button.vue";

const props = defineProps({
    defaultValue: { type: [String, Number], required: false },
    modelValue: { type: [String, Number], required: false },
    class: { type: null, required: false },
    type: { type: String, default: "text" },
    leftIcon: { type: String, required: false },
});

const emits = defineEmits(["update:modelValue"]);

const modelValue = useVModel(props, "modelValue", emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

const showPassword = ref(false);

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};
</script>

<template>
    <div class="relative flex justify-start items-center w-full">
        <div v-if="props.leftIcon" class="absolute left-3 flex justify-center items-center pointer-events-none">
            <img :src="props.leftIcon" class="w-5 h-5 object-contain opacity-70" alt="icon" />
        </div>
        <input v-model="modelValue" v-bind="$attrs" :type="props.type === 'password' && showPassword ? 'text' : props.type
            " :class="cn(
                'flex h-10 text-gray-800 w-full rounded-md transition-all duration-75 border border-muted hover:border-2 hover:border-primary/10  px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus:ring-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                props.leftIcon ? 'pl-9' : '',
                props.class,
                props.type === 'password' ? 'rounded-r-none' : '',
            )
                " />
        <Button v-if="props.type === 'password'" type="button" variant="outline"
            class="inset-y-0 right-0 flex items-center px-3 bg-white border rounded-r-lg h-full rounded-l-none"
            @click="togglePasswordVisibility">
            <EyeIcon v-if="!showPassword" class="w-5 h-5 text-muted-foreground" />
            <EyeOffIcon v-else class="w-5 h-5 text-muted-foreground" />
        </Button>
    </div>
</template>
