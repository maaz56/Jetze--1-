<script setup>
import InputMessage from "@/components/common/InputMessage.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { LoaderCircle } from "lucide-vue-next";
import {
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from "@/components/ui/form";
import { LOGIN } from "@/services/store/actions.type";
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";

const store = useStore();
const router = useRouter();

const apiError = computed(() => store.getters["auth/apiError"]);
const isLoading = computed(() => store.getters["auth/isLoading"]);

const loginForm = useForm({
    validationSchema: toTypedSchema(
        z.object({
            email: z.string().email().min(2).max(50),
            password: z.string().min(4),
        })
    ),
});

const handleLogin = loginForm.handleSubmit((values) => {
    store
        .dispatch("auth/" + LOGIN, {
            email: values.email,
            password: values.password,
        })
        .then(() => {
            if (apiError.value == null) {
                router.push({ name: "Dashboard" });
            }
        });
});
</script>

<template>
    <section class="grid grid-cols-2 h-screen">
        <div class="h-screen p-2">
            <img
                class="h-full w-full object-cover bg-center rounded-xl border"
                src="https://images.pexels.com/photos/1285625/pexels-photo-1285625.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1"
                alt=""
            />
        </div>
        <div class="flex flex-col items-center justify-center">
            <div class="w-[50%] p-6 space-y-4 md:space-y-6 sm:p-8">
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
                    Log In to your account
                </span>
                <form @submit="handleLogin" class="space-y-4 md:space-y-6">
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
                        <FormField v-slot="{ componentField }" name="password">
                            <FormItem>
                                <FormLabel>Password</FormLabel>
                                <FormControl>
                                    <Input
                                        type="passoword"
                                        placeholder="••••••••"
                                        v-bind="componentField"
                                        class="py-6"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                    <Button
                        type="submit"
                        :disabled="isLoading"
                        class="w-full py-6"
                    >
                        <span v-if="!isLoading"> Login </span>
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
                        >Don't have an account?</span
                    >
                    <router-link :to="{ name: 'Register' }" class="text-primary"
                        >Sign Up</router-link
                    >
                </p>
                <p class="mt-2 mx-auto text-center">
                    <span class="text-muted-foreground mr-2"
                        >Are you agent?</span
                    >
                    <router-link :to="{ name: 'AgentRegister' }" class="text-primary"
                        >Sign Up</router-link
                    >
                </p>
                <p class="text-center">
                    <router-link
                        :to="{ name: 'ForgetPassword' }"
                        class="text-primary"
                        ><span>Forget password?</span>
                    </router-link>
                </p>
            </div>
        </div>
    </section>
</template>
