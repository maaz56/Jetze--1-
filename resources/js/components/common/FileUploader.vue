<script setup>
import { ref, computed, defineEmits } from "vue";
import { CloudUpload, LoaderCircle, Trash2 } from "lucide-vue-next";
import { Card } from "@/components/ui/card";
import { toast } from "vue3-toastify";
import apiService from "@/services/store/apiService";

const emit = defineEmits(["file-uploaded"]);

const props = defineProps({
    initialFiles: {
        type: Array,
        default: () => [],
    },
});

const files = ref([]);
const isLoading = ref(false);

const combinedFiles = computed(() => {
    const normalizedInitialFiles = Array.isArray(props.initialFiles)
        ? props.initialFiles.map((file) => ({
              name: file.name,
              url: file.url,
          }))
        : [];

    return [...normalizedInitialFiles, ...files.value];
});

function handleFileChange(event) {
    const formData = new FormData();
    formData.append("file", event.target.files[0]);
    uploadFile(formData);
}

// Commenting out fetchFiles as it's no longer needed
// async function fetchFiles() {
//     isLoading.value = true;
//     try {
//         const response = await apiService.getUploadedFiles();
//         files.value = response.data;
//     } catch (error) {
//         console.error(error);
//         toast(error.message, {
//             theme: "dark",
//             type: "error",
//             dangerouslyHTMLString: true,
//         });
//     } finally {
//         isLoading.value = false;
//     }
// }

async function uploadFile(formData) {
    isLoading.value = true;
    try {
        const response = await apiService.uploadFile(formData);
        // Directly push the new file to the files array
        files.value.push(response.data);
        toast("File Uploaded.", {
            theme: "dark",
            type: "success",
            dangerouslyHTMLString: true,
        });
        // Emit the uploaded file data to the parent
        emit("file-uploaded", response.data);
    } catch (error) {
        console.error(error);
        toast(error.message, {
            theme: "dark",
            type: "error",
            dangerouslyHTMLString: true,
        });
    } finally {
        isLoading.value = false;
    }
}

async function deleteFile(name) {
    isLoading.value = true;
    try {
        await apiService.deleteUploadedFiles({ file_name: name });
        // Remove the file from the files array
        files.value = files.value.filter((file) => file.name !== name);
        toast("File deleted successfully.", {
            theme: "dark",
            type: "success",
            dangerouslyHTMLString: true,
        });
    } catch (error) {
        console.error(error);
        toast(error.message, {
            theme: "dark",
            type: "error",
            dangerouslyHTMLString: true,
        });
    } finally {
        isLoading.value = false;
    }
}

// Commented out fetchFiles as it is no longer needed
// onMounted(() => {
//     fetchFiles();
// });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div
            v-if="combinedFiles.length > 0"
            class="flex flex-wrap items-center gap-2"
        >
            <Card
                v-for="file in combinedFiles"
                :key="file.name"
                class="relative w-24 h-24 overflow-hidden group"
            >
                <img
                    class="w-full h-full object-cover"
                    :src="file.url"
                    alt=""
                />
                <div
                    class="absolute top-0 left-0 bottom-0 right-0 bg-black bg-opacity-50 hidden group-hover:block cursor-pointer"
                >
                    <div class="flex items-center justify-center w-full h-full">
                        <button
                            type="button"
                            @click="deleteFile(file.name)"
                            class="text-white"
                        >
                            <Trash2 class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </Card>
        </div>

        <label for="file">
            <Card
                class="w-24 h-24 flex flex-col items-center justify-center p-4 cursor-pointer"
            >
                <input
                    @change="handleFileChange"
                    type="file"
                    id="file"
                    hidden
                />
                <LoaderCircle v-if="isLoading" class="w-5 h-5 animate-spin" />
                <CloudUpload v-else class="w-8 h-8 text-primary" />
            </Card>
        </label>
    </div>
</template>
