<script setup>
import InputMessage from "@/components/common/InputMessage.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { Check, ChevronsUpDown, LoaderCircle } from "lucide-vue-next";
import {
    FormControl,
    FormDescription,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from "@/components/ui/form";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
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
import {
    FETCH_CITIES,
    FETCH_COUNTRIES,
    REGISTER,
} from "@/services/store/actions.type";
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import * as z from "zod";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

const store = useStore();
const router = useRouter();

const countries = computed(() => {
    const countries = store.getters["country/countries"] || [];
    return countries.map((country) => ({
        value: country.name,
        label: country.name,
    }));
});
const cities = computed(() => {
    const cities = store.getters["city/cities"] || [];
    return cities.map((city) => ({
        value: city.name,
        label: city.name,
    }));
});
const apiError = computed(() => store.getters["auth/apiError"]);
const isLoading = computed(() => store.getters["auth/isLoading"]);

const isOpenCountryDropdown = ref(false);
const isOpenCityDropdown = ref(false);

const registerForm = useForm({
    validationSchema: toTypedSchema(
        z.object({
            email: z.string().email().min(2).max(50),
            password: z.string().min(4),
            first_name: z.string(),
            last_name: z.string(),
            phone: z.number(),
            preferred_currency: z.string(),
            company_name: z.string(),
            company_reg_no: z.string(),
            business_nature: z.string(),
            country: z.string(),
            city: z.string(),
            postal_code: z.string(),
            address: z.string(),
        })
    ),
});

const handleRegister = registerForm.handleSubmit((values) => {
    store
        .dispatch("auth/" + REGISTER, {
            email: values.email,
            password: values.password,
            first_name: values.first_name,
            last_name: values.last_name,
            phone: values.phone,
            preferred_currency: values.preferred_currency,
            company_name: values.company_name,
            company_reg_no: values.company_reg_no,
            business_nature: values.business_nature,
            country: values.country,
            city: values.city,
            postal_code: values.postal_code,
            address: values.address,
            role: 'agent',
        })
        .then(() => {
            if (apiError.value == null) {
                router.push({ name: "Login" });
            }
        });
});

function fetchCountries(event) {
    //console.log(event.target.value);

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: event.target.value,
    });
}

function fetchCities(event) {
    //console.log(event.target.value);

    store.dispatch("city/" + FETCH_CITIES, {
        searchQuery: event.target.value,
    });
}
</script>

<template>
    <section class="grid grid-cols-2 h-screen overflow-hidden">
        <div class="h-screen p-2">
            <img
                class="h-full w-full object-cover bg-center rounded-xl border"
                src="https://images.pexels.com/photos/175656/pexels-photo-175656.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1"
                alt=""
            />
        </div>
        <div
            class="flex items-center justify-center overflow-y-scroll pt-[400px] pb-[100px]"
        >
            <div class="w-[80%] p-6 space-y-4 md:space-y-6 sm:p-8">
                <!-- <a
                            href="#"
                            class="mb-6 text-2xl font-semibold text-gray-900 dark:text-white"
                        >
                            <img
                                class="w-[200px] mr-2"
                                src="../../images/logo-new.png"
                                alt="Raheem Sons logo"
                            />
                        </a> -->
                <h1
                    class="text-xl font-medium leading-tight tracking-tight text-gray-800 md:text-3xl mb-4"
                >
                    Welcome!
                </h1>
                <span class="text-base font-normal text-muted-foreground">
                    Log In to your account
                </span>
                <form @submit="handleRegister" class="space-y-4 md:space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="mb-3">
                            <FormField v-slot="{ componentField }" name="email">
                                <FormItem>
                                    <FormLabel>Your email</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="email"
                                            placeholder="Enter your email address"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="password"
                            >
                                <FormItem>
                                    <FormLabel>Password</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="password"
                                            placeholder="••••••••"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="first_name"
                            >
                                <FormItem>
                                    <FormLabel>First Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your first name"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="last_name"
                            >
                                <FormItem>
                                    <FormLabel>Last Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your last name"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField v-slot="{ componentField }" name="phone">
                                <FormItem>
                                    <FormLabel>Phone no</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="number"
                                            placeholder="Enter your phone no"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="preferred_currency"
                            >
                                <FormItem>
                                    <FormLabel>Preferred Currency</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger class="py-6">
                                                <SelectValue
                                                    placeholder="Select a preferred currency"
                                                />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="USD">
                                                    USD
                                                </SelectItem>
                                                <SelectItem value="AED">
                                                    AED
                                                </SelectItem>
                                                <SelectItem value="PKR">
                                                    PKR
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>

                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="company_name"
                            >
                                <FormItem>
                                    <FormLabel>Company Name</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your company name"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="company_reg_no"
                            >
                                <FormItem>
                                    <FormLabel>Company Reg No</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your company reg no"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="business_nature"
                            >
                                <FormItem>
                                    <FormLabel>Business Nature</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger class="py-6">
                                                <SelectValue
                                                    placeholder="Select your business nature"
                                                />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem
                                                    value="des_management_company"
                                                >
                                                    Destination management
                                                    company
                                                </SelectItem>
                                                <SelectItem
                                                    value="tour_operator"
                                                >
                                                    Tour operator
                                                </SelectItem>
                                                <SelectItem
                                                    value="travel_agent"
                                                >
                                                    Travel Agent
                                                </SelectItem>
                                                <SelectItem
                                                    value="wholesale_travel_company"
                                                >
                                                    Wholesale travel company
                                                </SelectItem>
                                                <SelectItem value="corporation">
                                                    Corporation
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>

                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField name="country">
                                <FormItem>
                                    <FormLabel>Country</FormLabel>
                                    <Popover
                                        v-model:open="isOpenCountryDropdown"
                                    >
                                        <PopoverTrigger as-child>
                                            <FormControl>
                                                <Button
                                                    variant="outline"
                                                    role="combobox"
                                                    class="w-full justify-between py-6"
                                                >
                                                    {{
                                                        registerForm.values
                                                            .country !== ""
                                                            ? countries.find(
                                                                  (country) =>
                                                                      country.value ===
                                                                      registerForm
                                                                          .values
                                                                          .country
                                                              )?.label ||
                                                              "Select a country..."
                                                            : "Select a country..."
                                                    }}
                                                    <ChevronsUpDown
                                                        class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                                    />
                                                </Button>
                                            </FormControl>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-full p-0">
                                            <Command>
                                                <CommandInput
                                                    class="h-9"
                                                    @input="fetchCountries"
                                                    placeholder="Search country..."
                                                />
                                                <CommandEmpty
                                                    >No results
                                                    found.</CommandEmpty
                                                >
                                                <CommandList>
                                                    <CommandGroup>
                                                        <CommandItem
                                                            v-for="country in countries"
                                                            :key="country.value"
                                                            :value="
                                                                country.label
                                                            "
                                                            @select="
                                                                registerForm.setFieldValue(
                                                                    'country',
                                                                    country.value
                                                                );
                                                                isOpenCountryDropdown = false;
                                                            "
                                                        >
                                                            {{ country.label }}
                                                            <Check
                                                                :class="
                                                                    cn(
                                                                        'ml-auto h-4 w-4',
                                                                        registerForm
                                                                            .values
                                                                            .country ===
                                                                            country.value
                                                                            ? 'opacity-100'
                                                                            : 'opacity-0'
                                                                    )
                                                                "
                                                            />
                                                        </CommandItem>
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField name="city">
                                <FormItem>
                                    <FormLabel>City</FormLabel>
                                    <Popover v-model:open="isOpenCityDropdown">
                                        <PopoverTrigger as-child>
                                            <FormControl>
                                                <Button
                                                    variant="outline"
                                                    role="combobox"
                                                    class="w-full justify-between py-6"
                                                >
                                                    {{
                                                        registerForm.values
                                                            .city !== ""
                                                            ? cities.find(
                                                                  (city) =>
                                                                      city.value ===
                                                                      registerForm
                                                                          .values
                                                                          .city
                                                              )?.label ||
                                                              "Select a city..."
                                                            : "Select a city..."
                                                    }}
                                                    <ChevronsUpDown
                                                        class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                                    />
                                                </Button>
                                            </FormControl>
                                        </PopoverTrigger>
                                        <PopoverContent class="w-full p-0">
                                            <Command>
                                                <CommandInput
                                                    class="h-9"
                                                    @input="fetchCities"
                                                    placeholder="Search city..."
                                                />
                                                <CommandEmpty
                                                    >No results
                                                    found.</CommandEmpty
                                                >
                                                <CommandList>
                                                    <CommandGroup>
                                                        <CommandItem
                                                            v-for="city in cities"
                                                            :key="city.value"
                                                            :value="city.label"
                                                            @select="
                                                                registerForm.setFieldValue(
                                                                    'city',
                                                                    city.value
                                                                );
                                                                isOpenCityDropdown = false;
                                                            "
                                                        >
                                                            {{ city.label }}
                                                            <Check
                                                                :class="
                                                                    cn(
                                                                        'ml-auto h-4 w-4',
                                                                        registerForm
                                                                            .values
                                                                            .city ===
                                                                            city.value
                                                                            ? 'opacity-100'
                                                                            : 'opacity-0'
                                                                    )
                                                                "
                                                            />
                                                        </CommandItem>
                                                    </CommandGroup>
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3">
                            <FormField
                                v-slot="{ componentField }"
                                name="postal_code"
                            >
                                <FormItem>
                                    <FormLabel>Postal Code</FormLabel>
                                    <FormControl>
                                        <Input
                                            type="text"
                                            placeholder="Enter your postal code"
                                            v-bind="componentField"
                                            class="py-6"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="mb-3 col-span-2">
                            <FormField
                                v-slot="{ componentField }"
                                name="address"
                            >
                                <FormItem>
                                    <FormLabel>Address</FormLabel>
                                    <FormControl>
                                        <Textarea
                                            v-bind="componentField"
                                            placeholder="Enter your address."
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>
                    </div>
                    <Button
                        type="submit"
                        :disabled="isLoading"
                        class="w-full py-6"
                    >
                        <span v-if="!isLoading"> Sign Up </span>
                        <span v-else class="flex items-center gap-2"
                            ><LoaderCircle
                                class="w-5 h-5 animate-spin"
                            />Loading...</span
                        >
                    </Button>
                </form>
                <div class="flex items-center justify-between gap-4">
                    <hr class="w-full" />
                    <span>or</span>
                    <hr class="w-full" />
                </div>
                <p class="mt-2 mx-auto text-center">
                    <span class="text-muted-foreground mr-2"
                        >Already have and account?</span
                    >
                    <router-link :to="{ name: 'Login' }" class="text-primary"
                        >Log In</router-link
                    >
                </p>
            </div>
        </div>
    </section>
</template>
