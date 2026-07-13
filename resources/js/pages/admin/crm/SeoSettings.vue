<template>
  <div class="min-h-screen bg-gray-50 p-4 sm:p-6 lg:p-8">
    <div class="mx-auto max-w-6xl">
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-xs font-semibold uppercase tracking-widest text-primary">CMS</p><h1 class="mt-1 text-2xl font-bold text-gray-950">SEO Settings</h1><p class="mt-1 text-sm text-gray-500">Control how your static pages appear in search and social sharing.</p></div>
        <button @click="save" :disabled="loading || saving" class="rounded bg-primary px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90 disabled:opacity-50">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
      </div>

      <div class="mb-6 grid grid-cols-2 gap-3 rounded border border-gray-200 bg-white p-2 shadow-sm">
        <button v-for="item in pages" :key="item.value" @click="selectPage(item.value)" class="rounded px-4 py-3 text-sm font-semibold transition" :class="page === item.value ? 'bg-secondary text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'">{{ item.label }}</button>
      </div>

      <div v-if="loading" class="rounded border border-gray-200 bg-white p-16 text-center text-gray-500">Loading SEO settings...</div>
      <form v-else @submit.prevent="save" class="space-y-6">
        <section class="rounded border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-6 border-b border-gray-100 pb-4"><h2 class="text-lg font-bold text-gray-900">Search Engine Settings</h2><p class="mt-1 text-xs text-gray-500">Titles and descriptions shown by Google and other search engines.</p></div>
          <div class="grid gap-5 md:grid-cols-2">
            <Field label="Meta Title" :count="`${form.meta_title.length}/60`" :error="error('meta_title')"><input v-model="form.meta_title" maxlength="255" class="input" placeholder="Page title for search results"></Field>
            <Field label="Page H1" :error="error('h1')"><input v-model="form.h1" class="input" placeholder="Main page heading"></Field>
            <Field class="md:col-span-2" label="Meta Description" :count="`${form.meta_description.length}/160`" :error="error('meta_description')"><textarea v-model="form.meta_description" rows="4" class="input resize-none" placeholder="Concise page description for search results"></textarea></Field>
            <Field label="Canonical URL" :error="error('canonical_url')"><input v-model="form.canonical_url" type="url" class="input" :placeholder="canonicalPlaceholder"></Field>
            <Field label="SEO Keywords" :error="error('meta_keywords')"><input v-model="form.meta_keywords" class="input" placeholder="flight booking, cheap flights, travel"></Field>
            <Field label="Breadcrumb Title" :error="error('breadcrumb_title')"><input v-model="form.breadcrumb_title" class="input" placeholder="Short navigation title"></Field>
            <Field label="Image Alt Text" :error="error('alt_text')"><input v-model="form.alt_text" class="input" placeholder="Accessible image description"></Field>
          </div>
        </section>

        <section class="rounded border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-6 border-b border-gray-100 pb-4"><h2 class="text-lg font-bold text-gray-900">Open Graph & Twitter</h2><p class="mt-1 text-xs text-gray-500">Customize link previews for Facebook, WhatsApp, X, and other platforms.</p></div>
          <div class="grid gap-5 md:grid-cols-2">
            <Field label="OG Title"><template #action><button type="button" @click="form.og_title = form.meta_title" class="helper">Use SEO title</button></template><input v-model="form.og_title" class="input" placeholder="Open Graph title"></Field>
            <ImageField label="OG Image" :preview="ogPreview" @change="setImage($event, 'og')" @clear="clearImage('og')" />
            <Field class="md:col-span-2" label="OG Description"><template #action><button type="button" @click="form.og_description = form.meta_description" class="helper">Use SEO description</button></template><textarea v-model="form.og_description" rows="4" class="input resize-none" placeholder="Open Graph description"></textarea></Field>
            <Field label="Twitter Title"><template #action><button type="button" @click="form.twitter_title = form.og_title || form.meta_title" class="helper">Use OG title</button></template><input v-model="form.twitter_title" class="input" placeholder="Twitter card title"></Field>
            <ImageField label="Twitter Image" :preview="twitterPreview" @change="setImage($event, 'twitter')" @clear="clearImage('twitter')" />
            <Field class="md:col-span-2" label="Twitter Description"><template #action><button type="button" @click="form.twitter_description = form.og_description || form.meta_description" class="helper">Use OG description</button></template><textarea v-model="form.twitter_description" rows="4" class="input resize-none" placeholder="Twitter card description"></textarea></Field>
          </div>
        </section>

        <section class="rounded border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <div class="mb-6 border-b border-gray-100 pb-4"><h2 class="text-lg font-bold text-gray-900">Advanced SEO</h2></div>
          <div class="grid gap-5 md:grid-cols-2">
            <Field label="Robots Meta"><select v-model="form.robots_meta" class="input bg-white"><option>index, follow</option><option>index, nofollow</option><option>noindex, follow</option><option>noindex, nofollow</option></select></Field>
            <div class="flex items-end"><label class="flex w-full items-center justify-between rounded border border-gray-200 bg-gray-50 px-4 py-3"><span><strong class="block text-sm text-gray-900">SEO enabled</strong><small class="text-gray-500">Output these settings on the public page</small></span><input v-model="form.is_active" type="checkbox" class="h-5 w-5 accent-primary"></label></div>
            <Field class="md:col-span-2" label="Schema JSON-LD" :error="error('schema_json')"><textarea v-model="form.schema_json" rows="9" class="input font-mono text-xs" placeholder='{"@context":"https://schema.org","@type":"AboutPage"}'></textarea></Field>
          </div>
        </section>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import apiService from '@/services/store/apiService';
import { toast } from 'vue3-toastify';

const Field = defineComponent({ props: ['label', 'count', 'error'], setup(props, { slots, attrs }) { return () => h('label', { class: ['block', attrs.class] }, [h('span', { class: 'mb-1.5 flex items-center justify-between text-sm font-semibold text-gray-700' }, [h('span', props.label), slots.action?.() || h('small', { class: 'font-normal text-gray-400' }, props.count)]), slots.default?.(), props.error ? h('small', { class: 'mt-1 block text-red-600' }, props.error) : null]); } });
const ImageField = defineComponent({ props: ['label', 'preview'], emits: ['change', 'clear'], setup(props, { emit }) { return () => h('div', [h('div', { class: 'mb-1.5 text-sm font-semibold text-gray-700' }, props.label), h('label', { class: 'flex min-h-28 cursor-pointer items-center justify-center rounded border-2 border-dashed border-gray-200 bg-gray-50 p-3 hover:border-primary' }, [props.preview ? h('img', { src: props.preview, class: 'h-24 max-w-full object-contain', alt: '' }) : h('span', { class: 'text-sm text-gray-500' }, 'Choose image (1200×630 recommended)'), h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'hidden', onChange: e => emit('change', e) })]), props.preview ? h('button', { type: 'button', class: 'mt-2 text-xs font-semibold text-red-600', onClick: () => emit('clear') }, 'Remove image') : null]); } });

const pages = [{ value: 'about-us', label: 'About Us' }, { value: 'contact-us', label: 'Contact Us' }];
const page = ref('about-us'); const loading = ref(true); const saving = ref(false); const errors = ref({});
const files = reactive({ og: null, twitter: null }); const previews = reactive({ og: '', twitter: '' });
const empty = () => ({ meta_title: '', meta_description: '', meta_keywords: '', h1: '', canonical_url: '', robots_meta: 'index, follow', og_title: '', og_description: '', og_image: '', twitter_title: '', twitter_description: '', twitter_image: '', schema_json: '', breadcrumb_title: '', alt_text: '', is_active: true, remove_og_image: false, remove_twitter_image: false });
const form = reactive(empty());
const canonicalPlaceholder = computed(() => `${window.location.origin}/${page.value === 'about-us' ? 'about/us' : 'contact/us'}`);
const storageUrl = path => path ? `${window.location.origin}/storage/${path}` : '';
const ogPreview = computed(() => previews.og || storageUrl(form.og_image)); const twitterPreview = computed(() => previews.twitter || storageUrl(form.twitter_image));
const error = key => errors.value[key]?.[0] || '';

async function load() { loading.value = true; errors.value = {}; Object.assign(form, empty()); try { const { data } = await apiService.getSeoSetting(page.value); Object.assign(form, data.data, { schema_json: data.data.schema_json ? JSON.stringify(data.data.schema_json, null, 2) : '' }); } catch { toast.error('Unable to load SEO settings.'); } finally { loading.value = false; } }
function selectPage(value) { page.value = value; files.og = files.twitter = null; previews.og = previews.twitter = ''; load(); }
function setImage(event, type) { const file = event.target.files?.[0]; if (!file) return; files[type] = file; previews[type] = URL.createObjectURL(file); form[`remove_${type}_image`] = false; }
function clearImage(type) { files[type] = null; previews[type] = ''; form[`${type}_image`] = ''; form[`remove_${type}_image`] = true; }
async function save() { saving.value = true; errors.value = {}; const data = new FormData(); Object.entries(form).forEach(([key, value]) => data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? ''))); if (files.og) data.append('og_image', files.og); if (files.twitter) data.append('twitter_image', files.twitter); try { const response = await apiService.saveSeoSetting(page.value, data); Object.assign(form, response.data.data, { schema_json: response.data.data.schema_json ? JSON.stringify(response.data.data.schema_json, null, 2) : '' }); files.og = files.twitter = null; previews.og = previews.twitter = ''; toast.success(response.data.message); } catch (e) { errors.value = e.response?.data?.errors || {}; toast.error(e.response?.data?.message || 'Unable to save SEO settings.'); } finally { saving.value = false; } }
onMounted(load);
</script>

<style scoped>
.input { @apply w-full rounded border border-gray-300 bg-white px-3.5 py-2.5 text-sm text-gray-900 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15; }
.helper { @apply text-xs font-medium text-primary hover:underline; }
</style>
