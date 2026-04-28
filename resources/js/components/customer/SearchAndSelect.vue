<script setup>
import { computed, ref, watch } from "vue";
import { Search, Check, ChevronDown, X } from "lucide-vue-next";
import { onClickOutside } from "@vueuse/core"; // Optional, but helpful for dropdown handling
import { useUserStore } from "@/services/stores/user";
import {FETCH_AGENT_DATA} from "@/services/store/actions.type";

import { useStore } from "vuex";

// Props and emits
const props = defineProps({
  users: {
    type: Array,
    required: true,
    default: () => []
  },
  multiple: {
    type: Boolean,
    default: false
  },
  placeholder: {
    type: String,
    default: "Search users..."
  }
});

const emit = defineEmits(['update:selected']);
const userStore = useUserStore();
const store = useStore();

const users = computed(() => userStore.users);


// State
const searchQuery = ref('');
const isDropdownOpen = ref(false);
const selectedUsers = ref([]);
const dropdownRef = ref(null);
// //console.log(users);
// Computed
const filteredUsers = computed(() => {
  

  if (!searchQuery.value) return props.users;
  
  const query = searchQuery.value.toLowerCase();
  //console.log(props.users);
  return props.users.filter(user => 
    user.name?.toLowerCase().includes(query) || 
    user.email?.toLowerCase().includes(query) 
  );
});

// Methods
const toggleDropdown = () => {
  isDropdownOpen.value = !isDropdownOpen.value;
};

const closeDropdown = () => {
  isDropdownOpen.value = false;
};

const selectUser = (user) => {
  if (props.multiple) {
    const index = selectedUsers.value.findIndex(u => u.id === user.id);
    if (index === -1) {
      selectedUsers.value.push(user);
    } else {
      selectedUsers.value.splice(index, 1);
    }
  } else {
    selectedUsers.value = [user];
    closeDropdown();
  }
  
  emit('update:selected', props.multiple ? selectedUsers.value : selectedUsers.value[0]);
};

const isSelected = (user) => {
  return selectedUsers.value.some(u => u.id === user.id);
};

const removeUser = (user) => {
  const index = selectedUsers.value.findIndex(u => u.id === user.id);
  if (index !== -1) {
    selectedUsers.value.splice(index, 1);
    emit('update:selected', props.multiple ? selectedUsers.value : null);
  }
};

const clearSelection = () => {
  selectedUsers.value = [];
  emit('update:selected', props.multiple ? [] : null);
};

// Handle outside clicks to close dropdown
if (typeof window !== 'undefined') {
  onClickOutside(dropdownRef, closeDropdown);
}

// Watch for changes in search query
watch(searchQuery, () => {
  if (!isDropdownOpen.value && searchQuery.value) {
    isDropdownOpen.value = true;
  }
});
</script>

<template>
  <div class="relative w-full" ref="dropdownRef">
    <!-- Search input -->

    <div class="relative">
      <div class="flex items-center border rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-primary-500 focus-within:border-primary-500">
        <!-- Selected users (for multiple select) -->
        <div v-if="props.multiple && users?.length > 0" class="flex flex-wrap gap-1 p-1 pl-2">
          <div 
            v-for="user in users" 
            :key="user.id" 
            class="flex items-center gap-1 bg-primary-100 text-primary-800 px-2 py-1 rounded-md text-sm"
          >
            <span>{{  user.email }}</span>
            <button 
              type="button" 
              @click.stop="removeUser(user)" 
              class="text-primary-600 hover:text-primary-800"
            >
              <X class="w-3 h-3" />
            </button>
          </div>
        </div>
        
        <!-- Single selected user display -->
        <div v-else-if="!props.multiple && users?.length > 0" class="flex items-center justify-between flex-1 px-3 py-2">
          <span>{{ users[0].name || users[0].username || users[0].email }}</span>
          <button 
            type="button" 
            @click.stop="clearSelection()" 
            class="text-gray-400 hover:text-gray-600"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
        
        <!-- Input field -->
        <div class="flex-1 flex items-center">
          <Search class="w-4 h-4 text-gray-400 ml-3" v-if="!selectedUsers.length || props.multiple" />
          <input
            v-model="searchQuery"
            type="text"
            class="w-full py-2 px-3 outline-none bg-transparent"
            :placeholder="selectedUsers.length && !props.multiple ? '' : placeholder"
            @focus="isDropdownOpen = true"
          />
        </div>
        
        <!-- Dropdown toggle button -->
        <button 
          type="button" 
          @click="toggleDropdown" 
          class="p-2 text-gray-400 hover:text-gray-600"
        >
          <ChevronDown class="w-5 h-5" :class="{ 'transform rotate-180': isDropdownOpen }" />
        </button>
      </div>
    </div>
    
    <!-- Dropdown menu -->
    <div 
      v-if="isDropdownOpen" 
      class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto"
    >
      <div v-if="filteredUsers?.length === 0" class="p-3 text-gray-500 text-center">
        No users found
      </div>
      
      <ul v-else>
        <li 
          v-for="user in filteredUsers" 
          :key="user.id" 
          @click="selectUser(user)"
          class="px-3 py-2 cursor-pointer hover:bg-gray-100 flex items-center justify-between"
          :class="{ 'bg-primary-50': isSelected(user) }"
        >
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-600 flex-shrink-0">
              {{ (user.name || user.username || '').charAt(0).toUpperCase() }}
            </div>
            <div>
              <div class="font-medium">{{ user.name || user.username }}</div>
              <div class="text-sm text-gray-500">{{ user.email }}</div>
            </div>
          </div>
          
          <Check v-if="isSelected(user)" class="w-5 h-5 text-primary-600" />
        </li>
      </ul>
    </div>
  </div>
</template>