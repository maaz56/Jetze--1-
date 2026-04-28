<script setup>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import { FETCH_BLOGS } from '@/services/store/actions.type';
import { Calendar } from 'lucide-vue-next';

const store = useStore();

const blogs = ref([]);
const isLoading = ref(false);

onMounted(async () => {
  isLoading.value = true;
  try {
    await store.dispatch(`blog/${FETCH_BLOGS}`);
    blogs.value = store.getters['blog/blogs'].data;
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
});

</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Blogs</h1>
   
    <p class="text-gray-600" v-if="isLoading">Loading...</p>
   
    <ul class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" v-else>
      <li v-for="blog in blogs" :key="blog?.id" class="bg-white rounded-lg shadow-md p-4">
  
        <router-link :to="{ name: 'Blog', params: { id: blog?.id, slug: blog?.slug }}">
           <img :src="blog.featured_image ? `/storage/${blog.featured_image}` : null" :alt="blog.title" class="w-full md:w-1/2 rounded-lg">
           
          <div class="flex flex-col md:flex-row">
            <div class="flex flex-col md:pl-6">
              <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold">{{ blog?.title }}</h2>
                <div class="flex items-center text-gray-600">
                  <Calendar class="w-6 h-6 mr-2" />
                  <span>{{ new Date(blog?.published_at).toLocaleDateString() }}</span>
                </div>
              </div>
              <p class="text-gray-600 mt-2">{{ blog?.excerpt }}</p>
            </div>
          </div>
        </router-link>
      </li>
    </ul>
  </div>
</template>

