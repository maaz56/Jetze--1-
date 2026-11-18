<template>
  <div class="space-y-5">
    <!-- Top Bar Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <Button
          variant="outline"
          size="sm"
          class="h-8 px-2.5 text-xs rounded border-slate-200 text-slate-600 hover:bg-slate-100 max-sm:hidden"
          @click="router.push({ name: 'Dashboard' })"
        >
          <ArrowLeft class="w-3.5 h-3.5 mr-1" />
          Back
        </Button>
        <div>
          <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
            SEO & Social Metadata
          </h1>
          <p class="text-xs text-slate-500 mt-0.5">
            Optimize search engine visibility, Open Graph previews, and structured metadata for static pages
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Button
          type="button"
          :disabled="loading || saving"
          class="h-9 px-4 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
          @click="save"
        >
          <Save class="w-4 h-4" />
          <span>{{ saving ? 'Saving Changes...' : 'Save Settings' }}</span>
        </Button>
      </div>
    </div>

    <!-- KPI Metrics Ribbon -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Active Target</span>
          <Globe class="h-4 w-4 text-primary" />
        </div>
        <div class="mt-1 text-base font-bold text-slate-900 truncate">
          {{ activePageLabel }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Indexing</span>
          <CheckCircle2 v-if="form.is_active" class="h-4 w-4 text-emerald-500" />
          <AlertCircle v-else class="h-4 w-4 text-amber-500" />
        </div>
        <div class="mt-1 text-base font-bold" :class="form.is_active ? 'text-emerald-700' : 'text-amber-700'">
          {{ form.is_active ? 'Enabled' : 'Paused' }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Robots Directive</span>
          <Code2 class="h-4 w-4 text-slate-400" />
        </div>
        <div class="mt-1 text-xs font-bold font-mono text-slate-800 truncate" :title="form.robots_meta">
          {{ form.robots_meta }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Social Card</span>
          <Share2 class="h-4 w-4 text-sky-500" />
        </div>
        <div class="mt-1 text-base font-bold" :class="ogPreview ? 'text-sky-700' : 'text-slate-500'">
          {{ ogPreview ? 'Image Ready' : 'No Image' }}
        </div>
      </div>
    </div>

    <!-- Page Selector Segmented Tabs -->
    <div class="rounded border border-slate-200 bg-white p-1.5 shadow-xs flex flex-wrap items-center justify-between gap-3">
      <div class="inline-flex rounded border border-slate-200 bg-slate-50 p-1 gap-1">
        <button
          v-for="item in pages"
          :key="item.value"
          type="button"
          @click="selectPage(item.value)"
          class="rounded px-4 py-2 text-xs font-semibold transition-all duration-150 flex items-center gap-2"
          :class="page === item.value ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
        >
          <span class="h-2 w-2 rounded-sm" :class="page === item.value ? 'bg-primary' : 'bg-slate-300'"></span>
          <span>{{ item.label }}</span>
          <span class="text-[10px] font-mono text-slate-400">/{{ item.path }}</span>
        </button>
      </div>

      <div class="text-xs text-slate-500 pr-2 flex items-center gap-1.5">
        <Sparkles class="w-3.5 h-3.5 text-amber-500" />
        <span>Live SERP snippet updates in real time</span>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="rounded border border-slate-200 bg-white p-20 text-center shadow-xs">
      <Spinner class="mx-auto mb-3" />
      <span class="text-xs font-medium text-slate-500">Loading SEO configuration for {{ activePageLabel }}...</span>
    </div>

    <!-- Main Content Grid -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-5">
      <!-- Left Column: Form Settings (7 cols) -->
      <form @submit.prevent="save" class="lg:col-span-7 space-y-5">
        <!-- 1. Search Engine Settings -->
        <section class="rounded border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
          <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Search class="w-4 h-4 text-primary" />
                <span>Search Engine Meta</span>
              </h2>
              <p class="text-[11px] text-slate-500 mt-0.5">Primary snippet tags used by Google, Bing, and web spiders</p>
            </div>
            <span class="text-[10px] font-mono font-semibold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-600">
              HTML Head
            </span>
          </div>

          <!-- Meta Title -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <label class="font-semibold text-slate-700">Meta Title <span class="text-rose-500">*</span></label>
              <span
                class="font-mono text-[11px] px-1.5 py-0.2 rounded"
                :class="form.meta_title?.length >= 30 && form.meta_title?.length <= 60 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ form.meta_title?.length || 0 }} / 60 chars
              </span>
            </div>
            <input
              v-model="form.meta_title"
              maxlength="255"
              placeholder="e.g. About Jetze | Fast & Reliable Flight Bookings"
              class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
            />
            <p v-if="error('meta_title')" class="text-[11px] text-rose-600 font-medium">{{ error('meta_title') }}</p>
          </div>

          <!-- Page Heading H1 -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">Main Page Heading (H1)</label>
            <input
              v-model="form.h1"
              placeholder="e.g. About Our Airline Travel Agency"
              class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
            />
            <p v-if="error('h1')" class="text-[11px] text-rose-600 font-medium">{{ error('h1') }}</p>
          </div>

          <!-- Meta Description -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <label class="font-semibold text-slate-700">Meta Description</label>
              <span
                class="font-mono text-[11px] px-1.5 py-0.2 rounded"
                :class="form.meta_description?.length >= 70 && form.meta_description?.length <= 160 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ form.meta_description?.length || 0 }} / 160 chars
              </span>
            </div>
            <textarea
              v-model="form.meta_description"
              rows="3"
              placeholder="Concise 1-2 sentence overview summarizing page content in search results..."
              class="w-full px-3 py-2 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition resize-none"
            ></textarea>
            <p v-if="error('meta_description')" class="text-[11px] text-rose-600 font-medium">{{ error('meta_description') }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
            <!-- Canonical URL -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Canonical URL</label>
              <input
                v-model="form.canonical_url"
                type="url"
                :placeholder="canonicalPlaceholder"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              />
              <p v-if="error('canonical_url')" class="text-[11px] text-rose-600 font-medium">{{ error('canonical_url') }}</p>
            </div>

            <!-- SEO Keywords -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">SEO Keywords (Comma Separated)</label>
              <input
                v-model="form.meta_keywords"
                placeholder="flight booking, cheap tickets, airline"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              />
            </div>

            <!-- Breadcrumb Title -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Breadcrumb Title</label>
              <input
                v-model="form.breadcrumb_title"
                placeholder="Short breadcrumb label"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              />
            </div>

            <!-- Image Alt Text -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Image Alt Text</label>
              <input
                v-model="form.alt_text"
                placeholder="Descriptive image accessibility text"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              />
            </div>
          </div>
        </section>

        <!-- 2. Open Graph & Social Cards -->
        <section class="rounded border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
          <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Share2 class="w-4 h-4 text-sky-600" />
                <span>Open Graph & Social Share</span>
              </h2>
              <p class="text-[11px] text-slate-500 mt-0.5">Rich link previews displayed on WhatsApp, Facebook, LinkedIn, and X</p>
            </div>
            <span class="text-[10px] font-mono font-semibold uppercase px-2 py-0.5 rounded bg-sky-50 text-sky-700">
              og:tags
            </span>
          </div>

          <!-- OG Title -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <label class="font-semibold text-slate-700">OG Card Title</label>
              <button
                type="button"
                @click="form.og_title = form.meta_title"
                class="text-[11px] font-semibold text-primary hover:underline"
              >
                Copy Meta Title
              </button>
            </div>
            <input
              v-model="form.og_title"
              placeholder="Title for social media sharing"
              class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
            />
          </div>

          <!-- OG Image Upload Dropzone -->
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-700">OG Share Image (1200×630px recommended)</label>
            <div class="flex items-center gap-3">
              <div
                v-if="ogPreview"
                class="w-24 h-16 rounded border border-slate-200 bg-slate-50 overflow-hidden flex-shrink-0 relative group"
              >
                <img :src="ogPreview" alt="OG Preview" class="w-full h-full object-cover" />
                <button
                  type="button"
                  @click="clearImage('og')"
                  class="absolute inset-0 bg-slate-900/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-[10px] font-bold"
                >
                  <Trash2 class="w-3.5 h-3.5 mr-1" /> Remove
                </button>
              </div>

              <label class="flex-1 flex flex-col items-center justify-center h-16 rounded border-2 border-dashed border-slate-200 bg-slate-50 hover:bg-slate-100/60 hover:border-primary cursor-pointer transition p-2">
                <div class="flex items-center gap-1.5 text-xs text-slate-600 font-medium">
                  <Upload class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ ogPreview ? 'Change OG Image' : 'Upload OG Image' }}</span>
                </div>
                <span class="text-[10px] text-slate-400 mt-0.5">JPG, PNG, or WebP up to 5MB</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="setImage($event, 'og')" />
              </label>
            </div>
          </div>

          <!-- OG Description -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between text-xs">
              <label class="font-semibold text-slate-700">OG Card Description</label>
              <button
                type="button"
                @click="form.og_description = form.meta_description"
                class="text-[11px] font-semibold text-primary hover:underline"
              >
                Copy Meta Description
              </button>
            </div>
            <textarea
              v-model="form.og_description"
              rows="2"
              placeholder="Short description for social share card..."
              class="w-full px-3 py-2 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition resize-none"
            ></textarea>
          </div>

          <!-- Twitter Section Accordion / Fields -->
          <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
              <div class="flex items-center justify-between text-xs">
                <label class="font-semibold text-slate-700">Twitter Title</label>
                <button
                  type="button"
                  @click="form.twitter_title = form.og_title || form.meta_title"
                  class="text-[10px] font-semibold text-primary hover:underline"
                >
                  Sync OG
                </button>
              </div>
              <input
                v-model="form.twitter_title"
                placeholder="Twitter card title"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              />
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Twitter Image</label>
              <div class="flex items-center gap-2">
                <div v-if="twitterPreview" class="w-12 h-9 rounded border border-slate-200 bg-slate-50 overflow-hidden flex-shrink-0">
                  <img :src="twitterPreview" alt="Twitter Preview" class="w-full h-full object-cover" />
                </div>
                <label class="flex-1 h-9 px-3 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center justify-center text-xs font-medium text-slate-600 cursor-pointer transition">
                  <span>{{ twitterPreview ? 'Replace' : 'Upload' }}</span>
                  <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="setImage($event, 'twitter')" />
                </label>
                <button
                  v-if="twitterPreview"
                  type="button"
                  @click="clearImage('twitter')"
                  class="h-9 px-2 text-rose-600 hover:text-rose-700 text-xs font-semibold"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- 3. Robots Indexing & Schema JSON-LD -->
        <section class="rounded border border-slate-200 bg-white p-4 sm:p-5 shadow-xs space-y-4">
          <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Code2 class="w-4 h-4 text-emerald-600" />
                <span>Robots & Structured Schema</span>
              </h2>
              <p class="text-[11px] text-slate-500 mt-0.5">Control search bot behavior and embed JSON-LD schema</p>
            </div>
            <span class="text-[10px] font-mono font-semibold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">
              Indexing
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <!-- Robots Meta -->
            <div class="space-y-1.5">
              <label class="block text-xs font-semibold text-slate-700">Robots Meta Directive</label>
              <select
                v-model="form.robots_meta"
                class="w-full h-9 px-3 rounded border border-slate-200 bg-white text-xs text-slate-900 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
              >
                <option value="index, follow">index, follow (Standard Public)</option>
                <option value="index, nofollow">index, nofollow (Index only)</option>
                <option value="noindex, follow">noindex, follow (Do not index)</option>
                <option value="noindex, nofollow">noindex, nofollow (Strictly Hidden)</option>
              </select>
            </div>

            <!-- SEO Enabled Checkbox Switch -->
            <div class="flex items-end">
              <label class="flex w-full items-center justify-between h-9 px-3 rounded border border-slate-200 bg-slate-50 hover:bg-slate-100/70 cursor-pointer transition">
                <span class="text-xs font-semibold text-slate-800">SEO Active</span>
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-primary rounded" />
              </label>
            </div>
          </div>

          <!-- Schema JSON-LD -->
          <div class="space-y-1.5 pt-1">
            <div class="flex items-center justify-between text-xs">
              <label class="font-semibold text-slate-700">Schema JSON-LD</label>
              <span class="text-[10px] font-mono text-slate-400">application/ld+json</span>
            </div>
            <textarea
              v-model="form.schema_json"
              rows="5"
              placeholder='{"@context": "https://schema.org", "@type": "AboutPage"}'
              class="w-full p-3 rounded border border-slate-200 bg-slate-50 font-mono text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-primary focus:bg-white transition"
            ></textarea>
            <p v-if="error('schema_json')" class="text-[11px] text-rose-600 font-medium">{{ error('schema_json') }}</p>
          </div>
        </section>

        <!-- Save Button Row -->
        <div class="flex justify-end pt-2">
          <Button
            type="submit"
            :disabled="loading || saving"
            class="h-9 px-5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
          >
            <Save class="w-4 h-4" />
            <span>{{ saving ? 'Saving Changes...' : 'Save All Settings' }}</span>
          </Button>
        </div>
      </form>

      <!-- Right Column: Real-time SERP & Social Previews (5 cols) -->
      <div class="lg:col-span-5 space-y-5">
        <!-- Live Google Search Result Preview -->
        <div class="rounded border border-slate-200 bg-white p-4 shadow-xs space-y-3">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <div class="flex items-center gap-2">
              <span class="h-2 w-2 rounded-sm bg-emerald-500"></span>
              <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Google Search Preview</h3>
            </div>
            <span class="text-[10px] font-mono text-slate-400">Desktop SERP</span>
          </div>

          <!-- Realistic Google SERP Card -->
          <div class="p-3.5 rounded border border-slate-100 bg-slate-50/50 space-y-1">
            <!-- URL Path -->
            <div class="flex items-center gap-1.5 text-xs text-slate-600">
              <div class="h-4 w-4 rounded-sm bg-slate-200 flex items-center justify-center text-[9px] font-bold text-slate-600">J</div>
              <span class="truncate font-sans text-[12px] text-[#202124]">jetze.com</span>
              <span class="text-[11px] text-[#5f6368]">› {{ activePagePath }}</span>
            </div>

            <!-- Title -->
            <div class="pt-0.5">
              <h4 class="text-[15px] font-medium leading-snug text-[#1a0dab] hover:underline cursor-pointer break-words">
                {{ form.meta_title || `${activePageLabel} | Jetze` }}
              </h4>
            </div>

            <!-- Snippet Description -->
            <p class="text-xs leading-relaxed text-[#4d5156] break-words line-clamp-3">
              {{ form.meta_description || 'No meta description provided. Search engines will automatically extract text from page content.' }}
            </p>
          </div>
        </div>

        <!-- Live Social Share Card Preview -->
        <div class="rounded border border-slate-200 bg-white p-4 shadow-xs space-y-3">
          <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <div class="flex items-center gap-2">
              <span class="h-2 w-2 rounded-sm bg-sky-500"></span>
              <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Social Share Preview</h3>
            </div>
            <span class="text-[10px] font-mono text-slate-400">Facebook / WhatsApp / X</span>
          </div>

          <div class="rounded border border-slate-200 overflow-hidden bg-white shadow-xs">
            <!-- Image Area -->
            <div class="h-36 bg-slate-100 flex items-center justify-center overflow-hidden border-b border-slate-100">
              <img
                v-if="ogPreview"
                :src="ogPreview"
                alt="Social Card Preview"
                class="w-full h-full object-cover"
              />
              <div v-else class="flex flex-col items-center justify-center text-slate-400 gap-1">
                <Image class="w-6 h-6 text-slate-300" />
                <span class="text-[11px]">No social preview image uploaded</span>
              </div>
            </div>

            <!-- Card Meta Text -->
            <div class="p-3 bg-slate-50 space-y-1">
              <div class="text-[10px] uppercase font-mono tracking-wider text-slate-400">jetze.com</div>
              <div class="text-xs font-bold text-slate-900 truncate">
                {{ form.og_title || form.meta_title || activePageLabel }}
              </div>
              <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                {{ form.og_description || form.meta_description || 'Link preview description will appear here...' }}
              </p>
            </div>
          </div>
        </div>

        <!-- SEO Health Checklist -->
        <div class="rounded border border-slate-200 bg-white p-4 shadow-xs space-y-3">
          <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider border-b border-slate-100 pb-2">
            SEO Audit Checklist
          </h3>

          <ul class="space-y-2 text-xs">
            <li class="flex items-center justify-between">
              <span class="text-slate-600">Meta Title length (30-60 chars)</span>
              <span
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold"
                :class="form.meta_title?.length >= 30 && form.meta_title?.length <= 60 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
              >
                {{ form.meta_title?.length >= 30 && form.meta_title?.length <= 60 ? 'Optimal' : 'Needs attention' }}
              </span>
            </li>

            <li class="flex items-center justify-between">
              <span class="text-slate-600">Meta Description (70-160 chars)</span>
              <span
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold"
                :class="form.meta_description?.length >= 70 && form.meta_description?.length <= 160 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
              >
                {{ form.meta_description?.length >= 70 && form.meta_description?.length <= 160 ? 'Optimal' : 'Needs attention' }}
              </span>
            </li>

            <li class="flex items-center justify-between">
              <span class="text-slate-600">Canonical Tag</span>
              <span
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold"
                :class="form.canonical_url ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
              >
                {{ form.canonical_url ? 'Custom' : 'Default' }}
              </span>
            </li>

            <li class="flex items-center justify-between">
              <span class="text-slate-600">Social Share Image</span>
              <span
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold"
                :class="ogPreview ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
              >
                {{ ogPreview ? 'Uploaded' : 'Missing' }}
              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import apiService from '@/services/store/apiService';
import { toast } from 'vue3-toastify';
import { Button } from '@/components/ui/button';
import Spinner from '@/components/common/Spinner.vue';
import {
  ArrowLeft,
  Save,
  Search,
  Globe,
  Share2,
  Code2,
  CheckCircle2,
  AlertCircle,
  Sparkles,
  Trash2,
  Upload,
  Image,
} from 'lucide-vue-next';

const router = useRouter();

const pages = [
  { value: 'about-us', label: 'About Us', path: 'about/us' },
  { value: 'contact-us', label: 'Contact Us', path: 'contact/us' },
];

const page = ref('about-us');
const loading = ref(true);
const saving = ref(false);
const errors = ref({});
const files = reactive({ og: null, twitter: null });
const previews = reactive({ og: '', twitter: '' });

const activePageLabel = computed(() => pages.find((p) => p.value === page.value)?.label || 'Page');
const activePagePath = computed(() => pages.find((p) => p.value === page.value)?.path || page.value);

const empty = () => ({
  meta_title: '',
  meta_description: '',
  meta_keywords: '',
  h1: '',
  canonical_url: '',
  robots_meta: 'index, follow',
  og_title: '',
  og_description: '',
  og_image: '',
  twitter_title: '',
  twitter_description: '',
  twitter_image: '',
  schema_json: '',
  breadcrumb_title: '',
  alt_text: '',
  is_active: true,
  remove_og_image: false,
  remove_twitter_image: false,
});

const form = reactive(empty());
const canonicalPlaceholder = computed(() => `${window.location.origin}/${page.value === 'about-us' ? 'about/us' : 'contact/us'}`);
const storageUrl = (path) => (path ? `${window.location.origin}/storage/${path}` : '');
const ogPreview = computed(() => previews.og || storageUrl(form.og_image));
const twitterPreview = computed(() => previews.twitter || storageUrl(form.twitter_image));
const error = (key) => errors.value[key]?.[0] || '';

async function load() {
  loading.value = true;
  errors.value = {};
  Object.assign(form, empty());
  try {
    const { data } = await apiService.getSeoSetting(page.value);
    Object.assign(form, data.data, {
      schema_json: data.data.schema_json ? JSON.stringify(data.data.schema_json, null, 2) : '',
    });
  } catch {
    toast.error('Unable to load SEO settings.');
  } finally {
    loading.value = false;
  }
}

function selectPage(value) {
  page.value = value;
  files.og = files.twitter = null;
  previews.og = previews.twitter = '';
  load();
}

function setImage(event, type) {
  const file = event.target.files?.[0];
  if (!file) return;
  files[type] = file;
  previews[type] = URL.createObjectURL(file);
  form[`remove_${type}_image`] = false;
}

function clearImage(type) {
  files[type] = null;
  previews[type] = '';
  form[`${type}_image`] = '';
  form[`remove_${type}_image`] = true;
}

async function save() {
  saving.value = true;
  errors.value = {};
  const data = new FormData();
  Object.entries(form).forEach(([key, value]) =>
    data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? ''))
  );
  if (files.og) data.append('og_image', files.og);
  if (files.twitter) data.append('twitter_image', files.twitter);
  try {
    const response = await apiService.saveSeoSetting(page.value, data);
    Object.assign(form, response.data.data, {
      schema_json: response.data.data.schema_json ? JSON.stringify(response.data.data.schema_json, null, 2) : '',
    });
    files.og = files.twitter = null;
    previews.og = previews.twitter = '';
    toast.success(response.data.message || 'SEO settings saved successfully.');
  } catch (e) {
    errors.value = e.response?.data?.errors || {};
    toast.error(e.response?.data?.message || 'Unable to save SEO settings.');
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
