<template>
    <div class=" mx-auto p-6 bg-white rounded-lg border">
        <h2 class="text-2xl font-bold mb-4 text-center">Zoho API Authentication</h2>
        <form @submit.prevent="submitCredentials">
            <div class="mb-4">
                <label for="client_id" class="block text-sm font-medium text-gray-700">Client ID</label>
                <input v-model="form.client_id" id="client_id" type="text"
                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter Zoho Client ID" required />
            </div>
            <div class="mb-4">
                <label for="client_secret" class="block text-sm font-medium text-gray-700">Client Secret</label>
                <input v-model="form.client_secret" id="client_secret" type="password"
                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter Zoho Client Secret" required />
            </div>
            <button type="submit"
                class="w-full bg-primary text-white p-2 rounded-md hover:bg-primary/50 disabled:bg-primary/50"
                :disabled="isLoading">
                {{ isLoading ? 'Submitting...' : 'Submit Credentials' }}
            </button>
        </form>
        <p v-if="message" class="mt-4 text-center" :class="{ 'text-green-600': !error, 'text-red-600': error }">
            {{ message }}
        </p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useStore } from 'vuex';
import { FETCH_KEYS, SAVE_KEYS } from '@/services/store/actions.type';
import { onMounted } from 'vue';

// Reactive form data
const form = ref({
    client_id: '',
    client_secret: '',
});

const keys = computed(() => {
    return store.getters['zoho/keys']
})

// State for feedback and loading
const message = ref('');
const error = ref(false);
const isLoading = ref(false);
const store = useStore();

// Submit credentials to Laravel backend
const submitCredentials = async () => {
    console.log("Submitting credentials:", form.value);
    const baseURL =
        import.meta.env.VITE_MODE === "production"
            ? import.meta.env.VITE_PRODUCTION_CALLBACK_URL
            : import.meta.env.VITE_LOCAL_CALLBACK_URL;
    isLoading.value = true;


    try {
        store.dispatch('zoho/' + SAVE_KEYS, form.value).then(() => {
            const clientId = encodeURIComponent(form.value.client_id);
            const redirectUri = encodeURIComponent(`${baseURL}/zoho/callback`);
            const scope = "ZohoBooks.fullaccess.all";
            const responseType = "code";
            const accessType = "offline";
            const prompt = "consent";

            const url = `${import.meta.env.VITE_ZOHO_AUTH_URL}/auth?response_type=${responseType}&client_id=${clientId}&redirect_uri=${redirectUri}&scope=${scope}&access_type=${accessType}&prompt=${prompt}`;

            window.location.href = url;
        });
        error.value = false;
    } catch (err) {
        console.error("Error submitting credentials:", err);
        error.value = true;
    } finally {
        isLoading.value = false;
    }
};


watch(() => {
    if (keys.value) {
        form.value.client_id = keys.value.client_id || '';
        form.value.client_secret = keys.value.client_secret || '';


    }
})

const fetchCredentials = async () => {
    try {
        await store.dispatch('zoho/' + FETCH_KEYS, { client_id: form.value.client_id, }).then(() => {

        });

    } catch (error) {
        console.error("Error fetching credentials:", error);
    }
}

onMounted(() => {
    fetchCredentials();
})
</script>

<style scoped>
/* Optional: Tailwind CSS is used in the template, so no additional styles needed here */
</style>