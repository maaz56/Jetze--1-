<script setup>
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";
import Input from "@/components/ui/input/Input.vue";
import Pagination from "@/components/Pagination.vue";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import {
  ArrowLeft,
  Search,
  Pencil,
  Trash2,
  Plus,
  Eye,
  X,
  Send,
  BookOpen,
  CheckCircle2,
  FileText,
  Check,
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import {
  FETCH_BLOGS,
  DELETE_BLOG,
  SEND_BLOG_MAIL,
} from "@/services/store/actions.type";
import NothingFound from "@/components/common/NothingFound.vue";
import Spinner from "@/components/common/Spinner.vue";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);
const blogs = computed(() => store.getters["blog/blogs"] || { data: [], meta: {} });
const isLoading = computed(() => store.getters["blog/isLoading"]);

const showDeleteDialog = ref(false);
const blogToDelete = ref(null);
const selectedBlogIds = ref([]);
const mailAudience = ref('customers');
const isSendingBlogMail = ref(false);

function formatDate(date) {
  if (!date) return '—';

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(date));
}

const statusFilter = ref(route.query.status || 'all');
const searchQuery = ref(route.query.search || '');

const fetchBlogs = debounce(() => {
  store.dispatch("blog/" + FETCH_BLOGS, {
    search: searchQuery.value || undefined,
    page: route.query.page || 1,
    status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
  });
}, 300);

const filteredBlogs = computed(() => {
  return blogs.value.data || [];
});

const selectedBlogCount = computed(() => selectedBlogIds.value.length);
const isAllVisibleSelected = computed(() => {
  return filteredBlogs.value.length > 0 && filteredBlogs.value.every((blog) => selectedBlogIds.value.includes(blog.id));
});

// Quick Metrics
const totalBlogsCount = computed(() => blogs.value.meta?.total ?? filteredBlogs.value.length);
const publishedCount = computed(() => filteredBlogs.value.filter((b) => b.is_published || b.status === 'published').length);
const draftCount = computed(() => filteredBlogs.value.filter((b) => !b.is_published && b.status !== 'published').length);

function setBlogSelected(blogId, checked) {
  if (checked) {
    selectedBlogIds.value = Array.from(new Set([...selectedBlogIds.value, blogId]));
    return;
  }

  selectedBlogIds.value = selectedBlogIds.value.filter((id) => id !== blogId);
}

function toggleVisibleSelection(checked) {
  const visibleIds = filteredBlogs.value.map((blog) => blog.id);

  if (checked) {
    selectedBlogIds.value = Array.from(new Set([...selectedBlogIds.value, ...visibleIds]));
    return;
  }

  selectedBlogIds.value = selectedBlogIds.value.filter((id) => !visibleIds.includes(id));
}

async function sendSelectedBlogsMail() {
  if (!selectedBlogIds.value.length) {
    toast.error("Please select at least one blog.");
    return;
  }

  isSendingBlogMail.value = true;
  try {
    await store.dispatch("blog/" + SEND_BLOG_MAIL, {
      blog_ids: selectedBlogIds.value,
      audience: mailAudience.value,
    });
    selectedBlogIds.value = [];
  } catch (error) {
    // Handled by store
  } finally {
    isSendingBlogMail.value = false;
  }
}

function confirmDelete(blog) {
  blogToDelete.value = blog;
  showDeleteDialog.value = true;
}

function deleteBlog() {
  if (!blogToDelete.value?.id) return;

  store.dispatch("blog/" + DELETE_BLOG, blogToDelete.value.id)
    .then(() => {
      toast.success("Blog deleted successfully");
      fetchBlogs();
    })
    .catch(() => {
      toast.error("Failed to delete blog");
    })
    .finally(() => {
      showDeleteDialog.value = false;
      blogToDelete.value = null;
    });
}

function setStatus(status) {
  statusFilter.value = status;
  router.push({
    path: route.path,
    query: {
      ...route.query,
      status: status !== 'all' ? status : undefined,
      page: undefined,
    }
  });
  fetchBlogs();
}

onMounted(() => {
  fetchBlogs();
});
</script>

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
            Blogs & Content
          </h1>
          <p class="text-xs text-slate-500 mt-0.5">
            Manage travel guides, news, editorial publications, and newsletter distributions
          </p>
        </div>
      </div>

      <div v-if="authUser?.role === 'admin'" class="flex flex-wrap items-center gap-2.5">
        <div class="flex items-center gap-1.5">
          <Select v-model="mailAudience">
            <SelectTrigger class="h-9 w-36 rounded border-slate-200 bg-white text-xs">
              <SelectValue placeholder="Audience" />
            </SelectTrigger>
            <SelectContent class="rounded border-slate-200 bg-white">
              <SelectItem value="customers" class="text-xs">Customers</SelectItem>
              <SelectItem value="subscribers" class="text-xs">Subscribers</SelectItem>
              <SelectItem value="both" class="text-xs">Both Groups</SelectItem>
            </SelectContent>
          </Select>

          <Button
            variant="outline"
            class="h-9 px-3 text-xs font-semibold rounded border-slate-200 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5"
            :disabled="selectedBlogCount === 0 || isSendingBlogMail"
            @click="sendSelectedBlogsMail"
          >
            <Send class="h-3.5 w-3.5 text-primary" />
            <span>{{ isSendingBlogMail ? 'Sending...' : `Broadcast (${selectedBlogCount})` }}</span>
          </Button>
        </div>

        <Button
          class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
          @click="router.push({ name: 'NewBlog' })"
        >
          <Plus class="w-4 h-4" />
          <span>Add Blog</span>
        </Button>
      </div>
    </div>

    <!-- KPI Metrics Ribbon -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Total Articles</span>
          <BookOpen class="h-4 w-4 text-slate-400" />
        </div>
        <div class="mt-1 text-xl font-bold text-slate-900">
          {{ totalBlogsCount }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Published</span>
          <CheckCircle2 class="h-4 w-4 text-emerald-500" />
        </div>
        <div class="mt-1 text-xl font-bold text-emerald-700">
          {{ publishedCount }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Drafts</span>
          <FileText class="h-4 w-4 text-amber-500" />
        </div>
        <div class="mt-1 text-xl font-bold text-amber-700">
          {{ draftCount }}
        </div>
      </div>

      <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
        <div class="flex items-center justify-between text-slate-500">
          <span class="text-[11px] font-bold uppercase tracking-wider">Selected</span>
          <Check class="h-4 w-4 text-primary" />
        </div>
        <div class="mt-1 text-xl font-bold text-primary">
          {{ selectedBlogCount }}
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="rounded border border-slate-200 bg-white shadow-xs">
      <!-- Search & Filters Toolbar -->
      <div class="p-3.5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
        <div class="flex flex-wrap items-center gap-2.5 flex-1">
          <div class="relative w-full max-w-sm">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <Input
              v-model="searchQuery"
              placeholder="Search by title or slug..."
              class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
              @input="
                router.push({
                  path: route.path,
                  query: {
                    ...route.query,
                    search: searchQuery || undefined,
                    page: undefined,
                  },
                });
                fetchBlogs();
              "
            />
          </div>

          <!-- Status Segmented Control -->
          <div class="inline-flex rounded border border-slate-200 bg-slate-50 p-0.5 text-xs font-semibold">
            <button
              type="button"
              class="px-2.5 py-1 rounded transition-colors"
              :class="statusFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="setStatus('all')"
            >
              All
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded transition-colors"
              :class="statusFilter === 'published' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="setStatus('published')"
            >
              Published
            </button>
            <button
              type="button"
              class="px-2.5 py-1 rounded transition-colors"
              :class="statusFilter === 'draft' ? 'bg-white text-amber-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
              @click="setStatus('draft')"
            >
              Draft
            </button>
          </div>

          <!-- Clear Filters -->
          <button
            v-if="statusFilter !== 'all' || searchQuery"
            type="button"
            class="text-xs font-medium text-slate-500 hover:text-slate-800 flex items-center gap-1"
            @click="
              statusFilter = 'all';
              searchQuery = '';
              router.push({ path: route.path, query: { page: undefined } });
              fetchBlogs();
            "
          >
            <X class="w-3.5 h-3.5" />
            Clear
          </button>
        </div>

        <div v-if="selectedBlogCount > 0" class="flex items-center gap-2 text-xs font-medium text-slate-600">
          <span class="inline-flex items-center px-2 py-0.5 rounded bg-primary/10 text-primary font-bold text-[11px]">
            {{ selectedBlogCount }} selected
          </span>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600 underline text-xs"
            @click="selectedBlogIds = []"
          >
            Clear selection
          </button>
        </div>
      </div>

      <!-- Loading Spinner -->
      <section v-if="isLoading" class="py-20 flex justify-center">
        <Spinner />
      </section>

      <!-- Empty State -->
      <section v-else-if="filteredBlogs.length === 0" class="py-12">
        <NothingFound message="No blogs found" />
      </section>

      <!-- Table View -->
      <section v-else>
        <div class="overflow-x-auto">
          <Table>
            <TableHeader class="bg-slate-50 border-b border-slate-200">
              <TableRow>
                <TableHead v-if="authUser?.role === 'admin'" class="w-10 pl-4">
                  <Checkbox
                    :checked="isAllVisibleSelected"
                    aria-label="Select visible blogs"
                    @update:checked="(checked) => toggleVisibleSelection(checked === true)"
                  />
                </TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Title & Excerpt</TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">URL Slug</TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Status</TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Author</TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Published Date</TableHead>
                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="blog in filteredBlogs"
                :key="blog.id"
                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
              >
                <TableCell v-if="authUser?.role === 'admin'" class="w-10 pl-4">
                  <Checkbox
                    :checked="selectedBlogIds.includes(blog.id)"
                    :aria-label="`Select ${blog.title || 'blog'}`"
                    @update:checked="(checked) => setBlogSelected(blog.id, checked === true)"
                  />
                </TableCell>
                <TableCell class="max-w-xs">
                  <div class="flex flex-col">
                    <span class="text-xs font-bold text-slate-900 truncate" :title="blog.title">
                      {{ blog.title }}
                    </span>
                    <span v-if="blog.excerpt" class="text-[11px] text-slate-400 line-clamp-1 mt-0.5">
                      {{ blog.excerpt }}
                    </span>
                  </div>
                </TableCell>
                <TableCell>
                  <span class="font-mono text-xs text-slate-600 max-w-[150px] truncate block" :title="blog.slug">
                    /{{ blog.slug }}
                  </span>
                </TableCell>
                <TableCell>
                  <Badge
                    variant="outline"
                    :class="(blog.is_published || blog.status === 'published')
                      ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                      : 'bg-amber-50 text-amber-700 border-amber-200'"
                    class="rounded text-[11px] font-semibold px-2 py-0.5"
                  >
                    {{ (blog.is_published || blog.status === 'published') ? 'Published' : 'Draft' }}
                  </Badge>
                </TableCell>
                <TableCell>
                  <span class="text-xs font-medium text-slate-700">
                    {{ blog.author?.name || blog.author || 'Admin' }}
                  </span>
                </TableCell>
                <TableCell>
                  <span class="text-xs text-slate-600 font-mono">
                    {{ formatDate(blog.published_at || blog.created_at) }}
                  </span>
                </TableCell>
                <TableCell class="text-right whitespace-nowrap pr-4">
                  <div class="inline-flex items-center gap-1">
                    <Button
                      v-if="authUser?.role === 'admin'"
                      variant="outline"
                      size="sm"
                      class="h-7 px-2.5 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                      @click="router.push({ name: 'UpdateBlog', params: { id: blog.id } })"
                    >
                      <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                      Edit
                    </Button>

                    <Button
                      v-if="authUser?.role === 'admin'"
                      variant="outline"
                      size="sm"
                      class="h-7 px-2.5 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                      @click="confirmDelete(blog)"
                    >
                      <Trash2 class="w-3.5 h-3.5 mr-1" />
                      Delete
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>

        <div v-if="blogs.meta && blogs.meta.total" class="p-3.5 border-t border-slate-200 flex justify-end">
          <Pagination :meta="blogs.meta" @change="fetchBlogs" />
        </div>
      </section>
    </div>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
      <AlertDialogContent class="rounded border border-slate-200 bg-white">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Blog Post?</AlertDialogTitle>
          <AlertDialogDescription class="text-xs text-slate-600">
            This will permanently remove <strong class="text-slate-900">{{ blogToDelete?.title }}</strong>.
            This action cannot be undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
          <AlertDialogAction
            class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
            @click="deleteBlog"
          >
            Delete Post
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
