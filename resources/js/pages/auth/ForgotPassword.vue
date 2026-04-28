<script setup>
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import InputMessage from "@/components/ui/inputMessage.vue";
import Label from "@/components/ui/label/Label.vue";
import { useAuthStore } from "@/services/stores/auth";
import { computed, ref } from "vue";

const authStore = useAuthStore();

const isLoading = computed(() => authStore.isLoading);
const validationMessages = computed(() => authStore.validationMessages);

const email = ref("");

function handleForgotPassword() {
    authStore.forgotPassword({
        email: email.value,
    });
}
</script>

<template>
    <section>
        <div
            class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0"
        >
            <div
                class="w-full bg-white rounded-lg dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700"
            >
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                    <a href="#">
                        <img
                            class="w-[100px] mr-2"
                            src="/public/assets/logo.png"
                            alt=""
                        />
                    </a>
                    <div class="grid gap-2">
                        <h1
                            class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white"
                        >
                            Reset Password
                        </h1>
                        <p class="text-balance text-muted-foreground">
                            Enter your email below to get a verification email
                        </p>
                    </div>
                    <form
                        @submit.prevent="handleForgotPassword()"
                        class="space-y-4 md:space-y-6"
                        action="#"
                    >
                        <div class="grid gap-2">
                            <Label for="email">Your email</Label>
                            <Input
                                v-model="email"
                                type="email"
                                name="email"
                                id="email"
                                placeholder="name@gmail.com"
                            />
                            <InputMessage v-if="validationMessages?.email">
                                {{ validationMessages.email }}
                            </InputMessage>
                        </div>
                        <Button
                            type="submit"
                            :isLoading="isLoading"
                            class="w-full"
                        >
                            Reset Password
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>
