<template>
    <div class="space-y-5">
        <!-- Top Bar Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 px-2.5 text-xs rounded border-slate-200 text-slate-600 hover:bg-slate-100 max-sm:hidden"
                    @click="router.back()"
                >
                    <ArrowLeft class="w-3.5 h-3.5 mr-1" />
                    Back
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        New Offline Flight Booking
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Issue direct offline airline tickets, passenger manifests, and manual PNR ledger bookings
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    class="h-9 px-4 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
                    :disabled="isSubmitting"
                    @click="submitForm"
                >
                    <Ticket class="w-4 h-4" />
                    <span>{{ isSubmitting ? 'Processing Booking...' : 'Save Booking' }}</span>
                </Button>
            </div>
        </div>

        <!-- KPI / Configuration Summary Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Flight Type</span>
                    <PlaneTakeoff class="h-4 w-4 text-primary" />
                </div>
                <div class="mt-1 text-sm font-bold text-slate-900 capitalize">
                    {{ localValue.flightType.replace('-', ' ') }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Travelers</span>
                    <Users class="h-4 w-4 text-sky-500" />
                </div>
                <div class="mt-1 text-sm font-bold text-sky-700">
                    {{ totalTravelers }} Passenger{{ totalTravelers > 1 ? 's' : '' }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Assigned Agent</span>
                    <UserCheck class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xs font-bold text-emerald-700 truncate" :title="selectedAgent?.email || 'Direct Booking'">
                    {{ selectedAgent ? selectedAgent.email : 'Direct Booking' }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Amount</span>
                    <CreditCard class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-1 text-sm font-bold text-slate-900">
                    {{ amount ? `PKR ${Number(amount).toLocaleString()}` : 'PKR 0.00' }}
                </div>
            </div>
        </div>

        <!-- 1. Agent Selection Section -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <UserCheck class="w-4 h-4 text-primary" />
                        <span>Agent / Partner Assignment</span>
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Link this booking to a registered travel agency account or manage as a direct office booking
                    </p>
                </div>
                <span v-if="selectedAgent" class="text-[10px] font-mono font-semibold uppercase px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                    {{ selectedAgent.role || 'Agent' }}
                </span>
            </div>

            <div class="max-w-md space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">Select Travel Agent</label>
                <Popover v-model:open="isOpen">
                    <PopoverTrigger
                        class="w-full h-9 px-3 text-left rounded border border-slate-200 bg-white text-xs text-slate-800 hover:bg-slate-50 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition flex items-center justify-between"
                    >
                        <span class="truncate">{{ selectedAgent ? `${selectedAgent.email} (${selectedAgent.role})` : 'Search & select an agent...' }}</span>
                        <Search class="w-3.5 h-3.5 text-slate-400 flex-shrink-0 ml-2" />
                    </PopoverTrigger>
                    <PopoverContent class="w-[320px] p-0 rounded border border-slate-200 bg-white shadow-lg">
                        <Command class="rounded border-0">
                            <CommandInput placeholder="Type email or role to search..." v-model="searchQuery" class="h-9 text-xs" />
                            <CommandList class="max-h-56">
                                <CommandEmpty class="p-3 text-xs text-slate-500 text-center">No agents found.</CommandEmpty>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="user in filteredUsers"
                                        :key="user.id"
                                        :value="user.email"
                                        @select="() => { selectAgent(user); isOpen = false; }"
                                        class="cursor-pointer text-xs py-2 px-3 hover:bg-slate-100 flex items-center justify-between"
                                    >
                                        <span class="truncate font-medium text-slate-800">{{ user.email }}</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 ml-2 flex-shrink-0">
                                            {{ user.role }}
                                        </span>
                                    </CommandItem>
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>
        </div>

        <!-- 2. Travel Route Selection Section -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <PlaneTakeoff class="w-4 h-4 text-primary" />
                        <span>Travel Route & Schedule</span>
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Configure origin, destination, departure dates, flight segments, and cabin tier
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 uppercase font-semibold">
                        {{ localValue.flightType.replace('-', ' ') }}
                    </span>
                </div>
            </div>

            <!-- Route Type & Travelers Controls Bar -->
            <div class="p-2.5 rounded border border-slate-200 bg-slate-50/80 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <!-- Segmented Trip Type Selector -->
                <div class="inline-flex items-center rounded border border-slate-200 bg-white p-1 shadow-2xs w-full sm:w-auto">
                    <button
                        type="button"
                        @click="setFlightType('one-way')"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-1.5 text-xs rounded transition-all select-none"
                        :class="localValue.flightType === 'one-way'
                            ? 'bg-primary text-white shadow-xs font-semibold'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    >
                        <ArrowRight class="w-3.5 h-3.5" />
                        <span>One Way</span>
                    </button>
                    <button
                        type="button"
                        @click="setFlightType('return')"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-1.5 text-xs rounded transition-all select-none"
                        :class="localValue.flightType === 'return'
                            ? 'bg-primary text-white shadow-xs font-semibold'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    >
                        <ArrowLeftRight class="w-3.5 h-3.5" />
                        <span>Round Trip / Return</span>
                    </button>
                    <button
                        type="button"
                        @click="setFlightType('multi-city')"
                        class="flex-1 sm:flex-initial flex items-center justify-center gap-1.5 px-3.5 py-1.5 text-xs rounded transition-all select-none"
                        :class="localValue.flightType === 'multi-city'
                            ? 'bg-primary text-white shadow-xs font-semibold'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'"
                    >
                        <Milestone class="w-3.5 h-3.5" />
                        <span>Multi-City</span>
                    </button>
                </div>

                <!-- Right Side: Cabin Class & Travelers -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Cabin Class -->
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-medium text-slate-500 hidden sm:inline">Cabin:</span>
                        <Select v-model="localValue.classType">
                            <SelectTrigger class="h-9 w-36 rounded border-slate-200 bg-white text-xs font-medium">
                                <SelectValue placeholder="Cabin Class" />
                            </SelectTrigger>
                            <SelectContent class="rounded border-slate-200 bg-white">
                                <SelectItem value="Y" class="text-xs">{{ $t ? $t('economy') : 'Economy' }}</SelectItem>
                                <SelectItem value="S" class="text-xs">{{ $t ? $t('premium_economy') : 'Premium Economy' }}</SelectItem>
                                <SelectItem value="C" class="text-xs">{{ $t ? $t('business') : 'Business' }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Travelers Counter Popover -->
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button variant="outline" class="h-9 px-3 text-xs font-semibold rounded border-slate-200 bg-white text-slate-700 hover:bg-slate-50 flex items-center gap-1.5">
                                <Users class="w-3.5 h-3.5 text-slate-500" />
                                <span>{{ totalTravelers }} Traveler{{ totalTravelers > 1 ? 's' : '' }}</span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-64 p-4 rounded border border-slate-200 bg-white shadow-lg space-y-3.5">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-1.5">
                                Passenger Allocation
                            </h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <Label for="adult-field" class="text-xs font-semibold text-slate-800">Adult</Label>
                                        <p class="text-[10px] text-slate-400">12+ years</p>
                                    </div>
                                    <NumberField id="adult-field" v-model="localValue.adult" :min="1" :max="maxAdults" @update:modelValue="handleAdultChange" class="w-28">
                                        <NumberFieldContent class="rounded border border-slate-200">
                                            <NumberFieldDecrement class="rounded-l text-xs" />
                                            <NumberFieldInput class="text-xs font-bold" />
                                            <NumberFieldIncrement class="rounded-r text-xs" />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <Label for="child-field" class="text-xs font-semibold text-slate-800">Child</Label>
                                        <p class="text-[10px] text-slate-400">2 - 11 years</p>
                                    </div>
                                    <NumberField id="child-field" v-model="localValue.child" :min="0" :max="maxChildren" @update:modelValue="handleChildChange" class="w-28">
                                        <NumberFieldContent class="rounded border border-slate-200">
                                            <NumberFieldDecrement class="rounded-l text-xs" />
                                            <NumberFieldInput class="text-xs font-bold" />
                                            <NumberFieldIncrement class="rounded-r text-xs" />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <Label for="infant-field" class="text-xs font-semibold text-slate-800">Infant</Label>
                                        <p class="text-[10px] text-slate-400">Under 2 years</p>
                                    </div>
                                    <NumberField id="infant-field" v-model="localValue.infant" :min="0" :max="maxInfants" @update:modelValue="handleInfantChange" class="w-28">
                                        <NumberFieldContent class="rounded border border-slate-200">
                                            <NumberFieldDecrement class="rounded-l text-xs" />
                                            <NumberFieldInput class="text-xs font-bold" />
                                            <NumberFieldIncrement class="rounded-r text-xs" />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>
            </div>

            <!-- Route Inputs: One-Way Mode -->
            <div
                v-if="localValue.flightType === 'one-way'"
                class="grid grid-cols-1 md:grid-cols-3 gap-3.5 pt-1"
            >
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Origin Airport <span class="text-rose-500">*</span></label>
                    <AdminAirportSelect
                        v-model="localValue.origin"
                        placeholder="Select origin airport (e.g. KHI)..."
                        :source="airports"
                        icon="PlaneTakeoff"
                        :has-error="!!errors.origin"
                    />
                    <div v-if="errors.origin" class="text-rose-600 text-[11px] font-medium">{{ errors.origin }}</div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700">Destination Airport <span class="text-rose-500">*</span></label>
                        <button
                            type="button"
                            @click="swapAirports"
                            class="text-[11px] text-primary hover:text-primary/80 flex items-center gap-1 font-semibold transition"
                            title="Swap Origin and Destination"
                        >
                            <ArrowLeftRight class="w-3 h-3" />
                            <span>Swap</span>
                        </button>
                    </div>
                    <AdminAirportSelect
                        v-model="localValue.destination"
                        placeholder="Select destination airport (e.g. DXB)..."
                        :source="airports"
                        icon="PlaneLanding"
                        :has-error="!!errors.destination"
                    />
                    <div v-if="errors.destination" class="text-rose-600 text-[11px] font-medium">{{ errors.destination }}</div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Departure Date <span class="text-rose-500">*</span></label>
                    <Calender v-model="localValue.dateRange.start" :minValue="todayDate" class="rounded border border-slate-200" />
                    <div v-if="errors.start" class="text-rose-600 text-[11px] font-medium">{{ errors.start }}</div>
                </div>
            </div>

            <!-- Route Inputs: Return / Round Trip Mode -->
            <div
                v-else-if="localValue.flightType === 'return'"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-1"
            >
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Origin Airport <span class="text-rose-500">*</span></label>
                    <AdminAirportSelect
                        v-model="localValue.origin"
                        placeholder="Select origin (e.g. KHI)..."
                        :source="airports"
                        icon="PlaneTakeoff"
                        :has-error="!!errors.origin"
                    />
                    <div v-if="errors.origin" class="text-rose-600 text-[11px] font-medium">{{ errors.origin }}</div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700">Destination Airport <span class="text-rose-500">*</span></label>
                        <button
                            type="button"
                            @click="swapAirports"
                            class="text-[11px] text-primary hover:text-primary/80 flex items-center gap-1 font-semibold transition"
                            title="Swap Origin and Destination"
                        >
                            <ArrowLeftRight class="w-3 h-3" />
                            <span>Swap</span>
                        </button>
                    </div>
                    <AdminAirportSelect
                        v-model="localValue.destination"
                        placeholder="Select destination (e.g. DXB)..."
                        :source="airports"
                        icon="PlaneLanding"
                        :has-error="!!errors.destination"
                    />
                    <div v-if="errors.destination" class="text-rose-600 text-[11px] font-medium">{{ errors.destination }}</div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Departure Date <span class="text-rose-500">*</span></label>
                    <Calender v-model="localValue.dateRange.start" :minValue="todayDate" class="rounded border border-slate-200" />
                    <div v-if="errors.start" class="text-rose-600 text-[11px] font-medium">{{ errors.start }}</div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Return Date <span class="text-rose-500">*</span></label>
                    <Calender
                        v-model="localValue.dateRange.end"
                        :minValue="localValue.dateRange.start || todayDate"
                        class="rounded border border-slate-200"
                    />
                    <div v-if="errors.end" class="text-rose-600 text-[11px] font-medium">{{ errors.end }}</div>
                </div>
            </div>

            <!-- Route Inputs: Multi-City Mode -->
            <div v-else class="space-y-3 pt-1">
                <div
                    v-for="(trip, index) in localValue.multiCityTrips"
                    :key="index"
                    class="p-3.5 rounded border border-slate-200 bg-slate-50/50 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end"
                >
                    <div class="sm:col-span-5 space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-700">
                            Segment {{ index + 1 }} Origin <span class="text-rose-500">*</span>
                        </label>
                        <AdminAirportSelect
                            v-model="trip.origin"
                            :placeholder="`Segment ${index + 1} Origin...`"
                            :source="airports"
                            icon="PlaneTakeoff"
                            :has-error="!!(errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].origin)"
                        />
                        <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].origin" class="text-rose-600 text-[11px] font-medium">
                            {{ errors.multiCityTrips[index].origin }}
                        </div>
                    </div>

                    <div class="sm:col-span-4 space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-700">
                            Segment {{ index + 1 }} Destination <span class="text-rose-500">*</span>
                        </label>
                        <AdminAirportSelect
                            v-model="trip.destination"
                            :placeholder="`Segment ${index + 1} Destination...`"
                            :source="airports"
                            icon="PlaneLanding"
                            :has-error="!!(errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].destination)"
                        />
                        <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].destination" class="text-rose-600 text-[11px] font-medium">
                            {{ errors.multiCityTrips[index].destination }}
                        </div>
                    </div>

                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-700">
                            Flight Date <span class="text-rose-500">*</span>
                        </label>
                        <Calender
                            v-model="trip.date"
                            :minValue="index === 0 ? todayDate : localValue.multiCityTrips[index - 1]?.date || todayDate"
                            class="w-full rounded border border-slate-200"
                        />
                        <div v-if="errors.multiCityTrips && errors.multiCityTrips[index] && errors.multiCityTrips[index].date" class="text-rose-600 text-[11px] font-medium">
                            {{ errors.multiCityTrips[index].date }}
                        </div>
                    </div>

                    <div class="sm:col-span-1 flex items-center justify-end">
                        <Button
                            v-if="index >= 2"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10 w-10 p-0 rounded border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300"
                            @click="removeTrip(index)"
                            title="Remove Flight Segment"
                        >
                            <Trash2 class="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                <div class="pt-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 px-3 text-xs font-semibold rounded border-slate-200 text-primary hover:bg-primary/5 flex items-center gap-1.5"
                        @click="addTrip"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Add Flight Segment</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- 3. Travellers Section -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <Users class="w-4 h-4 text-primary" />
                        <span>Passenger Manifest Details</span>
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Ensure given names and surnames match official passport or government ID exactly
                    </p>
                </div>

                <div v-if="countdown !== null && countdown !== '0'" class="px-2.5 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 text-xs font-mono font-bold flex items-center gap-1 self-start sm:self-auto">
                    <span>Session: {{ countdown }}</span>
                </div>
            </div>

            <!-- Traveler Accordions -->
            <div class="space-y-3">
                <div
                    v-for="(traveller, index) in travellers"
                    :key="`traveller-${index}`"
                    class="rounded border border-slate-200 overflow-hidden"
                >
                    <Accordion type="single" collapsible class="border-0">
                        <AccordionItem :value="`traveller-${index}`" class="border-0">
                            <AccordionTrigger class="p-3.5 bg-slate-50/70 hover:bg-slate-100/70 transition-colors border-0">
                                <div class="flex items-center justify-between w-full pr-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 bg-primary/10 rounded text-primary font-bold text-xs flex items-center justify-center">
                                            {{ index + 1 }}
                                        </div>
                                        <div class="text-left">
                                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">
                                                {{ traveller.type === 'ADT' ? 'Adult' : traveller.type === 'CNN' ? 'Child' : 'Infant' }} Passenger {{ index + 1 }}
                                            </h3>
                                            <span class="text-[11px] text-slate-400 font-normal">
                                                {{ traveller.firstName || traveller.lastName ? `${traveller.title} ${traveller.firstName} ${traveller.lastName}` : 'Incomplete manifest' }}
                                            </span>
                                        </div>
                                    </div>

                                    <button
                                        v-if="canRemoveTraveller"
                                        type="button"
                                        @click.stop="removeTraveller(index)"
                                        class="h-7 px-2.5 rounded border border-rose-200 bg-white text-rose-600 hover:bg-rose-50 text-xs font-semibold flex items-center gap-1 transition"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                        <span>Remove</span>
                                    </button>
                                </div>
                            </AccordionTrigger>

                            <AccordionContent class="p-4 sm:p-5 bg-white border-t border-slate-100 space-y-4">
                                <!-- Quick-fill Agent Traveller + Gender Row -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded bg-slate-50 border border-slate-100">
                                    <div class="flex items-center gap-4">
                                        <span class="text-xs font-semibold text-slate-700">Gender <span class="text-rose-500">*</span></span>
                                        <RadioGroup
                                            v-model="traveller.gender"
                                            class="flex items-center gap-4"
                                            :orientation="'horizontal'"
                                        >
                                            <div class="flex items-center space-x-1.5">
                                                <RadioGroupItem id="male" value="M" class="accent-primary" />
                                                <Label for="male" class="text-xs font-medium text-slate-700 cursor-pointer">Male</Label>
                                            </div>
                                            <div class="flex items-center space-x-1.5">
                                                <RadioGroupItem id="female" value="F" class="accent-primary" />
                                                <Label for="female" class="text-xs font-medium text-slate-700 cursor-pointer">Female</Label>
                                            </div>
                                        </RadioGroup>
                                    </div>

                                    <div v-if="agentTravellers.length > 0" class="flex items-center gap-2">
                                        <span class="text-[11px] text-slate-500">Quick-Fill:</span>
                                        <Select @update:modelValue="handleSelectedTravellerAgentChange($event, index)">
                                            <SelectTrigger class="h-8 text-xs bg-white w-48 rounded border-slate-200">
                                                <SelectValue placeholder="Saved Passenger" />
                                            </SelectTrigger>
                                            <SelectContent class="rounded border-slate-200 bg-white">
                                                <SelectGroup>
                                                    <SelectItem
                                                        v-for="(agentTraveller, tIndex) in agentTravellers"
                                                        :key="agentTraveller.id"
                                                        :value="tIndex"
                                                        class="text-xs"
                                                    >
                                                        {{ agentTraveller.title }} {{ agentTraveller.first_name }} {{ agentTraveller.last_name }}
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div v-if="getErrorPath(`travellers.${index}.gender`)" class="text-rose-600 text-[11px] font-medium">
                                    {{ getErrorPath(`travellers.${index}.gender`) }}
                                </div>

                                <!-- Personal Details Grid -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3.5">
                                    <div class="space-y-1">
                                        <Label :for="`title-${index}`" class="text-xs font-semibold text-slate-700">
                                            Title <span class="text-rose-500">*</span>
                                        </Label>
                                        <Select v-model="traveller.title" :id="`title-${index}`">
                                            <SelectTrigger class="h-9 text-xs bg-white rounded border-slate-200">
                                                <SelectValue placeholder="Title" />
                                            </SelectTrigger>
                                            <SelectContent class="rounded border-slate-200 bg-white">
                                                <SelectGroup>
                                                    <SelectItem value="Mr" class="text-xs">Mr</SelectItem>
                                                    <SelectItem value="Mrs" class="text-xs">Mrs</SelectItem>
                                                    <SelectItem value="Ms" class="text-xs">Ms</SelectItem>
                                                    <SelectItem value="Miss" class="text-xs">Miss</SelectItem>
                                                    <SelectItem value="Mstr" class="text-xs">Mstr</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <div v-if="getErrorPath(`travellers.${index}.title`)" class="text-rose-600 text-[11px] font-medium">
                                            {{ getErrorPath(`travellers.${index}.title`) }}
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <Label :for="`first-name-${index}`" class="text-xs font-semibold text-slate-700">
                                            First / Given Name <span class="text-rose-500">*</span>
                                        </Label>
                                        <Input
                                            :id="`first-name-${index}`"
                                            v-model="traveller.firstName"
                                            type="text"
                                            placeholder="FIRST NAME"
                                            class="h-9 text-xs rounded border-slate-200 bg-white uppercase"
                                            @input="traveller.firstName = $event.target.value.toUpperCase()"
                                        />
                                        <div v-if="getErrorPath(`travellers.${index}.firstName`)" class="text-rose-600 text-[11px] font-medium">
                                            {{ getErrorPath(`travellers.${index}.firstName`) }}
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <Label :for="`last-name-${index}`" class="text-xs font-semibold text-slate-700">
                                            Last / Surname <span class="text-rose-500">*</span>
                                        </Label>
                                        <Input
                                            :id="`last-name-${index}`"
                                            v-model="traveller.lastName"
                                            type="text"
                                            placeholder="SURNAME"
                                            class="h-9 text-xs rounded border-slate-200 bg-white uppercase"
                                            @input="traveller.lastName = $event.target.value.toUpperCase()"
                                        />
                                        <div v-if="getErrorPath(`travellers.${index}.lastName`)" class="text-rose-600 text-[11px] font-medium">
                                            {{ getErrorPath(`travellers.${index}.lastName`) }}
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <Label :for="`date-of-birth-${index}`" class="text-xs font-semibold text-slate-700">
                                            Date of Birth <span class="text-rose-500">*</span>
                                        </Label>
                                        <Calender
                                            :id="`date-of-birth-${index}`"
                                            v-model="traveller.dob"
                                            type="date"
                                            :dob-type="traveller.type"
                                            :minValue="dobMinDate"
                                            :maxValue="todayDate"
                                            placeholder="YYYY-MM-DD"
                                            class="h-9 text-xs rounded border-slate-200 bg-white"
                                        />
                                        <div v-if="getErrorPath(`travellers.${index}.dob`)" class="text-rose-600 text-[11px] font-medium">
                                            {{ getErrorPath(`travellers.${index}.dob`) }}
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <Label :for="`nationality-${index}`" class="text-xs font-semibold text-slate-700">
                                            Nationality <span class="text-rose-500">*</span>
                                        </Label>
                                        <CountryDropdown
                                            :keyValue="'code'"
                                            placeholder="Nationality"
                                            v-model="traveller.nationality"
                                            @update:modelValue="(value) => traveller.issueCountry = value"
                                            class="h-9 text-xs rounded border-slate-200"
                                        />
                                        <div v-if="getErrorPath(`travellers.${index}.nationality`)" class="text-rose-600 text-[11px] font-medium">
                                            {{ getErrorPath(`travellers.${index}.nationality`) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Document Information Grid -->
                                <div class="pt-2 border-t border-slate-100">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2.5">
                                        Passport & Document Verification
                                    </h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3.5">
                                        <div class="space-y-1">
                                            <Label :for="`document-type-${index}`" class="text-xs font-semibold text-slate-700">
                                                Document Type <span class="text-rose-500">*</span>
                                            </Label>
                                            <Select v-model="traveller.documentType" :id="`document-type-${index}`">
                                                <SelectTrigger class="h-9 text-xs bg-white rounded border-slate-200">
                                                    <SelectValue placeholder="Passport" />
                                                </SelectTrigger>
                                                <SelectContent class="rounded border-slate-200 bg-white">
                                                    <SelectGroup>
                                                        <SelectItem value="passport" class="text-xs">Passport</SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <div v-if="getErrorPath(`travellers.${index}.documentType`)" class="text-rose-600 text-[11px] font-medium">
                                                {{ getErrorPath(`travellers.${index}.documentType`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <Label :for="`document-no-${index}`" class="text-xs font-semibold text-slate-700">
                                                Passport Number <span class="text-rose-500">*</span>
                                            </Label>
                                            <Input
                                                :id="`document-no-${index}`"
                                                v-model="traveller.documentNo"
                                                type="text"
                                                placeholder="e.g. A12345678"
                                                class="h-9 text-xs rounded border-slate-200 bg-white uppercase font-mono"
                                            />
                                            <div v-if="getErrorPath(`travellers.${index}.documentNo`)" class="text-rose-600 text-[11px] font-medium">
                                                {{ getErrorPath(`travellers.${index}.documentNo`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <Label :for="`expiry-date-${index}`" class="text-xs font-semibold text-slate-700">
                                                Passport Expiry Date <span class="text-rose-500">*</span>
                                            </Label>
                                            <Calender
                                                :id="`expiry-date-${index}`"
                                                v-model="traveller.expiryDate"
                                                :minValue="expiryMinDate"
                                                :maxValue="expiryMaxDate"
                                                type="date"
                                                placeholder="YYYY-MM-DD"
                                                class="h-9 text-xs rounded border-slate-200 bg-white"
                                            />
                                            <div v-if="getErrorPath(`travellers.${index}.expiryDate`)" class="text-rose-600 text-[11px] font-medium">
                                                {{ getErrorPath(`travellers.${index}.expiryDate`) }}
                                            </div>
                                        </div>

                                        <div class="space-y-1">
                                            <Label :for="`issue-country-${index}`" class="text-xs font-semibold text-slate-700">
                                                Issuing Country <span class="text-rose-500">*</span>
                                            </Label>
                                            <CountryDropdown
                                                :keyValue="'code'"
                                                placeholder="Issue Country"
                                                v-model="traveller.issueCountry"
                                                class="h-9 text-xs rounded border-slate-200"
                                            />
                                            <div v-if="getErrorPath(`travellers.${index}.issueCountry`)" class="text-rose-600 text-[11px] font-medium">
                                                {{ getErrorPath(`travellers.${index}.issueCountry`) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>

                <div class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 px-3 text-xs font-semibold rounded border-slate-200 text-primary hover:bg-primary/5 flex items-center gap-1.5"
                        @click="addTraveller"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Add Another Passenger</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- 4. PNR, Amount & Confirmation Section -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <CreditCard class="w-4 h-4 text-emerald-600" />
                        <span>PNR Record & Financial Settlement</span>
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Record global distribution GDS PNR locator and total payable booking ledger amount
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">GDS / Airline PNR Locator</label>
                    <Input
                        v-model="bookingPnr"
                        type="text"
                        placeholder="e.g. 6Q8XYZ"
                        class="h-9 text-xs rounded border-slate-200 bg-white font-mono uppercase font-bold text-slate-900"
                    />
                    <p class="text-[10px] text-slate-400">6-character booking reference from Sabre, Amadeus, or airline portal</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700">Total Booking Amount (PKR)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">PKR</span>
                        <Input
                            v-model="amount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="pl-12 h-9 text-xs rounded border-slate-200 bg-white font-bold text-slate-900"
                        />
                    </div>
                    <p class="text-[10px] text-slate-400">Total payable fare including all taxes and fees</p>
                </div>
            </div>

            <!-- Bottom Submit Button -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 px-3 text-xs rounded border-slate-200 text-slate-600 hover:bg-slate-50"
                    @click="router.back()"
                >
                    Cancel
                </Button>

                <Button
                    type="button"
                    class="h-9 px-6 text-xs font-bold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-2"
                    :disabled="isSubmitting"
                    @click="submitForm"
                >
                    <Ticket class="w-4 h-4" />
                    <span>{{ isSubmitting ? 'Saving Offline Booking...' : 'Save & Issue Booking' }}</span>
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
import AdminAirportSelect from '@/components/admin/AdminAirportSelect.vue';
import Calender from '@/components/common/Calender.vue';
import CountryDropdown from '@/components/common/CountryDropdown.vue';
import { useAuthStore } from "@/services/stores/auth";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import { FETCH_AIRPORTS, FETCH_TRAVELLERS, SAVE_OFFLINE_BOOKING } from "@/services/store/actions.type";
import {
    ArrowLeft,
    ArrowLeftRight,
    ArrowRight,
    Check,
    CreditCard,
    Milestone,
    PlaneLanding,
    PlaneTakeoff,
    Plus,
    Search,
    Ticket,
    Trash2,
    UserCheck,
    Users,
} from 'lucide-vue-next';

const store = useStore();
const userStore = useUserStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Flight and travellers state
const flight = ref(null);
const travellers = ref([{
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
}, {
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
}]);

const errors = reactive({
    travellers: travellers.value.map(() => ({
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
    })),
    origin: "",
    destination: "",
    start: "",
    end: "",
    multiCityTrips: [
        { origin: "", destination: "", date: "" },
        { origin: "", destination: "", date: "" }
    ]
});

const searchQuery = ref("");
const isOpen = ref(false);

const selectedAgent = ref(null);
const todayDate = ref(new Date().toISOString().split('T')[0]);

// Calendar limits (DOB / passport expiry)
function getDateInputValue(yearOffset = 0, dayOffset = 0) {
    const date = new Date();
    date.setFullYear(date.getFullYear() + yearOffset);
    date.setDate(date.getDate() + dayOffset);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}
const dobMinDate = computed(() => getDateInputValue(-100));
const expiryMinDate = computed(() => getDateInputValue(0, 1));
const expiryMaxDate = computed(() => getDateInputValue(20));
const airports = computed(() => store.getters["airport/airports"]);
const maxTotal = 9;
const countdown = ref(null);
const amount = ref("");
const bookingPnr = ref("");

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
    multiCityTrips: [
        { origin: null, destination: null, date: "" },
        { origin: null, destination: null, date: "" }
    ]
});

const totalTravelers = computed(() => localValue.value.adult + localValue.value.child + localValue.value.infant);
const maxAdults = computed(() => Math.max(1, maxTotal - localValue.value.child - localValue.value.infant));
const maxChildren = computed(() => Math.max(0, maxTotal - localValue.value.adult - localValue.value.infant));
const maxInfants = computed(() => {
    const maxByTotal = maxTotal - localValue.value.adult - localValue.value.child;
    return Math.min(localValue.value.adult, Math.max(0, maxByTotal));
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

const canRemoveTraveller = computed(() => {
    const minRequired = localValue.value.adult + localValue.value.child + localValue.value.infant;
    return travellers.value.length > minRequired;
});

const isSubmitting = ref(false);

// Helper to access error messages dynamically
const getErrorPath = (path) => {
    return path.split('.').reduce((obj, key) => obj && obj[key], errors);
};

// Validation
function validate() {
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
        gender: "" || null,
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
    if (val && errors.origin) errors.origin = "";
});
watch(() => localValue.value.destination, (val) => {
    if (val && errors.destination) errors.destination = "";
});
watch(() => localValue.value.dateRange.start, (val) => {
    if (val && errors.start) errors.start = "";
});
watch(() => localValue.value.dateRange.end, (val) => {
    if (val && errors.end) errors.end = "";
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
        if (title && errors.travellers?.[idx]?.title) errors.travellers[idx].title = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.firstName), (firstNames) => {
    firstNames.forEach((firstName, idx) => {
        if (firstName && errors.travellers?.[idx]?.firstName) errors.travellers[idx].firstName = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.lastName), (lastNames) => {
    lastNames.forEach((lastName, idx) => {
        if (lastName && errors.travellers?.[idx]?.lastName) errors.travellers[idx].lastName = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.nationality), (nationalities) => {
    nationalities.forEach((nationality, idx) => {
        if (nationality && errors.travellers?.[idx]?.nationality) errors.travellers[idx].nationality = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.documentType), (documentTypes) => {
    documentTypes.forEach((documentType, idx) => {
        if (documentType && errors.travellers?.[idx]?.documentType) errors.travellers[idx].documentType = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.documentNo), (documentNos) => {
    documentNos.forEach((documentNo, idx) => {
        if (documentNo && errors.travellers?.[idx]?.documentNo) errors.travellers[idx].documentNo = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.expiryDate), (expiryDates) => {
    expiryDates.forEach((expiryDate, idx) => {
        if (expiryDate && errors.travellers?.[idx]?.expiryDate) errors.travellers[idx].expiryDate = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.issueCountry), (issueCountries) => {
    issueCountries.forEach((issueCountry, idx) => {
        if (issueCountry && errors.travellers?.[idx]?.issueCountry) errors.travellers[idx].issueCountry = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.dob), (dobs) => {
    dobs.forEach((dob, idx) => {
        if (dob && errors.travellers?.[idx]?.dob) errors.travellers[idx].dob = "";
    });
}, { deep: true });
watch(() => travellers.value.map(t => t.gender), (genders) => {
    genders.forEach((gender, idx) => {
        if (gender && errors.travellers?.[idx]?.gender) errors.travellers[idx].gender = "";
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

// Initialize travellers
const initializeTravellers = () => {
    travellers.value = [];
    errors.travellers = [];
    const adultCount = localValue.value.adult || 1;
    const childCount = localValue.value.child || 0;
    const infantCount = localValue.value.infant || 0;

    const addTravellers = (count, type) => {
        for (let i = 0; i < count; i++) {
            travellers.value.push({
                type,
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
        }
    };

    addTravellers(adultCount, "ADT");
    addTravellers(childCount, "CNN");
    addTravellers(infantCount, "INF");
};

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

    if (travellerType === 'ADT' && localValue.value.adult > 1) {
        localValue.value.adult--;
    } else if (travellerType === 'CNN' && localValue.value.child > 0) {
        localValue.value.child--;
    } else if (travellerType === 'INF' && localValue.value.infant > 0) {
        localValue.value.infant--;
    }
};

// Route & flight type handlers
const setFlightType = (type) => {
    localValue.value.flightType = type;
    if (type === 'one-way') {
        errors.end = "";
    }
};

const swapAirports = () => {
    const temp = localValue.value.origin;
    localValue.value.origin = localValue.value.destination;
    localValue.value.destination = temp;
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
        approval_status: 'all',
        role: effectiveRole,
    });
};

const agentTravellers = computed(() => store.getters["traveller/travellers"]);
function fetchAgentTravellers() {
    store.dispatch("traveller/" + FETCH_TRAVELLERS);
}

const users = computed(() => userStore.users);

const selectAgent = (user) => {
    selectedAgent.value = user;
    searchQuery.value = '';
    isOpen.value = false;
    fetchAgentTravellers();
};

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
            amount: amount.value || "",
        };
    }
};

// Submit form
const submitForm = () => {
    isSubmitting.value = true;
    if (validate()) {
        const bookingData = {
            agentId: selectedAgent.value ? selectedAgent.value.id : null,
            flightType: localValue.value.flightType,
            route: localValue.value.flightType === 'multi-city'
                ? localValue.value.multiCityTrips
                : { origin: localValue.value.origin, destination: localValue.value.destination, dateRange: localValue.value.dateRange },
            travellers: travellers.value,
            classType: localValue.value.classType,
            adult: localValue.value.adult,
            child: localValue.value.child,
            infant: localValue.value.infant,
            amount: amount.value,
            bookingPnr: bookingPnr.value,
        };
        store.dispatch('offlineBooking/' + SAVE_OFFLINE_BOOKING, bookingData).then(() => {
            router.push({ name: "OfflineBookings" });
        });
    }
    isSubmitting.value = false;
};

// Initialize
onMounted(() => {
    fetchUsers();
    store.dispatch("airport/" + FETCH_AIRPORTS);
    initializeTravellers();
    fetchAgentTravellers();
});

// Sync travellers with adult/child/infant counts
watch(() => [localValue.value.adult, localValue.value.child, localValue.value.infant], () => {
    initializeTravellers();
});
</script>
