<template>
  <div class="max-w-6xl mx-auto p-6 bg-white rounded-lg shadow-sm">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Traveler </h2>

      <!-- Traveller Type Selection -->
      <div class="w-48">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Traveler Type<span class="text-red-500">*</span>
        </label>
        <div class="relative">
           <Select v-model="traveler.type">
            <SelectTrigger class="bg-white">
              <SelectValue placeholder="Select Traveler Type" />
            </SelectTrigger>
            <SelectContent class="bg-white">
              <SelectGroup>
             <SelectItem value="ADT">Adult (ADT)</SelectItem>
            <SelectItem value="CHD">Child (CHD)</SelectItem>
            <SelectItem value="INF">Infant (INF)</SelectItem>
      </SelectGroup>
    </SelectContent>
  </Select>

        </div>
        <p v-if="errors.type" class="text-red-500 text-sm mt-1">{{ errors.type }}</p>
      </div>
    </div>



    <!-- First Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <!-- Title -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Title<span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <Select v-model="traveler.title">
            <SelectTrigger class="bg-white">
              <SelectValue placeholder="Select title" />
            </SelectTrigger>
            <SelectContent class="bg-white">
              <SelectGroup>
             <SelectItem value="Mr">Mr</SelectItem>
                                                                <SelectItem value="Mrs">Mrs</SelectItem>
                                                                <SelectItem value="Ms">Ms</SelectItem>
                                                                <SelectItem value="Miss">Miss</SelectItem>
                                                                <SelectItem value="Mstr">Mstr</SelectItem>
      </SelectGroup>
    </SelectContent>
  </Select>
        
        </div>
        <p v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</p>
      </div>

      <!-- First Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          First Name<span class="text-red-500">*</span>
        </label>
        <Input type="text" v-model="traveler.firstName" placeholder="Enter first name"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 py-2 px-3"
          :class="{ 'border-red-500': errors.firstName }"/>
        <p v-if="errors.firstName" class="text-red-500 text-sm mt-1">{{ errors.firstName }}</p>
      </div>

      <!-- Last Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Last Name<span class="text-red-500">*</span>
        </label>
        <Input type="text" v-model="traveler.lastName" placeholder="Enter last name"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 py-2 px-3"
          :class="{ 'border-red-500': errors.lastName }"/>
        <p v-if="errors.lastName" class="text-red-500 text-sm mt-1">{{ errors.lastName }}</p>
      </div>

      <!-- Date of Birth -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Date of birth <span class="text-red-500">*{{ ageRequirementText }}</span>
        </label>
        <Calender  v-model="traveler.dateOfBirth" :max="maxDateOfBirth" ></Calender>
        <p v-if="errors.dateOfBirth" class="text-red-500 text-sm mt-1">{{ errors.dateOfBirth }}</p>
      </div>
    </div>

    <!-- Second Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <!-- Nationality -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Nationality<span class="text-red-500">*</span>
        </label>
         <CountryDropdown class="w-full" v-model="traveler.nationality" @update:model-value="traveler.issueCountry=$event" :placeholder="'Nationality'" :key-value="'code'" />
        <p v-if="errors.nationality" class="text-red-500 text-sm mt-1">{{ errors.nationality }}</p>
      </div>
          <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Gender<span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <Select  v-model="traveler.gender" >
    <SelectTrigger class="bg-white">
      <SelectValue  placeholder="Select Gender" />
    </SelectTrigger>
    <SelectContent class="bg-white">
      <SelectGroup>
            <SelectItem value="M">Male</SelectItem>
            <SelectItem value="F">Female</SelectItem>

      </SelectGroup>
    </SelectContent>
  </Select>
        </div>
       <p v-if="errors.gender" class="text-red-500 text-sm mt-1">{{ errors.gender }}</p>
      </div>
      </div>
      <h1 class="py-3 mb-6 border-b border-primary">Document Detail</h1>
 <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <!-- Doc Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Doc Type<span class="text-red-500">*</span>
        </label>
        <div class="relative">
          <Select  v-model="traveler.docType">
    <SelectTrigger class="bg-white">
      <SelectValue  placeholder="Document Type" />
    </SelectTrigger>
    <SelectContent class="bg-white">
      <SelectGroup>
            <SelectItem value="passport">Passport</SelectItem>
            <!-- <SelectItem value="ID Card">ID Card</SelectItem>
            <SelectItem value="Driver's License">Driver's License</SelectItem>
            <SelectItem value="Birth Certificate">Birth Certificate</SelectItem> -->
      </SelectGroup>
    </SelectContent>
  </Select>
        </div>
        <p v-if="errors.docType" class="text-red-500 text-sm mt-1">{{ errors.docType }}</p>
      </div>

      <!-- Document No -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Document No<span class="text-red-500">*</span>
        </label>
        <Input type="text" v-model="traveler.documentNo" placeholder="Enter document number"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500 py-2 px-3"
          :class="{ 'border-red-500': errors.documentNo }"/>
        <p v-if="errors.documentNo" class="text-red-500 text-sm mt-1">{{ errors.documentNo }}</p>
      </div>

      <!-- Expiry Date -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Expiry Date<span class="text-red-500">*</span>
        </label>
        <div class="relative">
            <Calender  v-model="traveler.expiryDate" ></Calender>
        </div>
        <p v-if="errors.expiryDate" class="text-red-500 text-sm mt-1">{{ errors.expiryDate }}</p>
      </div>
      <div class="">
         <div>
         <label class="block text-sm font-medium text-gray-700 mb-1">
           Issue Country<span class="text-red-500">*</span>
         </label>
         <CountryDropdown v-model="traveler.issueCountry" :placeholder="'Issue Country'" :key-value="'code'" />
       </div>
     
      <p v-if="errors.issueCountry" class="text-red-500 text-sm mt-1">{{ errors.issueCountry }}</p>
      </div>
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end">
      <button @click="submitForm"
        class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 flex items-center"
        :disabled="isSubmitting">
        <Loader2 v-if="isSubmitting" class="h-4 w-4 mr-2 animate-spin" />
        <span>{{ isSubmitting ? 'Saving...' : 'Save Traveller' }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import Calender from '@/components/common/Calender.vue';
import CountryDropdown from '@/components/common/CountryDropdown.vue';
import { Input } from '@/components/ui/input';

import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue
} from "@/components/ui/select";
import { SAVE_TRAVELLER } from "@/services/store/actions.type";
import { Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useStore } from 'vuex';
const store = useStore();
const traveler = ref({
  issueCountry:'',
  nationality: '',
});
const isSubmitting = ref(false);

const errors = ref({});

// watch(() => traveler.value.type, (newType) => {
//   // validateDateOfBirth();
// });

const ageRequirementText = computed(() => {
  switch (traveler.value.type) {
    case 'ADT': return 'Age Should be 12+';
    case 'CHD': return 'Age Should be 2-12';
    case 'INF': return 'Age Should be <2';
    default: return '';
  }
});

const today = computed(() => new Date().toISOString().split('T')[0]);

const maxDateOfBirth = computed(() => {
  const date = new Date();
  if (traveler.value.type === 'ADT') date.setFullYear(date.getFullYear() - 12);
  else if (traveler.value.type === 'CHD') date.setFullYear(date.getFullYear() - 2);
  return date.toISOString().split('T')[0];
});

const validateForm = () => {
  errors.value = {}; // Reset errors

  if (!traveler.value.type) errors.value.type = 'Traveller type is required';
  if (!traveler.value.gender) errors.value.gender = 'Gender is required';
  if (!traveler.value.title) errors.value.title = 'Title is required';
  if (!traveler.value.firstName.trim()) errors.value.firstName = 'First name is required';
  if (!traveler.value.lastName.trim()) errors.value.lastName = 'Last name is required';
  if (!traveler.value.dateOfBirth) errors.value.dateOfBirth = 'Date of Birth is required';
  if (!traveler.value.nationality) errors.value.nationality = 'Nationality is required';
  if (!traveler.value.docType) errors.value.docType = 'Document type is required';
  if (!traveler.value.documentNo) errors.value.documentNo = 'Document number is required';
  if (!traveler.value.expiryDate) errors.value.expiryDate = 'Expiry date is required';
  if (!traveler.value.issueCountry) errors.value.issueCountry = 'Issue country is required';

  return Object.keys(errors.value).length === 0;
};

function submitForm() {
  if (!validateForm()) {
    emit('validate', errors.value);
    return;
  }
  isSubmitting.value = true;
  try {
    //console.log('Traveller:', traveler.value);
    store.dispatch('traveller/' + SAVE_TRAVELLER, traveler.value);
  } catch (error) {

  } finally {
    isSubmitting.value = false;

  }
};
</script>


<style scoped>
/* Add any additional custom styles here */
Input[type="date"]::-webkit-calendar-picker-indicator {
  opacity: 0;
  position: absolute;
  right: 0;
  top: 0;
  width: 100%;
  height: 100%;
  cursor: pointer;
}
</style>