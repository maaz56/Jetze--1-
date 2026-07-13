<template>
  <div class="max-w-2xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <!-- Header -->
    <h2 class="text-2xl font-semibold text-gray-900 mb-4">New Top Airline</h2>

    <div class="space-y-4">
      <!-- Name -->
      <div>
        <label class="form-label">Airline Name *</label>
        <input class="form-input" v-model="form.name" placeholder="Enter airline name" />
        <p v-if="errors.name" class="text-red-500 text-xs mt-1">
          {{ errors.name }}
        </p>
      </div>

      <!-- Airline Type -->
      <div>
        <label class="form-label">Airline Type *</label>
        <Select v-model="form.type">
          <SelectTrigger class="form-input">
            <SelectValue placeholder="Select airline type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="domestic">Domestic</SelectItem>
            <SelectItem value="international">International</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.type" class="text-red-500 text-xs mt-1">
          {{ errors.type }}
        </p>
      </div>

      <!-- Image -->
      <div>
        <label class="form-label">Airline Logo</label>
        <label
          class="flex items-center justify-between h-[42px] px-3 border border-dashed rounded-md cursor-pointer text-sm text-gray-700 hover:bg-gray-50"
        >
          <span>{{ form.image ? form.image.name : "+ Choose Logo" }}</span>
          <input type="file" class="hidden" @change="onImageChange" />
        </label>
        <p v-if="errors.image" class="text-red-500 text-xs mt-1">
          {{ errors.image }}
        </p>
      </div>

      <!-- Is Active -->
      <div class="flex items-center gap-2">
          <input type="checkbox" id="is_active" v-model="form.is_active" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" />
          <label for="is_active" class="text-sm font-medium text-gray-700">Is Active</label>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-4 pt-4">
        <Button
          @click="submit"
          class="px-6 py-2"
        >
          Save
        </Button>
        <Button
          variant="outline"
          @click="router.push({ name: 'TopAirlines' })"
          class="px-6 py-2"
        >
          Cancel
        </Button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { SAVE_TOP_AIRLINE } from "@/services/store/actions.type";

const router = useRouter();
const store = useStore();

const form = reactive({
  name: "",
  type: null,
  image: null,
  is_active: true,
});

const errors = reactive({});

const clearErrors = () => Object.keys(errors).forEach(k => errors[k] = null);

const applyServerErrors = (error) => {
  const serverErrors = error?.response?.data?.errors || {};
  Object.keys(serverErrors).forEach((key) => {
    errors[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
  });
};

const onImageChange = (e) => {
  form.image = e.target.files[0];
};

const validate = () => {
  clearErrors();
  let valid = true;
  if (!form.name) { errors.name = "Name is required"; valid = false; }
  if (!form.type) { errors.type = "Type is required"; valid = false; }
  return valid;
};

const submit = () => {
  if (!validate()) return;
  
  const payload = new FormData();
  payload.append('name', form.name);
  payload.append('type', form.type);
  payload.append('is_active', form.is_active ? 1 : 0);
  if (form.image) {
    payload.append('image', form.image);
  }

  store.dispatch("cms/" + SAVE_TOP_AIRLINE, payload)
    .then(() => {
      toast.success("Top airline created successfully");
      router.push({ name: 'TopAirlines' });
    })
    .catch((error) => {
      applyServerErrors(error);
      toast.error(error?.response?.data?.message || "Failed to create top airline");
    });
};
</script>

<style scoped>
.form-label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
  @apply w-full h-[42px] px-3 border border-gray-300 rounded-md
         focus:outline-none focus:ring-1 focus:ring-primary
         focus:border-primary text-sm;
}
</style>
