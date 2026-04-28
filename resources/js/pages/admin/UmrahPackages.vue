<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import FileUploader from "@/components/common/FileUploader.vue";
import { useStore } from "vuex";
import {
    DELETE_UMRAH_HEADER_IMAGES,
    DELETE_UMRAH_PACKAGE,
    FETCH_UMRAH_HEADER_IMAGES,
    FETCH_UMRAH_PACKAGES,
    SAVE_UMRAH_HEADER_IMAGES,
} from "@/services/store/actions.type";
import { computed, onMounted } from "vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { ref } from "vue";
import { Trash2 } from "lucide-vue-next";

const store = useStore();

const umrahPackages = computed(
    () => store.getters["umrahPackage/umrahPackages"]
);
const initialHeaderImages = computed(
    () => store.getters["umrahPackage/headerImages"]
);

const headerImages = ref([]);
const removeFiles = ref(false);

function fetchUmrahPackages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_PACKAGES);
}

function fetchUmrahHeaderImages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_HEADER_IMAGES);
}

function saveHeaderImages() {
    store
        .dispatch("umrahPackage/" + SAVE_UMRAH_HEADER_IMAGES, {
            headerImages: headerImages.value,
        })
        .then(() => {
            removeFiles.value = true;
            headerImages.value = [];

            setTimeout(() => {
                removeFiles.value = false;
            }, 0);
        });
}

function deleteUmrahPackage(id) {
    store.dispatch("umrahPackage/" + DELETE_UMRAH_PACKAGE, {
        id: id,
    });
}

function deleteUmrahHeaderImage(name) {
    store.dispatch("umrahPackage/" + DELETE_UMRAH_HEADER_IMAGES, {
        file_name: name,
    });
}

onMounted(() => {
    fetchUmrahPackages();
    fetchUmrahHeaderImages();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between my-8">
                <span class="text-3xl font-medium">Umrah packages</span>

                <div class="flex gap-2">
                    <Dialog>
                        <DialogTrigger>
                            <Button>Header images</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Header Images</DialogTitle>

                                <div
                                    v-if="initialHeaderImages.length > 0"
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <Card
                                        v-for="file in initialHeaderImages"
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
                                            <div
                                                class="flex items-center justify-center w-full h-full"
                                            >
                                                <button
                                                    type="button"
                                                    @click="
                                                        deleteUmrahHeaderImage(
                                                            file.name
                                                        )
                                                    "
                                                    class="text-white"
                                                >
                                                    <Trash2 class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </div>
                                    </Card>
                                </div>

                                <FileUploader
                                    @file-uploaded="
                                        (file) => {
                                            headerImages.push(file);
                                        }
                                    "
                                    :removeFiles="removeFiles"
                                />
                            </DialogHeader>
                            <DialogFooter>
                                <Button
                                    @click="saveHeaderImages"
                                    :disabled="headerImages.length == 0"
                                    >Save changes</Button
                                >
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <Button @click="$router.push({ name: 'NewUmrahPackage' })"
                        >New Umrah package</Button
                    >
                </div>
            </div>
            <div
                class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border"
            >
                <div
                    class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4"
                >
                    <div class="w-full md:w-1/2">
                        <form class="flex items-center">
                            <label for="simple-search" class="sr-only"
                                >Search</label
                            >
                            <div class="relative w-full">
                                <div
                                    class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"
                                >
                                    <svg
                                        aria-hidden="true"
                                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                        fill="currentColor"
                                        viewbox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    id="simple-search"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="Search"
                                    required=""
                                />
                            </div>
                        </form>
                    </div>
                    <div
                        class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0"
                    >
                        <div
                            class="flex items-center space-x-3 w-full md:w-auto"
                        ></div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <div v-if="umrahPackages.length == 0">
                        <NothingFound />
                    </div>
                    <table
                        v-if="umrahPackages.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                    >
                        <thead
                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Header Image
                                </th>
                                <th scope="col" class="px-4 py-3">Title</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="umrahPackage in umrahPackages"
                                :key="umrahPackage.id"
                                class="border-b dark:border-gray-700"
                            >
                                <td class="px-4 py-3">
                                    <img
                                        :src="umrahPackage.header_image"
                                        alt=""
                                        class="w-20 h-20"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    {{ umrahPackage.title }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="flex gap-4">
                                        <Button
                                            class="w-20"
                                            @click="
                                                deleteUmrahPackage(
                                                    umrahPackage.id
                                                )
                                            "
                                            >Delete</Button
                                        >
                                        <Button
                                            @click="
                                                $router.push({
                                                    name: 'UpdateUmrahPackage',
                                                    query: {
                                                        umrah_package_id:
                                                            umrahPackage.id,
                                                    },
                                                })
                                            "
                                            class="w-20"
                                            >Edit</Button
                                        >
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>
