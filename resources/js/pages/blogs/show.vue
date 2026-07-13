<script setup>
import { useRoute } from 'vue-router';
import { ref, computed, onMounted } from 'vue';
import { useStore } from "vuex";
import { FETCH_BLOG } from "@/services/store/actions.type";
import { Calendar, Share2, Twitter, Linkedin } from 'lucide-vue-next';

const store = useStore();
const route = useRoute();

const blogKey = computed(() => route.params.slug || route.params.id);
const blog = ref(null);

function fetchBlog() {
  store.dispatch('blog/' + FETCH_BLOG, blogKey.value)
    .then(response => {
      blog.value = response?.data?.data || response?.data || null;
    });
}
onMounted(async () => {
  fetchBlog();
});

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

</script>

<template>
 <div class="min-h-screen bg-gray-50">
    <div v-if="blog?.content" class="container mx-auto px-4 py-12 md:py-16">
      <!-- Header Section -->
      <header class="mb-8 md:mb-12">
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4 leading-tight">
          {{ blog.title }}
        </h1>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between text-sm text-gray-600">
          <div class="flex items-center mb-4 md:mb-0">
            <Calendar class="w-5 h-5 mr-2 text-gray-500" />
            <span>{{ formatDate(blog.published_at) }}</span>
          </div>
          <div v-if="blog.seo?.meta_keywords" class="flex items-center">
            <span class="font-medium mr-2">Keywords:</span>
            <div class="flex flex-wrap gap-2">
              <span v-for="(keyword, index) in blog.seo.meta_keywords.split(',')" :key="index" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-full text-xs">
                {{ keyword.trim() }}
              </span>
            </div>
          </div>
        </div>
      </header>

      <!-- Featured Image (assuming blog.data.featured_image exists; otherwise, omit or make conditional) -->
      <div v-if="blog.featured_image" class="mb-8 md:mb-12">
        <img :src="`/storage/${blog.featured_image}`" alt="Featured image" class="w-full h-64 md:h-96 object-cover rounded-xl shadow-lg">
      </div>

      <!-- Content Section -->
      <article class="prose prose-lg max-w-none prose-headings:text-gray-800 prose-a:text-blue-600 prose-img:rounded-xl prose-img:shadow-md mx-auto">
        <div v-html="blog.content"></div>
      </article>

      <!-- Footer Section (optional enhancements like share buttons) -->
      <footer class="mt-12 pt-8 border-t border-gray-200">
        <div class="flex justify-between items-center">
          <p class="text-sm text-gray-500">Thank you for reading!</p>
          <div class="flex space-x-4">
            <button class="text-gray-600 hover:text-blue-500"><Share2 class="w-5 h-5" /></button>
            <button class="text-gray-600 hover:text-blue-500"><Twitter class="w-5 h-5" /></button>
            <button class="text-gray-600 hover:text-blue-500"><Linkedin class="w-5 h-5" /></button>
          </div>
        </div>
      </footer>
    </div>

    <div v-else class="container mx-auto px-4 py-12 md:py-16 text-center">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-4">Blog Not Found</h1>
      <p class="text-lg text-gray-600 mb-6">Sorry, we couldn't find the blog post you're looking for.</p>
      <router-link to="/" class="inline-block px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">Return to Home</router-link>
    </div>
  </div>
</template>

<style scoped>
/* Additional custom styles if needed beyond Tailwind */
.prose :where(pre):not(:where([class~="not-prose"] *)) {
  background-color: #f5f5f5;
  border-radius: 0.5rem;
  padding: 1rem;
}


</style>
