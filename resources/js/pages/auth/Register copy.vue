<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import InputMessage from "@/components/ui/inputMessage.vue";
import { Label } from "@/components/ui/label";
import { RadioGroup } from "@/components/ui/radio-group";
import RadioGroupItem from "@/components/ui/radio-group/RadioGroupItem.vue";
import { useAuthStore } from "@/services/stores/auth";
import { computed, ref } from "vue";
import { useRouter } from "vue-router";


const authStore = useAuthStore();
const router = useRouter();


const validationMessages = computed(() => authStore.validationMessages);
const isLoading = computed(() => authStore.isLoading);

const role = ref("");
const email = ref("");
const password = ref("");
const confirmPassword = ref("");
const errorMessage = ref("");

async function handleRegister() {
    try {
        await authStore.register({
            role: role.value,
            email: email.value,
            password: password.value,
            password_confirmation: confirmPassword.value,
        });

        // Redirect to login page on successful registration
        router.push({ name: "VerifyMessage" });
    } catch (error) {
        // Handle errors (e.g., show a message to the user)
        errorMessage.value = "Registration failed. Please check your inputs.";
        console.error(error);
    }
}
</script>

<template>
    <div class="w-full lg:grid lg:grid-cols-2 h-screen">
        <div class="flex items-center justify-center py-12">
            <div class="mx-auto grid w-[350px] gap-6">
                <div class="grid gap-2">
                    <h1 class="text-3xl font-bold">Register</h1>
                    <p class="text-balance text-muted-foreground">
                        Enter your information to create an account
                    </p>
                </div>
                <form @submit.prevent="handleRegister()" class="grid gap-4">
                    <div>
                        <RadioGroup
                            v-model="role"
                            class="grid grid-cols-2 gap-2"
                        >
                            <label
                                for="user"
                                class="flex items-center space-x-2 border p-3 rounded-lg"
                                :class="{
                                    'text-primary bg-primary/10':
                                        role === 'user',
                                }"
                            >
                                <RadioGroupItem id="user" value="user" />
                                <Label for="user">Register as User</Label>
                            </label>
                            <label
                                for="agent"
                                class="flex items-center space-x-2 border p-3 rounded-lg"
                                :class="{
                                    'text-primary bg-primary/10':
                                        role === 'agent',
                                }"
                            >
                                <RadioGroupItem id="agent" value="agent" />
                                <Label for="agent">Register as Agent</Label>
                            </label>
                        </RadioGroup>
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            v-model="email"
                            id="email"
                            type="email"
                            placeholder="m@example.com"
                        />
                        <InputMessage v-if="validationMessages?.email">
                            {{ validationMessages.email }}
                        </InputMessage>
                    </div>
                    <div class="grid gap-2">
                        <div class="flex items-center">
                            <Label for="password">Password</Label>
                        </div>
                        <Input
                            v-model="password"
                            id="password"
                            type="password"
                            placeholder="••••••••"
                        />
                        <InputMessage v-if="validationMessages?.password">
                            {{ validationMessages.password }}
                        </InputMessage>
                    </div>
                    <div class="grid gap-2">
                        <div class="flex items-center">
                            <Label for="confirm-password"
                                >Confirm Password</Label
                            >
                        </div>
                        <Input
                            v-model="confirmPassword"
                            id="confirm-password"
                            type="password"
                            placeholder="••••••••"
                        />
                    </div>
                    <Button type="submit" :isLoading="isLoading" class="w-full">
                        Register
                    </Button>
                    <!-- <Button variant="outline" class="w-full">
                        Login with Google
                    </Button> -->
                </form>
                <div class="mt-4 text-center text-sm">
                    Already have an account?
                    <router-link :to="{ name: 'Login' }" class="underline">
                        Sign in
                    </router-link>
                </div>
            </div>
        </div>
        <div class="hidden bg-muted lg:block">
            <img
                src=""
                alt="Image"
                width="1920"
                height="1080"
                class="h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
            />
        </div>
    </div>
</template>
