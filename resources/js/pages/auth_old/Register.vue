<script setup>
import InputMessage from "@/components/common/InputMessage.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { Check, ChevronsUpDown, LoaderCircle } from "lucide-vue-next";
import {
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from "@/components/ui/form";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
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
import {
    FETCH_CITIES,
    FETCH_COUNTRIES,
    REGISTER,
} from "@/services/store/actions.type";
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

const store = useStore();
const router = useRouter();
const apiError = computed(() => store.getters["auth/apiError"]);
const isLoading = computed(() => store.getters["auth/isLoading"]);

const registerForm = useForm({
    validationSchema: toTypedSchema(
        z.object({
            email: z.string().email().min(2).max(50),
            password: z.string().min(4),
            first_name: z.string(),
            last_name: z.string()
        })
    ),
});

const handleRegister = registerForm.handleSubmit((values) => {
    store
        .dispatch("auth/" + REGISTER, {
            email: values.email,
            password: values.password,
            first_name: values.first_name,
            last_name: values.last_name,
            role: 'user',
        })
        .then(() => {
            if (apiError.value == null) {
                router.push({ name: "Login" });
            }
        });
});
</script>

<template>
    <section class="grid grid-cols-2 h-screen overflow-hidden">
        <div class="h-screen p-2">
            <img
                class="h-full w-full object-cover bg-center rounded-xl border"
                src="https://images.pexels.com/photos/175656/pexels-photo-175656.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1"
                alt=""
            />
        </div>
        <div
            class="flex items-center justify-center overflow-y-scroll pt-[100px] pb-[100px]"
        >
            <div class="w-[80%] p-6 space-y-4 md:space-y-6 sm:p-8">
                <!-- <a
                            href="#"
                            class="mb-6 text-2xl font-semibold text-gray-900 dark:text-white"
                        >
                            <img
                                class="w-[200px] mr-2"
                                src="../../images/logo-new.png"
                                alt="Raheem Sons logo"
                            />
                        </a> -->
                <h1
                    class="text-xl font-medium leading-tight tracking-tight text-gray-800 md:text-3xl mb-4"
                >
                    Welcome!
                </h1>
                <span class="text-base font-normal text-muted-foreground">
                    Create your account
                </span>
                <form @submit.prevent="handleRegister" class="space-y-4 md:space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-3">
                            <FormField v-slot="{ componentField }" name="email">
                                <FormItem>
                                    <FormLabel>Your email</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="email"
                                            placeholder="Enter your email address"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="password"
                            >
                                <FormItem>
                                    <FormLabel>Password</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="password"
                                            placeholder="••••••••"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="first_name"
                            >
                                <FormItem>
                                    <FormLabel>First Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your first name"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="last_name"
                            >
                                <FormItem>
                                    <FormLabel>Last Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your last name"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>
                    </div>
                    <Button
                        type="submit"
                        :disabled="isLoading"
                        class="w-full py-6"
                    >
                        <span v-if="!isLoading"> Sign Up </span>
                        <span v-else class="flex items-center gap-2"
                            ><LoaderCircle
                                class="w-5 h-5 animate-spin"
                            />Loading...</span
                        >
                    </Button>
                </form>
                <div class="flex items-center justify-between gap-4">
                    <hr class="w-full" />
                    <span>or</span>
                    <hr class="w-full" />
                </div>
                <p class="mt-2 mx-auto text-center">
                    <span class="text-muted-foreground mr-2"
                        >Already have and account?</span
                    >
                    <router-link :to="{ name: 'Login' }" class="text-primary"
                        >Log In</router-link
                    >
                </p>
            </div>
        </div>
    </section>
</template>
