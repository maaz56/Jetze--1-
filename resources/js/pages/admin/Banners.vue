<script setup>
import { computed, onMounted, ref } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import InputMessage from "@/components/ui/inputMessage.vue";
import { useStore } from "vuex";

import { Textarea } from "@/components/ui/textarea";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon } from "lucide-vue-next";
import { useAuthStore } from "@/services/stores/auth";
import {
    DELETE_PROMO_IMAGE,
    FETCH_PROMO_IMAGES,
    SAVE_AGENT_DATA,
    SAVE_PROMO_IMAGE,
    UPDATE_PROMO_IMAGE,
} from "@/services/store/actions.type";
import { useRouter } from "vue-router";

const store = useStore();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const router = useRouter();

const imageTitle = ref("");
const image = ref(null);
const validationErrors = ref([]);

const promoImages = computed(() => store.getters["promoImage/promoImageData"]);

const handleImageChange = (event) => {
    image.value = event.target.files[0];
};
function handleImage() {
    let errors = [];

    if (!imageTitle.value) {
        errors.push("Title field is required.");
    }
    if (errors.length > 0) {
        validationErrors.value = errors;
        return;
    }

    const imageData = new FormData();
    imageData.append("image_title", imageTitle.value);

    if (image.value) {
        imageData.append("image", image.value); // Append the logo file if it exists
    }

    validationErrors.value = [];
    store.dispatch("promoImage/" + SAVE_PROMO_IMAGE, imageData).then(() => {
        // Reset form fields
        imageTitle.value = "";
        image.value = null;
    });
    validationErrors.value = [];
    
}



function updateIsHome(imageId, isHome) {
    store.dispatch("promoImage/" + UPDATE_PROMO_IMAGE, {
        id: imageId,
        isHome: isHome,
    });
}
function removePromoImage(id) {
    store.dispatch("promoImage/" + DELETE_PROMO_IMAGE, {
        id: id,
        deleted: true,
    });
}

function fetchPromoImages() {
    store.dispatch("promoImage/" + FETCH_PROMO_IMAGES);
}

onMounted(() => {
    fetchPromoImages();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between my-8">
                <span class="text-3xl font-medium">Promo Images</span>
            </div>

            <div class="w-full">
                <div class="mb-6" v-if="validationErrors.length > 0">
                    <ul
                        class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive"
                    >
                        <li v-for="error in validationErrors" :key="error.id">
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="handleImage" class="space-y-6">
                    <div class="flex gap-6 items-end">
                        <div class="space-y-2">
                            <Label
                                for="title"
                                class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >Image Title</Label
                            >
                            <Input
                                v-model="imageTitle"
                                id="imageTitle"
                                type="text"
                                placeholder="Enter image title"
                                class="w-full"
                            />
                        </div>

                        <div class="space-y-2">
                            <div class="grid gap-2">
                                <Label
                                    for="image"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >Image</Label
                                >
                            </div>
                            <Input
                                class="w-full"
                                id="image"
                                type="file"
                                @change="handleImageChange"
                            />
                        </div>
                        <Button
                            type="submit"
                            class="text-white font-semibold py-3 rounded-lg"
                        >
                            <SaveIcon class="w-5 h-5 mr-2" />
                            Save Image
                        </Button>
                    </div>
                </form>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            <div
            v-for="image in promoImages"
            :key="image.id"
            class="border rounded-lg p-4 shadow-sm"
            >
            <img
                :src="image.url"
                :alt="image.title"
                class="w-full h-48 object-cover rounded-md mb-2"
            />
            <h3 class="font-semibold text-lg mb-2">{{ image.title }}</h3>
            <div class="flex items-center mb-2">
                <input
                    type="checkbox"
                    :checked="image.is_home === 1"
                    @change="updateIsHome(image.id, $event.target.checked)"
                    class="accent-primary scale-125 form-checkbox h-5 w-5 rounded border-gray-300 focus:ring-0 focus:outline-none mr-2 transition duration-150 ease-in-out"
                    id="isHome-{{ image.id }}"
                    
                />
                <label :for="'isHome-' + image.id" class="text-sm text-700 dark:text-gray-300">Show on Home</label>
            </div>
            <div class="flex justify-end">
                <button
                @click="removePromoImage(image.id)"
                class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded"
                >
                Remove
                </button>
            </div>
            </div>
        </div>
    </section>
</template>
