<script setup>
import CountryList from "@/components/common/CountryList.json"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { ChevronsUpDownIcon, Search } from "lucide-vue-next"
import { computed, ref, watch } from "vue"
import { useStore } from 'vuex'
import Input from "../ui/input/Input.vue"

const props = defineProps({
  modelValue: {
    type: [String, Object],
    required: true
  },
  keyValue: {
    type: [String],
  },
  placeholder: {
    type: String,
    default: "Select a country"
  }
})
const emit = defineEmits(['update:modelValue']);

const selectedCountry = ref({})
const searchQuery = ref("")
const store = useStore()
const countries = ref(CountryList)
watch(() => props.modelValue, (newVal) => {
    if (typeof newVal === 'object' && newVal !== null) {
        selectedCountry.value = newVal;
    } else if (typeof newVal === 'string') {
        // If keyValue is provided, find the country object by that key
        if (props.keyValue) {
            const country = countries.value.find(c => c[props.keyValue] === newVal);
            selectedCountry.value = country || {};
        } else {
            // If no keyValue, just set the name property
            selectedCountry.value = { name: newVal };
        }
    } else {
        selectedCountry.value = {};
    }
}, { immediate: true });
const changeCountryEmit = () => {
    const country = props.keyValue ? selectedCountry.value[props.keyValue] : selectedCountry.value
  emit('update:modelValue', country)
}
const filteredCountries = computed(() => {
  if (!searchQuery.value) {
    // Show Dubai (United Arab Emirates), Pakistan, India on top, then rest up to 50
    const priorityCodes = ['ae', 'pk', 'in'];
    const priorityCountries = priorityCodes
      .map(code => countries.value.find(c => c.code === code))
      .filter(Boolean);

    // Remove priority countries from the rest
    const restCountries = countries.value.filter(
      c => !priorityCodes.includes(c.code)
    );

    // Take up to 47 from rest (since 3 priority countries)
    return [...priorityCountries, ...restCountries.slice(0, 47)];
  }
  return countries.value.filter(country =>
    country.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child class="hover:text-black">
      <Button variant="outline" class="!bg-white flex gap-2 w-full items-center h-10">
        <span class="flex items-center" v-if="selectedCountry?.name">
              <img :src="selectedCountry?.flag" class="w-[20px] h-[15px] mr-1 object-fit" :alt="`${selectedCountry.label} flag`"/>  {{ selectedCountry?.name }}
        </span>
       <span v-else>{{ placeholder }}</span>
       <ChevronsUpDownIcon :size="14" class="" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent class="w-56 ">
      <DropdownMenuLabel>
    <div class="relative">
      <Input v-model="searchQuery" class="pl-10 h-8" placeholder="Search country..." />
      <Search :size="16" class="absolute top-1/2 left-3 transform -translate-y-1/2 text-gray-400" />
    </div>
      </DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuRadioGroup class="h-40 overflow-auto" v-model="selectedCountry" @update:model-value="changeCountryEmit()" >
        <template v-if="filteredCountries.length">
          <DropdownMenuRadioItem class="flex items-center" v-for="country in filteredCountries" :key="country.id" :value="country">
            <img :src="country.flag" class="w-[20px] h-[15px] mr-1 object-fit" :alt="`${country.label} flag`"/>  {{ country.name }}
          </DropdownMenuRadioItem>
        </template>
        <template v-else>
          <DropdownMenuRadioItem value="no-results" disabled>No countries found</DropdownMenuRadioItem>
        </template>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>