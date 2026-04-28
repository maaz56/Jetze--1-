<script setup>
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import Button from "@/components/ui/button/Button.vue";
</script>

<template>
    <div>
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
            <span class="text-3xl font-medium">Update User</span>
        </div>
        <div class="bg-white rounded-lg py-24">
            <form class="max-w-xs mx-auto p-4">
                <div class="mb-3">
                    <Label for="name">Name</Label>
                    <Input
                        type="text"
                        placeholder="Enter name"
                        v-model="name"
                        id="name"
                    />
                </div>
                <div class="mb-3">
                    <Label for="phone">Phone</Label>
                    <Input
                        type="number"
                        placeholder="Enter phone number"
                        v-model="phone"
                        id="phone"
                    />
                </div>
                <div class="mb-3">
                    <Label for="email">Email Address</Label>
                    <Input
                        type="text"
                        placeholder="Enter email address"
                        v-model="email"
                        id="email"
                    />
                </div>
                <div class="mb-3">
                    <Label for="password">Password</Label>
                    <Input
                        type="text"
                        placeholder="Enter password"
                        v-model="password"
                        id="password"
                    />
                </div>
                <div>
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </div>
    </div>
</template>
