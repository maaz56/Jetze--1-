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
  AlertDialogTrigger,
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
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

import {
  Calendar as CalendarIcon,
  ArrowLeft,
  Search,
  RefreshCcw,
  Pencil,
  Trash2,
  Plus,
  Eye,
  X,
  Filter,
  Send,
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
  PUBLISH_BLOG, // if you have publish/unpublish action
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

// Filter states

function formatDate(date) {
  if (!date) return '';

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(new Date(date));
}

const statusFilter = ref('all'); // 'all', 'published', 'draft'
const searchQuery = ref(route.query.search || '');

// Fetch blogs with filters
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
    // The store action already shows the API error toast.
  } finally {
    isSendingBlogMail.value = false;
  }
}

function confirmDelete(blog) {
  blogToDelete.value = blog;
  showDeleteDialog.value = true;
}

function deleteBlog(id) {
  store.dispatch("blog/" + DELETE_BLOG, id)
    .then(() => {
      showDeleteDialog.value = false;
      blogToDelete.value = null;
      fetchBlogs();
    });
}

function resetFilters() {
  statusFilter.value = 'all';
  fetchBlogs();
}

onMounted(() => {
  fetchBlogs();
});
</script>

<template>
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-4">
      <Button
        @click="
          authUser?.role === 'admin'
            ? $router.push({ name: 'Dashboard' })
            : $router.back()
        "
        variant="outline"
        size="sm"
      >
        <ArrowLeft class="w-4 h-4 mr-1" />
        Back
      </Button>
      <h1 class="text-3xl font-medium leading-none tracking-tight text-gray-900">
        Blogs
      </h1>
    </div>
  </div>
  <div class="bg-white p-6 md:p-8 rounded-lg border">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
      <div class="flex flex-wrap items-center flex-1 gap-4">
        <!-- Search -->
        <div class="relative w-full max-w-sm">
          <Input
            v-model="searchQuery"
            @input="
              $router.push({
                path: $route.path,
                query: {
                  ...$route.query,
                  search: searchQuery || undefined,
                  page: undefined,
                },
              });
              fetchBlogs();
            "
            placeholder="Search by title or slug..."
            class="pl-10"
          />
          <span class="absolute inset-y-0 left-0 flex items-center pl-3">
            <Search class="h-4 w-4 text-muted-foreground" />
          </span>
        </div>

        <!-- Status Filter -->
        <Popover>
          <PopoverTrigger as-child>
            <Button variant="outline" size="sm" class="h-9 flex items-center gap-2">
              <Filter class="h-4 w-4" />
              Status: {{ statusFilter === 'all' ? 'All' : statusFilter === 'published' ? 'Published' : 'Draft' }}
            </Button>
          </PopoverTrigger>
          <PopoverContent class="w-56 p-2">
            <div class="space-y-1">
              <Button variant="ghost" size="sm" class="w-full justify-start" :class="{ 'bg-muted': statusFilter === 'all' }" @click="statusFilter = 'all'; fetchBlogs()">
                All
              </Button>
              <Button
                variant="ghost"
                size="sm"
                class="w-full justify-start"
                :class="{ 'bg-muted': statusFilter === 'published' }"
                @click="statusFilter = 'published'; fetchBlogs()"
              >
                Published
              </Button>
              <Button
                variant="ghost"
                size="sm"
                class="w-full justify-start"
                :class="{ 'bg-muted': statusFilter === 'draft' }"
                @click="statusFilter = 'draft'; fetchBlogs()"
              >
                Draft
              </Button>
            </div>
          </PopoverContent>
        </Popover>

        <!-- Clear Filters -->
        <Button
          v-if="statusFilter !== 'all' || searchQuery"
          variant="ghost"
          size="sm"
          class="h-9"
          @click="
            statusFilter = 'all';
            searchQuery = '';
            $router.push({ path: $route.path, query: { page: undefined } });
            fetchBlogs();
          "
        >
          <X class="h-4 w-4 mr-1" />
          Clear
        </Button>
      </div>

      <div v-if="authUser?.role === 'admin'" class="flex flex-wrap items-center gap-3">
        <Select v-model="mailAudience">
          <SelectTrigger class="h-9 w-[170px] bg-white">
            <SelectValue placeholder="Send to" />
          </SelectTrigger>
          <SelectContent class="bg-white">
            <SelectItem value="customers">Customers</SelectItem>
            <SelectItem value="subscribers">Subscribers</SelectItem>
            <SelectItem value="both">Both</SelectItem>
          </SelectContent>
        </Select>

        <Button
          variant="outline"
          class="h-9 flex items-center gap-2"
          :disabled="selectedBlogCount === 0 || isSendingBlogMail"
          @click="sendSelectedBlogsMail"
        >
          <Send class="h-4 w-4" />
          {{ isSendingBlogMail ? 'Queuing...' : `Send Mail (${selectedBlogCount})` }}
        </Button>

        <!-- Add New Blog Button -->
        <Button
          @click="$router.push({ name: 'NewBlog' })"
          class="flex items-center gap-2"
        >
          <Plus class="h-4 w-4" />
          Add Blog
        </Button>
      </div>
    </div>

    <!-- Loading / Empty / Content -->
    <div v-if="isLoading" class="py-24 flex items-center justify-center">
      <Spinner />
    </div>

    <div v-else-if="filteredBlogs.length === 0" class="py-16">
      <NothingFound message="No blogs found" />
    </div>

    <div v-else class="relative overflow-x-auto rounded-lg border">
      <Table>
        <TableHeader class="bg-muted/50">
          <TableRow>
            <TableHead v-if="authUser?.role === 'admin'" class="w-12">
              <Checkbox
                :checked="isAllVisibleSelected"
                aria-label="Select visible blogs"
                @update:checked="(checked) => toggleVisibleSelection(checked === true)"
              />
            </TableHead>
            <TableHead>Title</TableHead>
            <TableHead>Slug</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Author</TableHead>
            <TableHead>Published At</TableHead>
            <TableHead class="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-for="blog in filteredBlogs" :key="blog.id" class="hover:bg-gray-50/80">
            <TableCell v-if="authUser?.role === 'admin'" class="w-12">
              <Checkbox
                :checked="selectedBlogIds.includes(blog.id)"
                :aria-label="`Select ${blog.title || 'blog'}`"
                @update:checked="(checked) => setBlogSelected(blog.id, checked === true)"
              />
            </TableCell>
            <TableCell class="font-medium">
              {{ blog.title || '—' }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ blog.slug || '—' }}
            </TableCell>
            <TableCell>
              <Badge
                :variant="blog.is_published ? 'success' : 'secondary'"
                class="capitalize"
              >
                {{ blog.is_published ? 'Published' : 'Draft' }}
              </Badge>
            </TableCell>
            <TableCell>
              {{ blog.author?.name || 'Admin' }}
            </TableCell>
            <TableCell class="text-muted-foreground">
              {{ blog.published_at ? formatDate(blog.published_at) : '—' }}
            </TableCell>
            <TableCell class="text-right space-x-2">
              <Button
                variant="ghost"
                size="sm"
                @click="$router.push({ name: 'Blog', params: { id: blog.id, slug: blog.slug } })"
              >
                <Eye class="h-4 w-4 mr-1" />
                View
              </Button>

              <Button
                v-if="authUser?.role === 'admin'"
                variant="ghost"
                size="sm"
                @click="$router.push({ name: 'UpdateBlog', params: { id: blog.id } })"
              >
                <Pencil class="h-4 w-4 mr-1" />
                Edit
              </Button>

              <Button
                v-if="authUser?.role === 'admin'"
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive hover:bg-destructive/10"
                @click="confirmDelete(blog)"
              >
                <Trash2 class="h-4 w-4 mr-1" />
                Delete
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <div class="py-4 px-4 flex justify-end">
        <Pagination :meta="blogs.meta" @change="fetchBlogs" />
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Dialog -->
  <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Delete Blog Post?</AlertDialogTitle>
        <AlertDialogDescription>
          Are you sure you want to delete "{{ blogToDelete?.title }}"?
          This action cannot be undone.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction
          class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
          @click="deleteBlog(blogToDelete?.id)"
        >
          Delete
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
