<script setup>
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { FORGOT_PASSWORD, LOGIN } from "@/services/store/actions.type";
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";

const store = useStore();
const router = useRouter();

const email = ref("");

function handleEmailVerification() {
    store.dispatch("auth/" + FORGOT_PASSWORD, {
        email: email.value,
    });
}
</script>

<template>
    <section>
        <div
            class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0"
        >
            <a
                href="#"
                class="flex flex-col gap-4 items-center mb-6 text-2xl font-semibold text-gray-900 dark:text-white"
            >
                <img
                    class="w-[200px] mr-2"
                    src=""
                    alt=""
                />
            </a>
            <div
                class="w-full bg-white rounded-lg dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700"
            >
                <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                    <h1
                        class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white"
                    >
                        Please verify you email
                    </h1>
                    <form
                        @submit.prevent="handleForgotPassword()"
                        class="space-y-4 md:space-y-6"
                        action="#"
                    >
                        <Button type="submit" class="w-full">
                            Resent verification
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>
