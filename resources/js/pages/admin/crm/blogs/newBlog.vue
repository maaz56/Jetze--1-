<template>
  <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8 bg-gray-50">
    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow border p-6 sm:p-8">
      <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ isEditMode ? 'Edit Blog Post' : 'Create New Blog Post' }}
        </h1>
        <Button variant="outline" @click="$router.back()">
          <ArrowLeft class="w-4 h-4 mr-2" />
          Back
        </Button>
      </div>
      <form @submit.prevent="submitForm" class="space-y-10">
        <!-- Main Content -->
        <section class="space-y-6">
          <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">
              Title <span class="text-red-600">*</span>
            </label>
            <input
              id="title"
              v-model="form.title"
              type="text"
              placeholder="e.g. 10 Trading Mistakes Beginners Make in 2026"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
              required
            />
            <p v-if="errors.title" class="mt-1.5 text-sm text-red-600">{{ errors.title }}</p>
          </div>

          <div>
            <label for="slug" class="block text-sm font-medium text-gray-700 mb-1.5">
              Slug <span class="text-red-600">*</span>
            </label>
            <input
              id="slug"
              v-model="form.slug"
              type="text"
              placeholder="10-trading-mistakes-beginners-2026"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg font-mono focus:ring-2 focus:ring-primary focus:border-primary transition"
              required
            />
            <p class="mt-1.5 text-xs text-gray-500">Auto-generated from title — edit if needed</p>
            <p v-if="errors.slug" class="mt-1.5 text-sm text-red-600">{{ errors.slug }}</p>
          </div>

          <div>
            <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-1.5">
              Excerpt / Summary (120–160 chars recommended)
            </label>
            <textarea
              id="excerpt"
              v-model="form.excerpt"
              rows="3"
              placeholder="Short description shown in search results and social previews..."
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
            ></textarea>
            <p v-if="errors.excerpt" class="mt-1.5 text-sm text-red-600">{{ errors.excerpt }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              Content <span class="text-red-600">*</span>
            </label>
            <QuillEditor
              v-model:content="form.content"
              contentType="html"
              theme="snow"
              placeholder="Start writing your blog post here..."
              class="min-h-[320px] rounded-lg border border-gray-300 focus-within:ring-2 focus-within:ring-primary focus-within:border-primary transition"
            />
            <p v-if="errors.content" class="mt-1.5 text-sm text-red-600">{{ errors.content }}</p>
          </div>

          <!-- Featured Image -->
          <div class="space-y-3">
            <label class="block text-sm font-medium text-gray-700">
              Featured Image <span class="text-gray-500 text-xs">(1200×630 recommended)</span>
            </label>
            <div
              class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-primary transition-colors cursor-pointer bg-gray-50 relative"
              @dragover.prevent
              @drop.prevent="handleDrop($event, 'featured')"
              @click="$refs.featuredImageInput.click()"
            >
              <input
                ref="featuredImageInput"
                type="file"
                accept="image/*"
                class="hidden"
                @change="handleImageUpload($event, 'featured')"
              />

              <div v-if="!form.featured_image_preview" class="space-y-3">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm text-gray-600">
                  <span class="font-medium text-primary">Click or drag</span> image here
                </p>
              </div>

              <div v-else class="relative inline-block">
                
                <img
                  :src="form.featured_image_preview"
                  alt="Preview"
                  class="max-h-64 mx-auto rounded-lg shadow object-cover"
                />
                <button
                  type="button"
                  @click="removeImage('featured')"
                  class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-2 hover:bg-red-700 transition"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            <p v-if="errors.featured_image" class="mt-1.5 text-sm text-red-600">{{ errors.featured_image }}</p>
          </div>
        </section>

        <!-- SEO & Social -->
        <section class="bg-gray-50 p-6 rounded-xl border space-y-8">
          <h2 class="text-xl font-semibold text-gray-900 border-b border-gray-200 pb-3">
            SEO & Social Media Settings
          </h2>

          <div>
            <label for="focus_keyword" class="block text-sm font-medium text-gray-700 mb-1.5">
              Focus Keyword
            </label>
            <input
              id="focus_keyword"
              v-model="form.focus_keyword"
              type="text"
              placeholder="trading mistakes 2026, beginner errors"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
            />
          </div>

          <div>
            <div class="flex justify-between mb-1.5">
              <label class="text-sm font-medium text-gray-700">Meta Title <span class="text-xs text-gray-500">(50–60 chars)</span></label>
              <span :class="metaTitleLengthClass">{{ metaTitleLength }} / 60</span>
            </div>
            <input
              v-model="form.meta_title"
              type="text"
              placeholder="10 Common Trading Mistakes in 2026 | Guide"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
            />
          </div>

          <div>
            <div class="flex justify-between mb-1.5">
              <label class="text-sm font-medium text-gray-700">Meta Description <span class="text-xs text-gray-500">(120–160 chars)</span></label>
              <span :class="metaDescLengthClass">{{ metaDescLength }} / 160</span>
            </div>
            <textarea
              v-model="form.meta_description"
              rows="3"
              placeholder="Avoid these costly beginner mistakes in 2026..."
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
            ></textarea>
          </div>

          <!-- Social Image Selector -->
          <div class="pt-4 border-t border-gray-200">
            <h3 class="text-base font-medium text-gray-800 mb-4">Social Preview Image (Open Graph)</h3>

            <div class="flex flex-col sm:flex-row gap-4">
              <button
                type="button"
                @click="socialImageMode = 'featured'"
                :class="socialImageMode === 'featured'
                  ? 'bg-primary text-white border-primary'
                  : 'bg-white text-gray-700 border-gray-300 hover:bg-secondary/10'"
                class="flex-1 py-3 px-4 border-2 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
              >
                Use Featured Image
              </button>

              <button
                type="button"
                @click="socialImageMode = 'custom'"
                :class="socialImageMode === 'custom'
                  ? 'bg-primary text-white border-primary'
                  : 'bg-white text-gray-700 border-gray-300 hover:bg-secondary/10'"
                class="flex-1 py-3 px-4 border-2 rounded-lg font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary"
              >
                Upload / Replace OG Image
              </button>
            </div>

            <div v-if="socialImageMode === 'custom'" class="mt-5 space-y-3">
              <div
                class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-primary transition-colors cursor-pointer bg-white"
                @dragover.prevent
                @drop.prevent="handleDrop($event, 'og')"
                @click="$refs.ogImageInput.click()"
              >
                <input
                  ref="ogImageInput"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="handleImageUpload($event, 'og')"
                />

                <div v-if="!form.og_image_preview" class="space-y-2">
                  <p class="text-sm text-gray-600">
                    <span class="font-medium text-primary">Upload</span> custom social image
                  </p>
                  <p class="text-xs text-gray-500">1200×630 recommended</p>
                </div>

                <div v-else class="relative inline-block">
                  <img
                    :src="form.og_image_preview"
                    alt="OG preview"
                    class="max-h-48 mx-auto rounded shadow object-cover"
                  />
                  <button
                    type="button"
                    @click="removeImage('og')"
                    class="absolute top-2 right-2 bg-red-600 text-white rounded-full p-1.5 hover:bg-red-700 transition"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              <p v-if="errors.og_image" class="mt-1.5 text-sm text-red-600">{{ errors.og_image }}</p>
            </div>

            <p v-if="socialImageMode === 'featured' && form.featured_image_preview" class="mt-3 text-sm text-green-700">
              Using featured image for social previews
            </p>
          </div>

          <div class="pt-4 border-t border-gray-200">
            <label for="canonical_url" class="block text-sm font-medium text-gray-700 mb-1.5">
              Canonical URL
            </label>
            <input
              id="canonical_url"
              v-model="form.canonical_url"
              type="url"
              placeholder="https://example.com/original-post"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition"
            />
            <p v-if="errors.canonical_url" class="mt-1.5 text-sm text-red-600">{{ errors.canonical_url }}</p>
          </div>

          <div class="flex gap-8 pt-3">
            <label class="flex items-center gap-2">
              <input v-model="form.no_index" type="checkbox" class="h-5 w-5 text-red-600 rounded focus:ring-primary" />
              <span class="text-sm text-gray-700">No Index</span>
            </label>
            <label class="flex items-center gap-2">
              <input v-model="form.no_follow" type="checkbox" class="h-5 w-5 text-red-600 rounded focus:ring-primary" />
              <span class="text-sm text-gray-700">No Follow</span>
            </label>
          </div>
        </section>

        <!-- Submit -->
        <div class="pt-6">
          <button
            type="submit"
            :disabled="isSubmitting || isFetching"
            class="w-full py-3.5 px-6 bg-primary text-white font-medium rounded-lg shadow hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition"
          >
            <span v-if="isSubmitting">Saving...</span>
            <span v-else-if="isEditMode">Update Blog Post</span>
            <span v-else>Create Blog Post</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import { toast } from 'vue3-toastify';
import {
  SAVE_BLOG,
  UPDATE_BLOG,
  FETCH_BLOG,
} from '@/services/store/actions.type';
// import { getBlogRoutes } from '../../../../api/blogApi';
// const blogRoutes = await getBlogRoutes();
const route = useRoute();
const router = useRouter();
const store = useStore();

const isEditMode = computed(() => !!route.params.id);
const blogId = computed(() => route.params.id);

const form = reactive({
  title: '',
  slug: '',
  content: '',
  excerpt: '',
  featured_image: null,
  featured_image_preview: null,
  og_image_file: null,
  og_image_preview: null,
  focus_keyword: '',
  meta_title: '',
  meta_description: '',
  og_title: '',
  canonical_url: '',
  no_index: false,
  no_follow: false,
});

const socialImageMode = ref('featured');
const errors = reactive({});
const isSubmitting = ref(false);
const isFetching = ref(false);
const thisBlog = ref([]);
const MAX_IMAGE_SIZE_BYTES = 5 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

const metaTitleLength = computed(() => form.meta_title?.length || 0);
const metaDescLength = computed(() => form.meta_description?.length || 0);

const metaTitleLengthClass = computed(() => {
  if (metaTitleLength.value > 60) return 'text-red-600 text-xs';
  if (metaTitleLength.value > 50) return 'text-amber-600 text-xs';
  return 'text-green-600 text-xs';
});

const metaDescLengthClass = computed(() => {
  if (metaDescLength.value > 160) return 'text-red-600 text-xs';
  if (metaDescLength.value > 120) return 'text-amber-600 text-xs';
  return 'text-green-600 text-xs';
});

function extractPlainTextFromHtml(html) {
  const div = document.createElement('div');
  div.innerHTML = html || '';
  return (div.textContent || div.innerText || '').trim();
}

function isValidUrl(url) {
  try {
    new URL(url);
    return true;
  } catch (_) {
    return false;
  }
}

function clearErrors() {
  Object.keys(errors).forEach((key) => {
    errors[key] = '';
  });
}

function validateImageFile(file, fieldName) {
  if (!file) return true;

  if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
    errors[fieldName] = 'Only JPG, JPEG, PNG, or WEBP images are allowed.';
    return false;
  }

  if (file.size > MAX_IMAGE_SIZE_BYTES) {
    errors[fieldName] = 'Image size must not exceed 5MB.';
    return false;
  }

  errors[fieldName] = '';
  return true;
}

function validateForm() {
  clearErrors();
  let isValid = true;

  if (!form.title?.trim()) {
    errors.title = 'Title is required.';
    isValid = false;
  } else if (form.title.trim().length > 255) {
    errors.title = 'Title must not exceed 255 characters.';
    isValid = false;
  }

  if (!form.slug?.trim()) {
    errors.slug = 'Slug is required.';
    isValid = false;
  } else if (form.slug.trim().length > 255) {
    errors.slug = 'Slug must not exceed 255 characters.';
    isValid = false;
  } else if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug.trim())) {
    errors.slug = 'Slug may only contain lowercase letters, numbers, and single hyphens.';
    isValid = false;
  }

  const plainContent = extractPlainTextFromHtml(form.content);
  if (!plainContent) {
    errors.content = 'Content is required.';
    isValid = false;
  }

  if (form.excerpt && form.excerpt.length > 500) {
    errors.excerpt = 'Excerpt must not exceed 500 characters.';
    isValid = false;
  }

  if (form.focus_keyword && form.focus_keyword.length > 255) {
    errors.focus_keyword = 'Focus keyword must not exceed 255 characters.';
    isValid = false;
  }

  if (form.meta_title && form.meta_title.length > 255) {
    errors.meta_title = 'Meta title must not exceed 255 characters.';
    isValid = false;
  }

  if (form.meta_description && form.meta_description.length > 500) {
    errors.meta_description = 'Meta description must not exceed 500 characters.';
    isValid = false;
  }

  if (form.og_title && form.og_title.length > 255) {
    errors.og_title = 'OG title must not exceed 255 characters.';
    isValid = false;
  }

  if (form.canonical_url && !isValidUrl(form.canonical_url)) {
    errors.canonical_url = 'Canonical URL must be a valid URL.';
    isValid = false;
  } else if (form.canonical_url && form.canonical_url.length > 500) {
    errors.canonical_url = 'Canonical URL must not exceed 500 characters.';
    isValid = false;
  }

  if (!validateImageFile(form.featured_image, 'featured_image')) {
    isValid = false;
  }

  if (socialImageMode.value === 'custom' && !validateImageFile(form.og_image_file, 'og_image')) {
    isValid = false;
  }

  return isValid;
}

function applyBackendErrors(err) {
  const backendErrors = err?.response?.data?.errors;
  if (!backendErrors || typeof backendErrors !== 'object') return;

  Object.entries(backendErrors).forEach(([key, value]) => {
    errors[key] = Array.isArray(value) ? value[0] : value;
  });
}

// Auto-generate slug
watch(() => form.title, (newTitle) => {
  if (!form.slug || form.slug.trim() === '') {
    form.slug = newTitle
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '');
  }
});

// Fetch blog if editing
onMounted(async () => {
  if (isEditMode.value) {
    isFetching.value = true;
    try {
      await store.dispatch(`blog/${FETCH_BLOG}`, blogId.value);
      const blog = store.getters['blog/currentBlog'];
      thisBlog.value = blog;

      if (blog) {
        Object.assign(form, {
          title: blog.title || '',
          slug: blog.slug || '',
          content: blog.content || '',
          excerpt: blog.excerpt || '',
          focus_keyword: blog.seo?.meta_keywords || '',
          meta_title: blog.seo?.meta_title || '',
          meta_description: blog.seo?.meta_description || '',
          og_title: blog.seo?.og_title || '',
          canonical_url: blog.seo?.canonical_url || '',
          no_index: blog.seo?.no_index || false,
          no_follow: blog.seo?.no_follow || false,
        });

        // Set image previews from existing paths
        if (blog.featured_image) {
          form.featured_image_preview = `/storage/${blog.featured_image}`;
        }
        if (blog.seo?.og_image) {
          form.og_image_preview = `/storage/${blog.seo.og_image}`;
          socialImageMode.value = 'custom'; // prefer custom if exists
        }
      }
    } catch (err) {
      toast.error('Failed to load blog post');
      router.push({ name: 'Blogs' });
    } finally {
      isFetching.value = false;
    }
  }
});

function handleImageUpload(e, type) {
  const file = e.target.files?.[0];
  if (!file) return;

  const fieldName = type === 'featured' ? 'featured_image' : 'og_image';
  if (!validateImageFile(file, fieldName)) {
    toast.error(errors[fieldName]);
    return;
  }

  const previewUrl = URL.createObjectURL(file);

  if (type === 'featured') {
    if (form.featured_image_preview && !form.featured_image_preview.startsWith('blob:')) {
      // Don't revoke existing server URL
    } else if (form.featured_image_preview) {
      URL.revokeObjectURL(form.featured_image_preview);
    }
    form.featured_image = file;
    form.featured_image_preview = previewUrl;
  } else if (type === 'og') {
    if (form.og_image_preview && !form.og_image_preview.startsWith('blob:')) {
      // Don't revoke server URL
    } else if (form.og_image_preview) {
      URL.revokeObjectURL(form.og_image_preview);
    }
    form.og_image_file = file;
    form.og_image_preview = previewUrl;
    socialImageMode.value = 'custom';
  }
}

function handleDrop(e, type) {
  e.preventDefault();
  const file = e.dataTransfer.files?.[0];
  if (file) {
    handleImageUpload({ target: { files: [file] } }, type);
  }
}

function removeImage(type) {
  if (type === 'featured') {
    if (form.featured_image_preview && form.featured_image_preview.startsWith('blob:')) {
      URL.revokeObjectURL(form.featured_image_preview);
    }
    form.featured_image = null;
    form.featured_image_preview = null;
  } else if (type === 'og') {
    if (form.og_image_preview && form.og_image_preview.startsWith('blob:')) {
      URL.revokeObjectURL(form.og_image_preview);
    }
    form.og_image_file = null;
    form.og_image_preview = null;
  }
}

async function submitForm() {
  if (!validateForm()) {
    toast.error('Please fix the errors in the form');
    return;
  }

  const payload = new FormData();

  payload.append('title', form.title);
  payload.append('slug', form.slug);
  payload.append('content', form.content || '');
  payload.append('excerpt', form.excerpt || '');

  if (form.featured_image) {
    payload.append('featured_image', form.featured_image);
  }

  payload.append('focus_keyword', form.focus_keyword || '');
  payload.append('meta_title', form.meta_title || '');
  payload.append('meta_description', form.meta_description || '');
  payload.append('og_title', form.og_title || '');

  if (socialImageMode.value === 'custom' && form.og_image_file) {
    payload.append('og_image', form.og_image_file);
  }

  payload.append('canonical_url', form.canonical_url || '');
  payload.append('no_index', form.no_index ? '1' : '0');
  payload.append('no_follow', form.no_follow ? '1' : '0');

  isSubmitting.value = true;

  try {
    if (isEditMode.value) {
      payload.append('id', blogId.value);
      await store.dispatch(`blog/${UPDATE_BLOG}`, payload);
      toast.success('Blog post updated successfully');
      router.push({ name: 'Blogs' });
    } else {
      await store.dispatch(`blog/${SAVE_BLOG}`, payload);
      toast.success('Blog post created successfully');
      router.push({ name: 'Blogs' });
    }
  } catch (err) {
    applyBackendErrors(err);
    const firstBackendError = err?.response?.data?.errors
      ? Object.values(err.response.data.errors)?.[0]?.[0]
      : null;
    toast.error(firstBackendError || err?.response?.data?.message || 'Failed to save blog post');
    console.error(err);
  } finally {
    isSubmitting.value = false;
  }
}
</script>
