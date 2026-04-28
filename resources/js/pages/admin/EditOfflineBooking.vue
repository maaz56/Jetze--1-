<template>
    <div class="min-h-screen py-8 px-4 sm:px-6 lg:px-8">
        <div v-if="isLoading" class="flex items-center justify-center md:container h-[50vh] bg-white rounded-lg">
            <Spinner />
        </div>
        <div v-else class="mx-auto bg-white rounded-lg border p-6 sm:p-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Offline Booking</h1>
            <!-- Agent Selection Section -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Agent Selection</h2>
                <label for="agent" class="block text-sm font-medium text-gray-700 mb-1">Select Agent</label>
                <Popover v-model:open="isOpen">
                    <PopoverTrigger
                        class="w-1/3 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        {{ selectedAgent ? selectedAgent.email : 'Select an agent' }}
                    </PopoverTrigger>
                    <PopoverContent class="w-[300px] p-0">
                        <Command>
                            <CommandInput placeholder="Search agents..." v-model="searchQuery" class="h-9" />
                            <CommandList>
                                <CommandEmpty>No agents found.</CommandEmpty>
                                <CommandGroup>
                                    <CommandItem v-for="user in filteredUsers" :key="user.id" :value="user.email"
                                        @select="() => { selectAgent(user); isOpen = false; }" class="cursor-pointer">
                                        {{ user.email }} ({{ user.role }})
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>

            <!-- Travel Route Selection Section -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Travel Route Selection</h2>
                <div class="flex justify-between">
                    <Tabs v-model="localValue.flightType" class="w-full">
                        <TabsList class="grid grid-cols-3 mb-4 bg-white border justify-self-start w-full md:w-fit">
                            <TabsTrigger value="one-way">One Way</TabsTrigger>
                            <TabsTrigger value="return">Return</TabsTrigger>
                            <TabsTrigger value="multi-city">Multi-City</TabsTrigger>
                        </TabsList>
                    </Tabs>
                    <div class="flex gap-3 items-center">
                        <Select v-model="localValue.classType" class="min-w-[140px]">
                            <SelectTrigger class="gap-2 bg-white text-xs sm:text-sm w-full sm:w-40">
                                <SelectValue placeholder="Cabin Class" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Y">{{ $t('economy') }}</SelectItem>
                                <SelectItem value="S">{{ $t('premium_economy') }}</SelectItem>
                                <SelectItem value="C">{{ $t('business') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" class="text-xs sm:text-sm bg-white">
                                    {{ totalTravelers }} Traveler{{ totalTravelers > 1 ? 's' : '' }}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64 p-4">
                                <div class="space-y-4">
                                    <div>
                                        <Label for="adult-field">Adult</Label>
                                        <NumberField id="adult-field" v-model="localValue.adult" :min="1"
                                            :max="maxAdults" @update:modelValue="handleAdultChange">
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </div>
                                    <div>
                                        <Label for="child-field">Child</Label>
                                        <NumberField id="child-field" v-model="localValue.child" :min="0"
                                            :max="maxChildren" @update:modelValue="handleChildChange">
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </div>
                                    <div>
                                        <Label for="infant-field">Infant</Label>
                                        <NumberField id="infant-field" v-model="localValue.infant" :min="0"
                                            :max="maxInfants" @update:modelValue="handleInfantChange">
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </div>
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>

                <div class="gap-2 grid grid-cols-1 md:grid-cols-3"
                    v-if="localValue.flightType === 'one-way' || localValue.flightType === 'return'">
                    <div class="w-full">
                        <Autocomplete v-model="localValue.origin" placeholder="Origin" :source="airports"
                            :icon="'PlaneTakeoff'" />
                        <div v-if="errors.origin" class="text-destructive mt-1 text-xs">{{ errors.origin }}</div>
                    </div>
                    <div class="w-full">
                        <Autocomplete v-model="localValue.destination" placeholder="Destination" :source="airports"
                            :icon="'PlaneLanding'" />
                        <div v-if="errors.destination" class="text-destructive mt-1 text-xs">{{ errors.destination }}
                        </div>
                    </div>
                    <div class="w-full" v-show="localValue.flightType === 'one-way'">
                        <Calender v-model="localValue.dateRange.start" :minValue="todayDate" />
                        <div v-if="errors.start" class="text-destructive mt-1 text-xs">{{ errors.start }}</div>
                    </div>
                    <div class="w-full" v-show="localValue.flightType === 'return'">
                        <DateRangePicker :minValue="todayDate" v-model="localValue.dateRange" />
                        <div v-if="errors.start" class="text-destructive mt-1 text-xs">{{ errors.start }}</div>
                        <div v-if="errors.end" class="text-destructive mt-1 text-xs">{{ errors.end }}</div>
                    </div>
                </div>
                <div v-else class="flex gap-2 flex-col">
                    <div v-for="(trip, index) in localValue.multiCityTrips" :key="index"
                        class="grid grid-cols-1 sm:grid-cols-4 gap-2 sm:gap-3">
                        <div class="w-full">
                            <Autocomplete v-model="trip.origin" :label="`From (Trip ${index + 1})`"
                                :placeholder="$t('origin')" :source="airports" class="w-full" />
                            <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].origin"
                                class="text-destructive mt-1 text-xs">
                                {{ errors.multiCityTrips[index].origin }}
                            </div>
                        </div>
                        <div class="w-full">
                            <Autocomplete v-model="trip.destination" :label="`To (Trip ${index + 1})`"
                                :placeholder="$t('destination')" :icon="'PlaneLanding'" :source="airports"
                                class="w-full" />
                            <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].destination"
                                class="text-destructive mt-1 text-xs">
                                {{ errors.multiCityTrips[index].destination }}
                            </div>
                        </div>
                        <div class="w-full">
                            <Calender v-model="trip.date"
                                :minValue="index === 0 ? todayDate : localValue.multiCityTrips[index - 1]?.date || todayDate"
                                class="w-full" />
                            <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].date"
                                class="text-destructive mt-1 text-xs">
                                {{ errors.multiCityTrips[index].date }}
                            </div>
                        </div>
                        <Button v-if="index >= 2" @click="removeTrip(index)"
                            class="text-background h-10 bg-red-500 hover:bg-red-600 w-full sm:w-auto">
                            Remove
                        </Button>
                    </div>
                    <div class="flex justify-start">
                        <Button variant="link" @click="addTrip">Add Trip</Button>
                    </div>
                </div>
            </div>

            <!-- Travellers Section -->
            <div class="w-full mx-auto mt-1 bg-white shadow-sm border-gray-200">
                <div class="p-4 sm:p-6 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white">
                    <p class="text-lg sm:text-xl font-semibold text-gray-800">
                        Traveller Details
                        <span class="text-sm font-normal text-gray-600 block sm:inline mt-2 sm:mt-0 sm:ml-2">
                            (Use all given names and surnames exactly as they appear on your passport/ID to avoid
                            complications.)
                        </span>
                    </p>
                    <div v-if="countdown !== null || countdown == '0'"
                        class="border bg-white py-1.5 px-2 rounded-md text-primary text-xs sm:text-sm flex items-center h-10">
                        {{ countdown }}
                    </div>
                </div>

                <div v-for="(traveller, index) in travellers" :key="`traveller-${index}`"
                    class="border border-gray-100 mt-2 last:border-b-0">
                    <Accordion type="single" collapsible class="mb-0">
                        <AccordionItem :value="`traveller-${index}`" class="border-0">
                            <AccordionTrigger
                                class="p-4 border bg-gray-100 hover:bg-gray-50 transition-colors duration-200 rounded-none"
                                :aria-expanded="index === 0 ? 'true' : undefined">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                            <span class="text-primary font-semibold text-sm">{{ index + 1 }}</span>
                                        </div>
                                        <h3 class="text-lg sm:text-xl font-semibold text-gray-800">
                                            {{ traveller.type }} Traveller {{ index + 1 }}
                                        </h3>
                                    </div>

                                    <button v-if="canRemoveTraveller" @click.stop="removeTraveller(index)"
                                        class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-destructive h-8 px-3 text-destructive hover:bg-destructive/10">
                                        <Trash2 class="w-4 h-4 me-2" />
                                        Delete
                                    </button>
                                </div>
                            </AccordionTrigger>

                            <AccordionContent class="bg-white border-0 p-0">
                                <!-- Gender Selection -->
                                <div class="flex justify-between items-center px-4 sm:px-6 py-4">
                                    <div class="bg-gray-50/50 border-b border-gray-100">
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                            <span class="text-sm font-medium text-gray-700">Gender <span
                                                    class="text-red-500">*</span></span>
                                            <RadioGroup class="flex flex-row gap-6" default-value="comfortable"
                                                :orientation="'horizontal'" v-model="traveller.gender"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.gender`) }">
                                                <div class="flex items-center space-x-2">
                                                    <RadioGroupItem id="male" value="M" class="border-gray-300" />
                                                    <Label for="male"
                                                        class="text-sm font-medium text-gray-700">Male</Label>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <RadioGroupItem id="female" value="F" class="border-gray-300" />
                                                    <Label for="female"
                                                        class="text-sm font-medium text-gray-700">Female</Label>
                                                </div>
                                            </RadioGroup>
                                        </div>
                                        <div v-if="getErrorPath(`travellers.${index}.gender`)"
                                            class="error-message text-xs mt-2 text-red-500">
                                            {{ getErrorPath(`travellers.${index}.gender`) }}
                                        </div>
                                    </div>
                                    <Select v-if="agentTravellers.length > 0"
                                        @update:modelValue="handleSelectedTravellerAgentChange($event, index)">
                                        <SelectTrigger
                                            class="h-10 text-sm bg-white w-[200px] border-gray-200 focus:border-primary focus:ring-primary/20">
                                            <SelectValue placeholder="Select Traveller" />
                                        </SelectTrigger>
                                        <SelectContent class="bg-white border-gray-200 w-[200px]">
                                            <SelectGroup>
                                                <SelectItem :value="index"
                                                    v-for="(agentTraveller, index) in agentTravellers"
                                                    :key="agentTraveller.id">
                                                    {{ agentTraveller.title }} {{ agentTraveller.first_name }} {{
                                                    agentTraveller.last_name }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Personal Information -->
                                <div class="p-4 sm:p-6">
                                    <h4 class="text-base font-semibold text-gray-800 mb-4">Personal Information</h4>

                                    <!-- First Grid: Personal Details -->
                                    <div
                                        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                                        <div class="space-y-2">
                                            <Label :for="`title-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                Title <span class="text-red-500">*</span>
                                            </Label>
                                            <Select v-model="traveller.title" :id="`title-${index}`"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.title`) }">
                                                <SelectTrigger
                                                    class="h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20">
                                                    <SelectValue placeholder="Select title" />
                                                </SelectTrigger>
                                                <SelectContent class="bg-white border-gray-200">
                                                    <SelectGroup>
                                                        <SelectItem value="Mr">Mr</SelectItem>
                                                        <SelectItem value="Mrs">Mrs</SelectItem>
                                                        <SelectItem value="Ms">Ms</SelectItem>
                                                        <SelectItem value="Miss">Miss</SelectItem>
                                                        <SelectItem value="Mstr">Mstr</SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <div v-if="getErrorPath(`travellers.${index}.title`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.title`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label :for="`first-name-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                First Name <span class="text-red-500">*</span>
                                            </Label>
                                            <Input v-model="traveller.firstName" :id="`first-name-${index}`" type="text"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.firstName`) }"
                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                placeholder="Enter first name"
                                                @input="traveller.firstName = $event.target.value.toUpperCase()" />
                                            <div v-if="getErrorPath(`travellers.${index}.firstName`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.firstName`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label :for="`last-name-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                Last Name <span class="text-red-500">*</span>
                                            </Label>
                                            <Input v-model="traveller.lastName" :id="`last-name-${index}`" type="text"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.lastName`) }"
                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                placeholder="Enter last name"
                                                @input="traveller.lastName = $event.target.value.toUpperCase()" />
                                            <div v-if="getErrorPath(`travellers.${index}.lastName`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.lastName`) }}
                                            </div>
                                        </div>

                                        <div class="">
                                            <Label :for="`date-of-birth-${index}`"
                                                class="text-sm font-medium text-gray-700">
                                                D.O.B
                                                <span v-if="traveller.type == 'ADT'"
                                                    class="text-xs text-red-500 font-normal">
                                                    *Age Should be 12+
                                                </span>
                                                <span v-else-if="traveller.type == 'CNN'"
                                                    class="text-xs text-red-500 font-normal">
                                                    *Age 2 to 12 years
                                                </span>
                                                <span v-if="traveller.type == 'INF'"
                                                    class="text-xs text-red-500 font-normal">
                                                    *Age Less than 2
                                                </span>
                                            </Label>
                                            <Calender v-model="traveller.dob" type="date" :id="`date-of-birth-${index}`"
                                                placeholder="Date Of Birth"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.dob`) }"
                                                class="w-full h-8 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                            <div v-if="getErrorPath(`travellers.${index}.dob`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.dob`) }}
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <Label :for="`nationality-${index}`"
                                                class="block text-sm font-medium text-gray-700 mb-1">
                                                Nationality<span class="required">*</span>
                                            </Label>
                                            <CountryDropdown :keyValue="'code'" placeholder="SELECT NATIONALITY"
                                                v-model="traveller.nationality"
                                                @update:modelValue="(value) => traveller.issueCountry = value" />
                                            <div v-if="getErrorPath(`travellers.${index}.nationality`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.nationality`) }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Document Information -->
                                    <h4 class="text-base font-semibold text-gray-800 mb-4">Document Information</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div class="space-y-2">
                                            <Label :for="`document-type-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                Document Type <span class="text-red-500">*</span>
                                            </Label>
                                            <Select v-model="traveller.documentType" :id="`document-type-${index}`"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.documentType`) }">
                                                <SelectTrigger
                                                    class="h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20">
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                                <SelectContent class="bg-white border-gray-200">
                                                    <SelectGroup>
                                                        <SelectItem value="passport">Passport</SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <div v-if="getErrorPath(`travellers.${index}.documentType`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.documentType`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label :for="`document-no-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                Document Number <span class="text-red-500">*</span>
                                            </Label>
                                            <Input v-model="traveller.documentNo" :id="`document-no-${index}`"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.documentNo`) }"
                                                type="text"
                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                placeholder="Enter document number" />
                                            <div v-if="getErrorPath(`travellers.${index}.documentNo`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.documentNo`) }}
                                            </div>
                                        </div>
                                        <div class="">
                                            <Label :for="`expiry-date-${index}`"
                                                class="block text-sm font-medium text-gray-700">
                                                Expiry Date <span class="text-red-500">*</span>
                                            </Label>
                                            <Calender v-model="traveller.expiryDate" :id="`expiry-date-${index}`"
                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.expiryDate`) }"
                                                type="date"
                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                            <div v-if="getErrorPath(`travellers.${index}.expiryDate`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.expiryDate`) }}
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <Label :for="`issue-country-${index}`"
                                                class="block text-sm font-medium text-gray-700 mb-1">
                                                Issue Country<span class="required">*</span>
                                            </Label>
                                            <CountryDropdown :keyValue="'code'" placeholder="SELECT ISSUE COUNTRY"
                                                v-model="traveller.issueCountry" />
                                            <div v-if="getErrorPath(`travellers.${index}.issueCountry`)"
                                                class="error-message text-xs text-red-500">
                                                {{ getErrorPath(`travellers.${index}.issueCountry`) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>
                <button type="button" @click="addTraveller" class="mt-2 px-4 py-2 bg-primary text-white rounded-md">Add
                    Traveller</button>
            </div>
            <div class="flex items-center justify-between mt-6">
                    <div class="text-gray-700 font-medium">
                        PNR
                        <div class="text-xs text-gray-500">Enter PNR</div>
                    </div>
                    <div class="w-40">
                        <Input v-model="bookingPnr" type="text" placeholder="PNR"
                            class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                    </div>
                </div>
            <div class="flex items-center justify-between mt-6">
                <div class="text-gray-700 font-medium">
                    Amount
                    <div class="text-xs text-gray-500">Enter the total booking amount</div>
                </div>
                <div class="w-40">
                    <Input v-model="bookingAmount" type="number" min="0" step="0.01" placeholder="Amount"
                        class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                </div>
            </div>
           <!-- Submit Button -->
            <div class="mt-6 flex justify-end">
                <Button type="submit"
                    class=" flex justify-end py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-gray-700 focus:outline-none focus:ring-1 focus:ring-offset-2 focus:ring-primary"
                    :disabled="isSubmitting" @click="submitForm">
                    <span v-if="isSubmitting">Processing...</span>
                    <span v-else>Save Booking</span>
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';
import Autocomplete from '@/components/common/Autocomplete.vue';
import DateRangePicker from '@/components/common/DateRangePicker.vue';
import Calender from '@/components/common/Calender.vue';
import CountryDropdown from '@/components/common/CountryDropdown.vue';
import { useAuthStore } from "@/services/stores/auth";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import { FETCH_AIRPORTS, FETCH_OFFLINE_BOOKING_DETAILS, FETCH_TRAVELLERS, UPDATE_OFFLINE_BOOKING } from "@/services/store/actions.type";
import Spinner from "@/components/common/Spinner.vue";
import { Trash2 } from "lucide-vue-next";

const store = useStore();
const userStore = useUserStore();
const route = useRoute();
const router = useRouter();
const isOpen = ref(false)

const authStore = useAuthStore();

// Flight and travellers state
const flight = ref(null);
const travellers = ref([]);
const errors = reactive({
    travellers: [],
    origin: "",
    destination: "",
    start: "",
    end: "",
    multiCityTrips: []
});
const searchQuery = ref("");
const selectedAgent = ref(null);
const bookingAmount = ref("");
const bookingPnr = ref("");
const todayDate = ref(new Date().toISOString().split('T')[0]);
const airports = computed(() => store.getters["airport/airports"]);
const offlineBookingDetails = computed(() => {
    const details = store.getters["offlineBooking/offlineBookingDetails"];
    if (details && typeof details.route === "string") {
        try {
            details.route = JSON.parse(details.route);
        } catch (error) {
            console.error("Error parsing route:", error);
            details.route = {};
        }
    }
    return details;
});
const isLoading = computed(() => store.getters["offlineBooking/isLoading"]);
const maxTotal = 9;
const countdown = ref(null); // Placeholder for countdown
const isSubmitting = ref(false);

const localValue = ref({
    flightType: 'one-way',
    adult: 1,
    child: 0,
    infant: 0,
    classType: 'Y',
    origin: '',
    destination: '',
    dateRange: {
        start: null,
        end: null
    },
    multiCityTrips: []
});

const totalTravelers = computed(() => localValue.value.adult + localValue.value.child + localValue.value.infant);
const maxAdults = computed(() => Math.max(1, maxTotal - localValue.value.child - localValue.value.infant));
const maxChildren = computed(() => Math.max(0, maxTotal - localValue.value.adult - localValue.value.infant));
const maxInfants = computed(() => {
    const maxByTotal = maxTotal - localValue.value.adult - localValue.value.child;
    return Math.min(localValue.value.adult, Math.max(0, maxByTotal));
});

const canRemoveTraveller = computed(() => {
    const minRequired = localValue.value.adult + localValue.value.child + localValue.value.infant;
    return travellers.value.length > minRequired;
});

const filteredUsers = computed(() => {
    if (!searchQuery.value) {
        return users.value.data;
    }
    return users?.value?.data?.filter(user =>
        user.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        user.role.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const users = computed(() => userStore.users);

// Helper to access error messages dynamically
const getErrorPath = (path) => {
    return path.split('.').reduce((obj, key) => obj && obj[key], errors);
};

// Validation
function validate() {
    // Reset errors while preserving structure
    errors.origin = "";
    errors.destination = "";
    errors.start = "";
    errors.end = "";
    errors.multiCityTrips = localValue.value.multiCityTrips.map(() => ({
        origin: "",
        destination: "",
        date: ""
    }));
    errors.travellers = travellers.value.map(() => ({
        title: "",
        firstName: "",
        lastName: "",
        nationality: "",
        documentType: "",
        documentNo: "",
        expiryDate: "",
        issueCountry: "",
        dob: "",
        gender: "",
    }));

    const { flightType, origin, destination, dateRange, multiCityTrips } = localValue.value;
    let valid = true;

    // Validate flight details
    if (flightType === 'one-way') {
        if (!origin) {
            errors.origin = "Origin is required.";
            valid = false;
        }
        if (!destination) {
            errors.destination = "Destination is required.";
            valid = false;
        }
        if (!dateRange.start) {
            errors.start = "Start date is required.";
            valid = false;
        }
    } else if (flightType === 'return') {
        if (!origin) {
            errors.origin = "Origin is required.";
            valid = false;
        }
        if (!destination) {
            errors.destination = "Destination is required.";
            valid = false;
        }
        if (!dateRange.start) {
            errors.start = "Start date is required.";
            valid = false;
        }
        if (!dateRange.end) {
            errors.end = "End date is required.";
            valid = false;
        }
    } else if (flightType === 'multi-city') {
        multiCityTrips.forEach((trip, idx) => {
            const tripErrors = { origin: "", destination: "", date: "" };
            if (!trip.origin) tripErrors.origin = "Origin is required.";
            if (!trip.destination) tripErrors.destination = "Destination is required.";
            if (!trip.date) tripErrors.date = "Date is required.";
            errors.multiCityTrips[idx] = tripErrors;
            if (tripErrors.origin || tripErrors.destination || tripErrors.date) valid = false;
        });
    }

    // Validate travellers
    travellers.value.forEach((traveller, idx) => {
        if (!traveller.title) errors.travellers[idx].title = "Title is required.";
        if (!traveller.firstName) errors.travellers[idx].firstName = "First Name is required.";
        if (!traveller.lastName) errors.travellers[idx].lastName = "Last Name is required.";
        if (!traveller.nationality) errors.travellers[idx].nationality = "Nationality is required.";
        if (!traveller.documentType) errors.travellers[idx].documentType = "Document Type is required.";
        if (!traveller.documentNo) errors.travellers[idx].documentNo = "Document Number is required.";
        if (!traveller.expiryDate) errors.travellers[idx].expiryDate = "Expiry Date is required.";
        if (!traveller.issueCountry) errors.travellers[idx].issueCountry = "Issue Country is required.";
        if (!traveller.dob) errors.travellers[idx].dob = "Date of Birth is required.";
        if (!traveller.gender) errors.travellers[idx].gender = "Gender is required.";

        if (
            errors.travellers[idx].title ||
            errors.travellers[idx].firstName ||
            errors.travellers[idx].lastName ||
            errors.travellers[idx].nationality ||
            errors.travellers[idx].documentType ||
            errors.travellers[idx].documentNo ||
            errors.travellers[idx].expiryDate ||
            errors.travellers[idx].issueCountry ||
            errors.travellers[idx].dob ||
            errors.travellers[idx].gender
        ) {
            valid = false;
        }
    });

    return valid;
}

// Watchers for error clearing
watch(() => localValue.value.origin, (val) => {
    if (val && errors.origin) {
        errors.origin = "";
    }
});
watch(() => localValue.value.destination, (val) => {
    if (val && errors.destination) {
        errors.destination = "";
    }
});
watch(() => localValue.value.dateRange.start, (val) => {
    if (val && errors.start) {
        errors.start = "";
    }
});
watch(() => localValue.value.dateRange.end, (val) => {
    if (val && errors.end) {
        errors.end = "";
    }
});
watch(() => localValue.value.multiCityTrips.map(t => t.origin), (origins) => {
    origins.forEach((origin, idx) => {
        if (origin && errors.multiCityTrips?.[idx]?.origin) {
            errors.multiCityTrips[idx].origin = "";
        }
    });
}, { deep: true });
watch(() => localValue.value.multiCityTrips.map(t => t.destination), (destinations) => {
    destinations.forEach((destination, idx) => {
        if (destination && errors.multiCityTrips?.[idx]?.destination) {
            errors.multiCityTrips[idx].destination = "";
        }
    });
}, { deep: true });
watch(() => localValue.value.multiCityTrips.map(t => t.date), (dates) => {
    dates.forEach((date, idx) => {
        if (date && errors.multiCityTrips?.[idx]?.date) {
            errors.multiCityTrips[idx].date = "";
        }
    });
}, { deep: true });

// Watchers for traveller error clearing
watch(() => travellers.value.map(t => t.title), (titles) => {
    titles.forEach((title, idx) => {
        if (title && errors.travellers?.[idx]?.title) {
            errors.travellers[idx].title = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.firstName), (firstNames) => {
    firstNames.forEach((firstName, idx) => {
        if (firstName && errors.travellers?.[idx]?.firstName) {
            errors.travellers[idx].firstName = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.lastName), (lastNames) => {
    lastNames.forEach((lastName, idx) => {
        if (lastName && errors.travellers?.[idx]?.lastName) {
            errors.travellers[idx].lastName = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.nationality), (nationalities) => {
    nationalities.forEach((nationality, idx) => {
        if (nationality && errors.travellers?.[idx]?.nationality) {
            errors.travellers[idx].nationality = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.documentType), (documentTypes) => {
    documentTypes.forEach((documentType, idx) => {
        if (documentType && errors.travellers?.[idx]?.documentType) {
            errors.travellers[idx].documentType = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.documentNo), (documentNos) => {
    documentNos.forEach((documentNo, idx) => {
        if (documentNo && errors.travellers?.[idx]?.documentNo) {
            errors.travellers[idx].documentNo = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.expiryDate), (expiryDates) => {
    expiryDates.forEach((expiryDate, idx) => {
        if (expiryDate && errors.travellers?.[idx]?.expiryDate) {
            errors.travellers[idx].expiryDate = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.issueCountry), (issueCountries) => {
    issueCountries.forEach((issueCountry, idx) => {
        if (issueCountry && errors.travellers?.[idx]?.issueCountry) {
            errors.travellers[idx].issueCountry = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.dob), (dobs) => {
    dobs.forEach((dob, idx) => {
        if (dob && errors.travellers?.[idx]?.dob) {
            errors.travellers[idx].dob = "";
        }
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.gender), (genders) => {
    genders.forEach((gender, idx) => {
        if (gender && errors.travellers?.[idx]?.gender) {
            errors.travellers[idx].gender = "";
        }
    });
}, { deep: true });

// Traveler Management
function clampTravelers() {
    let total = localValue.value.adult + localValue.value.child + localValue.value.infant;
    if (total > maxTotal) {
        let adults = localValue.value.adult;
        let infants = Math.min(localValue.value.infant, adults);
        let children = localValue.value.child;
        adults = Math.max(1, Math.min(adults, maxTotal));
        infants = Math.min(infants, adults);
        let remaining = maxTotal - adults - infants;
        children = Math.max(0, Math.min(children, remaining));
        localValue.value.adult = adults;
        localValue.value.infant = infants;
        localValue.value.child = children;
    }
}

function handleAdultChange(val) {
    const maxAllowed = maxTotal - localValue.value.child - localValue.value.infant;
    localValue.value.adult = Math.min(val, maxAllowed);
    const maxInf = Math.min(localValue.value.adult, maxTotal - localValue.value.adult - localValue.value.child);
    if (localValue.value.infant > maxInf) localValue.value.infant = maxInf;
    clampTravelers();
}

function handleChildChange(val) {
    localValue.value.child = val;
    clampTravelers();
}

function handleInfantChange(val) {
    const maxAllowed = Math.min(localValue.value.adult, maxTotal - localValue.value.adult - localValue.value.child);
    localValue.value.infant = Math.min(val, maxAllowed);
    clampTravelers();
}

// Add new traveller
const addTraveller = () => {
    travellers.value.push({
        type: "ADT",
        title: "",
        firstName: "",
        lastName: "",
        nationality: "",
        documentType: "passport",
        documentNo: "",
        expiryDate: "",
        issueCountry: "",
        dob: "",
        gender: "",
        isOpenCountryDropdown: false,
        isOpenIssueCountryDropdown: false,
        showAncillaries: false,
    });
    errors.travellers.push({
        title: "",
        firstName: "",
        lastName: "",
        nationality: "",
        documentType: "",
        documentNo: "",
        expiryDate: "",
        issueCountry: "",
        dob: "",
        gender: "",
    });
};

// Remove traveller
const removeTraveller = (index) => {
    const minRequired = localValue.value.adult + localValue.value.child + localValue.value.infant;
    if (travellers.value.length <= minRequired) return;

    const travellerType = travellers.value[index].type;
    travellers.value.splice(index, 1);
    errors.travellers.splice(index, 1);

    // Adjust counts based on removed traveller type
    if (travellerType === 'ADT' && localValue.value.adult > 1) {
        localValue.value.adult--;
    } else if (travellerType === 'CNN' && localValue.value.child > 0) {
        localValue.value.child--;
    } else if (travellerType === 'INF' && localValue.value.infant > 0) {
        localValue.value.infant--;
    }
};

// Add new trip for multi-city
const addTrip = () => {
    localValue.value.multiCityTrips.push({
        origin: null,
        destination: null,
        date: ""
    });
    errors.multiCityTrips.push({
        origin: "",
        destination: "",
        date: ""
    });
};

// Remove trip for multi-city
const removeTrip = (index) => {
    if (localValue.value.multiCityTrips.length > 2) {
        localValue.value.multiCityTrips.splice(index, 1);
        errors.multiCityTrips.splice(index, 1);
    }
};

// Fetch users
const fetchUsers = () => {
    const effectiveRole = 'agent';
    userStore.fetchUsers({
        search_query: searchQuery.value,
        page: route.query.page,
        approval_status: 'all',
        role: effectiveRole,
    });
};

// Placeholder for fetching agent travellers
const agentTravellers = computed(() => store.getters["traveller/travellers"]);
function fetchAgentTravellers() {
    store.dispatch("traveller/" + FETCH_TRAVELLERS);
}

const selectAgent = (user) => {
    selectedAgent.value = user;
    searchQuery.value = '';
    fetchAgentTravellers();
};

// Handle agent traveller selection
const handleSelectedTravellerAgentChange = (travellerIndex, index) => {
    const selectedTraveller = agentTravellers.value[travellerIndex];
    if (selectedTraveller) {
        travellers.value[index] = {
            ...travellers.value[index],
            title: selectedTraveller.title || "",
            firstName: selectedTraveller.first_name?.toUpperCase() || "",
            lastName: selectedTraveller.last_name?.toUpperCase() || "",
            nationality: selectedTraveller.nationality || "",
            documentType: selectedTraveller.document_type || "passport",
            documentNo: selectedTraveller.document_no || "",
            expiryDate: selectedTraveller.expiry_date || "",
            issueCountry: selectedTraveller.issue_country || "",
            dob: selectedTraveller.dob || "",
            gender: selectedTraveller.gender || "",
        };
    }
};

// Populate form with booking details
const populateForm = () => {
    if (offlineBookingDetails.value) {
        const { user, flight_type, class_type, adult, child, infant, route, amount,booking_pnr, travellers: bookingTravellers } = offlineBookingDetails.value;
        // Populate agent
        selectedAgent.value = user;
        bookingAmount.value = amount ?? 0;
        bookingPnr.value = booking_pnr ?? "";

        // Populate flight details
        localValue.value.flightType = flight_type;
        localValue.value.classType = class_type;
        localValue.value.adult = adult || 1;
        localValue.value.child = child || 0;
        localValue.value.infant = infant || 0;

        // Handle route based on type
        if (Array.isArray(route)) {
            // Multi-city route
            localValue.value.flightType = 'multi-city';
            localValue.value.multiCityTrips = route.map(trip => ({
                origin: trip.origin,
                destination: trip.destination,
                date: trip.date
            }));
            // Ensure at least two trips for multi-city
            while (localValue.value.multiCityTrips.length < 2) {
                localValue.value.multiCityTrips.push({ origin: null, destination: null, date: "" });
            }
            errors.multiCityTrips = localValue.value.multiCityTrips.map(() => ({
                origin: "",
                destination: "",
                date: ""
            }));
        } else {
            // Single route (one-way or return)
            localValue.value.origin = route.origin;
            localValue.value.destination = route.destination;
            localValue.value.dateRange.start = route.dateRange.start;
            localValue.value.dateRange.end = route.dateRange.end;
            localValue.value.flightType = route.dateRange.end ? 'return' : 'one-way';
        }

        // Populate travellers
        travellers.value = bookingTravellers.map(traveller => ({
            id: traveller.id,
            type: traveller.type,
            title: traveller.title,
            firstName: traveller.first_name?.toUpperCase(),
            lastName: traveller.last_name?.toUpperCase(),
            nationality: traveller.nationality,
            documentType: traveller.document_type,
            documentNo: traveller.document_no,
            expiryDate: traveller.expiry_date,
            issueCountry: traveller.issue_country,
            dob: traveller.dob,
            gender: traveller.gender,
            isOpenCountryDropdown: false,
            isOpenIssueCountryDropdown: false,
            showAncillaries: false,
        }));
        errors.travellers = travellers.value.map(() => ({
            title: "",
            firstName: "",
            lastName: "",
            nationality: "",
            documentType: "",
            documentNo: "",
            expiryDate: "",
            issueCountry: "",
            dob: "",
            gender: "",
        }));
    }
};

// Fetch booking details
function fetchBookingDetails() {
    store.dispatch("offlineBooking/" + FETCH_OFFLINE_BOOKING_DETAILS, {
        bookingId: route.query.booking_id,
    }).then(() => {
        populateForm();
    }).catch(error => {
        console.error("Error fetching booking details:", error);
    });
}

// Submit form
const submitForm = () => {
    isSubmitting.value = true;
    if (validate()) {
        const bookingData = {
            id: offlineBookingDetails.value.id,
            agentId: selectedAgent.value ? selectedAgent.value.id : null,
            flightType: localValue.value.flightType,
            classType: localValue.value.classType,
            adult: localValue.value.adult,
            child: localValue.value.child,
            infant: localValue.value.infant,
            amount: bookingAmount.value || offlineBookingDetails.value.amount,
            bookingPnr: bookingPnr.value || offlineBookingDetails.value.booking_pnr,
            route: localValue.value.flightType === 'multi-city'
                ? localValue.value.multiCityTrips
                : { origin: localValue.value.origin, destination: localValue.value.destination, dateRange: localValue.value.dateRange },
            travellers: travellers.value.map(traveller => ({
                id: traveller.id || null,
                offline_booking_id: offlineBookingDetails.value.id,
                type: traveller.type,
                title: traveller.title,
                firstName: traveller.firstName,
                lastName: traveller.lastName,
                nationality: traveller.nationality,
                documentType: traveller.documentType,
                documentNo: traveller.documentNo,
                expiryDate: traveller.expiryDate,
                issueCountry: traveller.issueCountry,
                dob: traveller.dob,
                gender: traveller.gender
            }))
        };

        store.dispatch('offlineBooking/' + UPDATE_OFFLINE_BOOKING, bookingData).then(() => {
            router.push({ name: "OfflineBookings" });
        }).catch(error => {
            console.error("Error updating booking:", error);
        });
    }
    isSubmitting.value = false;
};

// Initialize
onMounted(() => {
    fetchUsers();
    store.dispatch("airport/" + FETCH_AIRPORTS);
    fetchAgentTravellers();
    fetchBookingDetails();
});

// Watch offlineBookingDetails to populate form when data is fetched
watch(offlineBookingDetails, () => {
    populateForm();
}, { deep: true });
</script>