<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { File as FileIcon, Upload, X } from "lucide-vue-next";
import { ref, watch } from "vue";

const props = defineProps({
  accept: { type: String, default: "image/*" }, // e.g., "image/*,application/pdf"
  maxSize: { type: Number, default: 2 }, // in MB
  multiple: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  modelValue: { type: Array as () => File[], default: () => [] }
});

const emit = defineEmits(["update:modelValue"]);

const previews = ref<(string | { type: "file"; name: string })[]>([]);
const errorMessage = ref("");
const fileInput = ref<HTMLElement>()

watch(
  () => props.modelValue,
  (newFiles) => {
    previews.value = newFiles.map((file) =>
      file.type.startsWith("image/")
        ? URL.createObjectURL(file)
        : { type: "file", name: file.name }
    );
  },
  { immediate: true }
);

const handleFiles = (files: FileList | null) => {
  if (!files) return;
  errorMessage.value = "";

  const validFiles: File[] = [];
  const newPreviews: (string | { type: "file"; name: string })[] = [];

  Array.from(files).forEach((file) => {
    const acceptedTypes = props.accept.split(",").map((t) => t.trim());
    if (!acceptedTypes.some((type) => file.type.match(type))) {
      errorMessage.value = `File "${file.name}" is not an accepted type.`;
      return;
    }
    if (file.size > props.maxSize * 1024 * 1024) {
      errorMessage.value = `File "${file.name}" exceeds the size limit of ${props.maxSize} MB.`;
      return;
    }

    validFiles.push(file);

    if (file.type.startsWith("image/")) {
      newPreviews.push(URL.createObjectURL(file));
    } else {
      newPreviews.push({ type: "file", name: file.name });
    }
  });

  if (props.multiple) {
    emit("update:modelValue", [...props.modelValue, ...validFiles]);
    previews.value.push(...newPreviews);
  } else {
    emit("update:modelValue", validFiles);
    previews.value = newPreviews;
  }
};

const removeFile = (index: number) => {
  const updatedFiles = [...props.modelValue];
  updatedFiles.splice(index, 1);
  emit("update:modelValue", updatedFiles);
  previews.value.splice(index, 1);
};
</script>

<template>
  <div class="space-y-2">
    <!-- Drag & Drop Section -->
    <div
      v-if="!previews.length"
      class="border-2 border-dashed border-muted rounded-lg p-6 text-center cursor-pointer hover:bg-muted/20 transition"
      @click="fileInput.click()"
    >
      <Upload class="mx-auto w-10 h-10 text-muted-foreground" />
      <p class="mt-2 text-sm text-muted-foreground">
        Drag & drop or click to upload
      </p>
      <p class="text-xs text-muted-foreground">
        Accepted: {{ accept }} | Max size: {{ maxSize }} MB
      </p>
    </div>

    <!-- Preview Section -->
    <div
      v-if="previews.length"
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"
    >
      <Card
        v-for="(item, index) in previews"
        :key="index"
        class="relative group overflow-hidden"
      >
        <CardContent
          class="p-0 flex flex-col items-center justify-center h-40 bg-muted"
        >
          <template v-if="typeof item === 'string'">
            <img
              :src="item"
              alt="Preview"
              class="w-full h-40 object-cover rounded-lg"
            />
          </template>
          <template v-else>
            <FileIcon class="w-10 h-10 text-primary mb-2" />
            <p class="text-xs truncate w-full text-center px-2">
              {{ item.name }}
            </p>
          </template>

          <div
            class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition"
          >
            <Button
              variant="destructive"
              size="icon"
              @click.stop="removeFile(index)"
            >
              <X class="w-4 h-4" />
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Error Message -->
    <p v-if="errorMessage" class="text-sm text-red-500">
      {{ errorMessage }}
    </p>

    <!-- Hidden File Input -->
    <input
      ref="fileInput"
      type="file"
      class="hidden"
      :accept="accept"
      :multiple="multiple"
      @change="(e: Event) => handleFiles((e.target as HTMLInputElement).files)"
    />
  </div>
</template>
