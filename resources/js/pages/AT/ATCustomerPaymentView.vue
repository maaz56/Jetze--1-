<template>


  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-6">
    <div class=" mx-auto">
      <div class=" bg-white border mb-6 border-gray-200 mx-auto  py-6 px-4">
        <div class="relative  flex items-center justify-between  px-4">

          <!-- Step 1 - Completed -->
          <div class="flex flex-col items-center relative z-10">
            <div
              class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-sm shadow-primary/30 flex items-center justify-center border-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <span class="mt-3 text-sm font-semibold text-gray-700">Information</span>
          </div>

          <!-- Line (Filled) -->
          <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full relative">
            <div class="absolute left-0 top-0 h-full w-full bg-primary rounded-full"></div>
          </div>

          <!-- Step 2 - Completed -->

          <div class="flex flex-col items-center relative z-10">
            <div
              class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-sm shadow-primary/30 flex items-center justify-center border-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <span class="mt-3 text-sm font-semibold text-gray-700">Addons</span>
          </div>

          <!-- Line (Filled) -->
          <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full relative">
            <div class="absolute left-0 top-0 h-full w-full bg-primary rounded-full"></div>
          </div>


          <!-- Step 3 - Current (Payment) -->
          <div class="flex flex-col items-center relative z-10">
            <div
              class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-sm shadow-primary/30 flex items-center justify-center border-2">
              <span class="text-xs font-bold">3</span>
            </div>
            <span class="mt-3 text-sm font-semibold text-gray-700">Payment</span>
          </div>

          <!-- Line (Empty) -->
          <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full"></div>

          <!-- Step 4 - Pending -->
          <div class="flex flex-col items-center relative z-10">
            <div
              class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-gray-400 flex items-center justify-center">
              <span class="text-xs font-bold">4</span>
            </div>
            <span class="mt-3 text-sm font-semibold text-gray-500">E-Ticket</span>
          </div>

        </div>
      </div>
      <div v-if="isLoading || isPaymentLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="bg-white p-6 max-w-md w-full mx-4">
          <div class="flex flex-col items-center space-y-3">
            <Spinner />
          </div>
        </div>
      </div>
      <!-- Main Container -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Payment Methods Section -->
        <div class="lg:col-span-2 space-y-4">
          <div class="bg-white rounded shadow-sm p-4">
            <!-- Header -->
            <h1 class="text-3xl font-bold text-slate-900 mb-2">How Would You Like To Pay?</h1>

            <!-- Main Payment Section with Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
              <!-- Left: Payment Methods List (Static) -->
              <div class="lg:col-span-1 space-y-3">


                <!-- Bank Transfer (Abhi Pay) -->


                <!-- Abhipay -->
                <div @click="paymentMethod = 'abhipay-bank'" :class="[
                  'flex items-center gap-4 p-4 rounded border-2 cursor-pointer transition-all duration-300',
                  paymentMethod === 'abhipay-bank'
                    ? 'bg-primary border-primary shadow-lg'
                    : 'border-primary bg-white hover:bg-primary/10'
                ]">
                  <div :class="[
                    'w-10 h-10 rounded flex items-center justify-center text-lg flex-shrink-0 font-bold',
                    paymentMethod === 'abhipay-bank'
                      ? 'bg-white text-primary'
                      : 'text-primary'
                  ]">
                    <Landmark />
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3
                      :class="['font-semibold truncate', paymentMethod === 'abhipay-bank' ? 'text-white' : 'text-slate-900']">
                      Bank Transfer - Abhipay
                    </h3>
                  </div>
                </div>
                <div @click="paymentMethod = 'abhipay'" :class="[
                  'flex items-center gap-4 p-4 rounded border-2 cursor-pointer transition-all duration-300',
                  paymentMethod === 'abhipay'
                    ? 'bg-primary border-primary shadow-lg'
                    : 'border-primary bg-white hover:bg-primary/10'
                ]">
                  <div :class="[
                    'w-10 h-10 rounded flex items-center justify-center text-lg flex-shrink-0 font-bold',
                    paymentMethod === 'abhipay'
                      ? 'bg-white text-primary'
                      : 'text-primary'
                  ]">
                    <CreditCard />
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3
                      :class="['font-semibold truncate', paymentMethod === 'abhipay' ? 'text-white' : 'text-slate-900']">
                      Abhipay - Debit/Credit card
                    </h3>
                  </div>
                </div>

                <!-- Wallet Balance -->
                <div @click="paymentMethod = 'wallet'" :class="[
                  'flex items-center gap-4 p-4 rounded border-2 cursor-pointer transition-all duration-300',
                  paymentMethod === 'wallet'
                    ? 'bg-primary border-primary shadow-lg'
                    : 'border-primary bg-white hover:bg-primary/10'
                ]">
                  <div :class="[
                    'w-10 h-10 rounded flex items-center justify-center text-lg flex-shrink-0 font-bold',
                    paymentMethod === 'wallet'
                      ? 'bg-white text-primary'
                      : 'text-primary'
                  ]">
                    <Wallet />
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3
                      :class="['font-semibold truncate', paymentMethod === 'wallet' ? 'text-white' : 'text-slate-900']">
                      Wallet Balance
                    </h3>
                  </div>
                </div>
              </div>

              <!-- Right: Dynamic Information Box Based on Selected Method -->
              <div class="lg:col-span-2">
                <!-- PayPal / PayPro Info -->
                <div v-if="paymentMethod === 'paypal'" class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          We will be redirecting you to PayPro secure Banking Payment gateway to make payment and you
                          will be redirected back to our site once payment is successful to get the Flight Booking. (No
                          additional service fee will be charged for bank transfers)
                        </p>
                      </div>
                    </div>
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Please contact our support team at <a href="mailto:support@apniticket.pk"
                            class="text-primary font-semibold hover:underline">support@apniticket.pk</a> and <a
                            href="tel:+923421111003"
                            class="text-primary font-semibold hover:underline">+923421111003</a> for any payment related
                          issues.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Bank Transfer (Abhi Pay) Info -->
                <div v-else-if="paymentMethod === 'abhipay-bank'" class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">

                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          We will be redirecting you to Abhi Pay secure gateway for bank transfer. Complete your
                          transfer and you will be redirected back to confirm your Flight Booking.
                          (Rs 500 service fee will be charged for OneBill bank transfer)
                        </p>
                         <a
    :href="$router.resolve({ name: 'HowToPay' }).href"
    target="_blank"
    rel="noopener"
    class="flex items-start gap-1 text-primary text-sm underline hover:underline hover:text-primary/80 transition"
  >
    <!-- Icon -->
    

    How To Use AbhiPay Bank Transfer
   
    <ExternalLink class="w-3 h-3 mt-1"/>
  </a>
                      </div>
                    </div>

                    <!-- 🔹 Bill ID Section -->
                    <div v-if="billId" class="bg-white border border-green-200 rounded-lg p-4 shadow-sm">
                      <p class="text-xs text-slate-500 mb-1">Generated Bill ID</p>
                      <div class="flex items-center justify-between">
                        <span class="text-lg font-bold text-green-700 tracking-wide">
                          {{ billId }}
                        </span>

                        <!-- Optional Copy Button -->
                        <button @click="copyBillId"
                          class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded transition">
                          Copy
                        </button>
                      </div>
                    </div>

                  </div>

                  <!-- Bottom Action Buttons -->
                  <div class="mt-4">

                    <!-- Buttons Row -->
                    <div class="flex gap-2">

                      <!-- Refresh / Check Status Button -->
                      <button v-if="billId" @click="checkPaymentStatus('abhipay-bank')"
                        class="w-12 flex items-center justify-center rounded bg-blue-100 hover:bg-blue-200 text-blue-700 transition disabled:opacity-50">

                        <RefreshCcw class="w-5 h-5" />

                      </button>

                      <!-- Generate / Regenerate Button -->
                      <button @click="handlePaymentMethod('abhipay-bank')" :disabled="isProcessing" :class="[
                        'flex-1 py-3 px-6 rounded font-semibold text-lg transition-all duration-300 flex items-center justify-center gap-2',
                        !isProcessing
                          ? 'bg-green-600 hover:bg-green-700 text-white shadow-sm hover:shadow-xl'
                          : 'bg-slate-300 text-slate-600 cursor-not-allowed'
                      ]">

                        <span v-if="!billId">Generate Bill ID</span>
                        <span v-else>Regenerate Bill ID</span>

                        <span>→</span>
                      </button>

                    </div>

                    <!-- 🔹 Helper Message -->
                    <p v-if="billId" class="text-xs text-slate-500 mt-2 flex items-center gap-1">

                      <RefreshCcw class="w-3 h-3" />

                      Click refresh to check payment status after completing bank transfer

                    </p>

                  </div>


                </div>


                <!-- HBL Pay Info -->
                <div v-else-if="paymentMethod === 'hbl'" class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          We will be redirecting you to HBL secure payment gateway. Enter your debit or credit card
                          details to complete payment. Service Fee of 3% will be charged for credit/debit card
                          transactions.
                        </p>
                      </div>
                    </div>
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Your card information is encrypted and secure. You will be redirected back once payment is
                          successful to get your Flight Booking confirmation.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Abhipay Info -->
                <div v-else-if="paymentMethod === 'abhipay'" class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Pay securely using your debit or credit card through Abhipay platform. Service Fee of 3% will
                          be charged for Visa/Master and Union card transactions. Fast and secure payment processing.
                        </p>
                      </div>
                    </div>
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Your payment is processed through Abhipay's encrypted gateway. You will receive confirmation
                          email immediately after successful payment completion.
                        </p>
                      </div>
                    </div>
                    <button @click="handlePaymentMethod('abhipay')" :disabled="!paymentMethod || isProcessing" :class="[
                      'w-full mt-4 py-3 px-6 rounded font-semibold text-lg transition-all duration-300 flex items-center justify-center gap-2',
                      paymentMethod && !isProcessing
                        ? 'bg-green-600 hover:bg-green-700 text-white shadow-sm hover:shadow-xl'
                        : 'bg-slate-300 text-slate-600 cursor-not-allowed'
                    ]">
                      <span>Proceed to Pay {{ formatAmount(amount) }}</span>
                      <span>→</span>
                    </button>
                  </div>
                </div>

                <!-- Wallet Info -->
                <div v-else-if="paymentMethod === 'wallet'" class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Pay directly from your Jetze wallet balance. Your current wallet balance is PKR 150,000.
                          This is the fastest payment method with instant confirmation.
                        </p>
                      </div>
                    </div>
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Your booking will be confirmed immediately. No additional fees apply for wallet payments.
                          Contact support if you have any questions.
                        </p>
                      </div>
                    </div>
                  </div>
                  <button @click="handlePaymentMethod('wallet')" :disabled="!paymentMethod || isProcessing" :class="[
                    'w-full mt-4 py-3 px-6 rounded font-semibold text-lg transition-all duration-300 flex items-center justify-center gap-2',
                    paymentMethod && !isProcessing
                      ? 'bg-green-600 hover:bg-green-700 text-white shadow-sm hover:shadow-xl'
                      : 'bg-slate-300 text-slate-600 cursor-not-allowed'
                  ]">
                    <span>Proceed to Pay {{ formatAmount(amount) }}</span>
                    <span>→</span>
                  </button>
                </div>

                <!-- Default/Placeholder Info -->
                <div v-else class="bg-blue-50 rounded p-6 border border-blue-100">
                  <div class="space-y-4">
                    <div class="flex gap-3">
                      <div class="text-green-600 font-bold text-lg flex-shrink-0 mt-0.5">✓</div>
                      <div>
                        <p class="text-sm text-slate-700 leading-relaxed">
                          Select a payment method to see the available options and instructions.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Security Badge -->
                <div class="mt-4 bg-amber-50 border border-amber-300 rounded p-4 flex items-center gap-3">
                  <span class="text-2xl flex-shrink-0">🔒</span>
                  <p class="text-sm text-amber-900">
                    <span class="font-semibold">All Your personal information is secure</span> when you book with
                    Jetze
                  </p>
                </div>

                <!-- Payment Button -->

              </div>
            </div>
          </div>

          <!-- Trust Badges -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
            <div class="bg-white rounded p-6 text-center shadow-sm">
              <div class="text-3xl mb-3">🔐</div>
              <h4 class="font-bold text-slate-900 mb-2">100% Secure</h4>
              <p class="text-sm text-slate-600">We use 256-bit SSL encryption</p>
            </div>
            <div class="bg-white rounded p-6 text-center shadow-sm">
              <div class="text-3xl mb-3">✓</div>
              <h4 class="font-bold text-slate-900 mb-2">Trusted worldwide</h4>
              <p class="text-sm text-slate-600">We do not store or view your card data</p>
            </div>
            <div class="bg-white rounded p-6 text-center shadow-sm">
              <div class="text-3xl mb-3">💳</div>
              <h4 class="font-bold text-slate-900 mb-2">Easy Payments</h4>
              <p class="text-sm text-slate-600">We do not store or view your card data</p>
            </div>
          </div>

          <!-- Footer Info -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
            <div class="bg-white rounded p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">📍</span>
                <h4 class="font-bold text-slate-900">Address</h4>
              </div>
              <p class="text-sm text-slate-600">Jetze</p>
            </div>
            <div class="bg-white rounded p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">📞</span>
                <h4 class="font-bold text-slate-900">Contact Us :</h4>
              </div>
              <p class="text-sm text-slate-600">+92 311 1711123</p>
            </div>
            <div class="bg-white rounded p-6 shadow-sm">
              <div class="flex items-center gap-3 mb-3">
                <span class="text-2xl">📧</span>
                <h4 class="font-bold text-slate-900">Email :</h4>
              </div>
              <p class="text-sm text-slate-600">support@Jetze.pk</p>
            </div>
          </div>
        </div>

        <!-- Flight Summary Section -->
        <div class="lg:col-span-1">
          <div class="sticky top-6 space-y-6">
            <!-- Summary Card -->
            <div class="bg-white rounded shadow-sm p-6">
              <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-900">Flight summary</h2>
                <span class="text-xs font-semibold text-primary bg-amber-100 px-3 py-1 rounded-full">
                  Flight details
                </span>
              </div>

              <div v-if="passengers.length" class="mb-6 rounded border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between mb-3">
                  <h3 class="text-sm font-semibold text-slate-900">Passengers</h3>
                  <span class="text-xs font-medium text-slate-500">{{ passengers.length }} Total</span>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="(passenger, passengerIdx) in passengers"
                    :key="passenger.id || passengerIdx"
                    class="flex items-center justify-between rounded bg-white px-3 py-2"
                  >
                    <p class="text-sm font-medium text-slate-900">
                      {{ formatPassengerName(passenger) }}
                    </p>
                    <span class="text-xs uppercase tracking-wide text-slate-500">
                      {{ passenger.type || 'PAX' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Flight Route -->
              <div class="">
                <div v-for="(flight, flightIdx) in flightData?.leg?.flights" :key="flightIdx" class="mb-8 last:mb-0">
                  <!-- Route Header -->
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                      <img :src="flight.marketing_carrier?.logo" :alt="flight.marketing_carrier?.name"
                        class="h-8 w-8 rounded-full border border-gray-200" />
                      <div>
                        <p class="font-medium text-gray-900">{{ flight.marketing_carrier?.name }}</p>
                        <p class="text-xs text-gray-500">Flight {{ flight.segments?.[0]?.flight_number }}</p>
                      </div>
                    </div>
                    <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                      Cabin Class: {{ flight.segments?.[0]?.cabin_class === 'E' ? 'Economy' :
                        flight.segments?.[0]?.cabin_class ||
                      'Economy' }}
                    </span>
                  </div>

                  <!-- Flight Segments -->
                  <div v-for="(segment, sIdx) in flight.segments" :key="sIdx" class="relative">
                    <!-- Flight Segment -->
                    <div class="flex flex-col md:flex-row md:items-center gap-4 p-4 bg-gray-50 rounded">
                      <!-- Departure -->
                      <div class="flex-1">
                        <div class="flex items-start gap-3">
                          <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                          <div>
                            <p class="text-sm font-semibold text-gray-900">
                              {{ formatDateTime(segment.departure_at) }}
                            </p>
                            <p class="text-base font-bold text-gray-900 mt-1">
                              {{ segment.from?.iata }}
                            </p>
                            <p class="text-xs text-gray-600">{{ segment.from?.name }}</p>
                          </div>
                        </div>
                      </div>
                      <!-- Flight Path -->
                      <div class="flex-1 flex flex-col items-center">
                        <p class="text-xs text-gray-500 mb-1">{{ formatDuration(flight.travel_time) }}</p>
                        <div class="w-full flex items-center">
                          <div class="w-2 h-2 bg-primary rounded-full"></div>
                          <div class="flex-1 h-0.5 bg-gradient-to-r from-primary to-primary/30"></div>
                          <div class="w-2 h-2 bg-primary rounded-full"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Direct</p>
                      </div>

                      <!-- Arrival -->
                      <div class="flex-1 text-right">
                        <div class="flex items-start justify-end gap-3">
                          <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">
                              {{ formatDateTime(segment.arrival_at) }}
                            </p>
                            <p class="text-base font-bold text-gray-900 mt-1">
                              {{ segment.to?.iata }}
                            </p>
                            <p class="text-xs text-gray-600">{{ segment.to?.name }}</p>
                          </div>
                          <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                        </div>
                      </div>
                    </div>
                  </div>


                </div>
              </div>



              <!-- Fare Breakdown -->
              <div class="lg:col-span-1">
                <div class="bg-white shadow-sm border border-gray-200 p-2 overflow-hidden">
                  <div class="flex p-4 items-center justify-between">
                    <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Price Details</h3>
                  </div>
                  <div class="">
                    <div v-for="(flight, flightIndex) in flightData?.leg?.flights" :key="flightIndex">
                      <div
                        class="text-xs sm:text-sm font-semibold text-gray-900 my-1 sm:my-2 flex items-center gap-1 sm:gap-2 px-2">
                        <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 bg-primary rounded-full"></div>
                        {{ flight.from.iata }} → {{ flight.to.iata }}
                      </div>
                      <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                        <div v-if="selectedFares?.includes(fare.ref_id)" class="">
                          <Accordion class="" type="multiple" collapsible>
                            <template v-for="(passengerFare, index) in fare.passenger_fares" :key="index">
                              <AccordionItem :value="`fare-${flightIndex}-${fareIndex}-${index}`"
                                class="overflow-hidden">
                                <AccordionTrigger
                                  class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center hover:no-underline gap-1">
                                  <div class="flex items-center gap-2">
                                    <span class="text-xs sm:text-sm font-bold text-gray-600">
                                      {{ passengerFare.traveler_type }} X {{ passengerFare.total_passenger }}
                                    </span>
                                  </div>
                                  <span
                                    class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                                    {{ formatAmount(parseFloat(passengerFare.base_price || 0) +
                                      parseFloat(passengerFare.surchage || 0) +
                                      parseFloat(passengerFare.taxes || 0) +
                                      parseFloat(passengerFare.fees || 0) +
                                      parseFloat(passengerFare.service_charges || 0) +
                                      parseFloat(passengerFare.ancillaries_charges || 0) +
                                      ((calculateFareMargin(
                                        parseFloat(passengerFare.base_price) || 0,
                                        fare.margin_amount,
                                        fare.margin_type,
                                        fare.amount_type,
                                      ) +
                                        calculateCustomerMargin(
                                          parseFloat(passengerFare.base_price) || 0,
                                        )) * passengerFare?.total_passenger))
                                    }}
                                  </span>
                                </AccordionTrigger>
                                <AccordionContent class="px-3 sm:px-4 sm:pr-7 pb-3 space-y-2">
                                  <div class="flex justify-between items-center">
                                    <span class="text-xs sm:text-sm text-gray-600">Base Fare</span>
                                    <span class="text-xs sm:text-sm font-medium">
                                      {{ formatAmount(
                                        ((calculateFareMargin(
                                          parseFloat(passengerFare?.base_price) || 0,
                                          fare?.margin_amount,
                                          fare?.margin_type,
                                          fare?.amount_type,
                                        ) +
                                          parseFloat(CustomerMargin?.other_charges || 0) +
                                          parseFloat(calculateCustomerMargin(
                                            passengerFare?.base_price,
                                            CustomerMargin?.value?.discount || 0,
                                            CustomerMargin?.value?.margin_amount || 0,
                                          ))) *
                                          passengerCount) +
                                        parseFloat(passengerFare?.base_price || 0)
                                      ) }}
                                    </span>
                                  </div>
                                  <div class="flex justify-between items-center">
                                    <span class="text-xs sm:text-sm text-gray-600">Taxes</span>
                                    <span class="text-xs sm:text-sm font-medium">{{ formatAmount(passengerFare?.taxes)
                                      }}</span>
                                  </div>
                                  <div class="flex justify-between items-center">
                                    <span class="text-xs sm:text-sm text-gray-600">Fees</span>
                                    <span class="text-xs sm:text-sm font-medium">{{ formatAmount(passengerFare?.fees)
                                      }}</span>
                                  </div>
                                  <div class="flex justify-between items-center">
                                    <span class="text-xs sm:text-sm text-gray-600">Service Charges</span>
                                    <span class="text-xs sm:text-sm font-medium">{{
                                      formatAmount(passengerFare.service_charges) }}</span>
                                  </div>
                                  <hr class="border-dashed border-gray-300" />
                                  <div class="flex justify-between items-center rounded">
                                    <span class="text-xs sm:text-sm font-medium text-gray-700">Amount</span>
                                    <span class="text-sm sm:text-base font-bold text-primary">
                                      {{ formatAmount(
                                        parseFloat(passengerFare.base_price || 0) +
                                        parseFloat(passengerFare.surchage || 0) +
                                        parseFloat(passengerFare.taxes || 0) +
                                        parseFloat(passengerFare.fees || 0) +
                                        parseFloat(passengerFare.service_charges || 0) +
                                        parseFloat(passengerFare.ancillaries_charges || 0) +
                                        ((calculateFareMargin(
                                          parseFloat(passengerFare.base_price) || 0,
                                          fare.margin_amount,
                                          fare.margin_type,
                                          fare.amount_type,
                                        ) +
                                          calculateCustomerMargin(
                                            parseFloat(passengerFare.base_price) || 0,
                                          )) * passengerFare?.total_passenger))
                                      }}
                                    </span>
                                  </div>
                                </AccordionContent>
                              </AccordionItem>
                            </template>
                          </Accordion>
                          <div class="flex justify-between items-center bg-gray-50 p-2 sm:px-4 rounded">
                            <span class="text-xs sm:text-sm font-bold text-gray-700">Amount</span>
                            <span class="text-sm sm:text-base font-bold text-primary">
                              {{ formatAmount(calculateTotalFare(fare) + marginPerFlight) }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="flex p-2 justify-between items-center">
                    <span class="text-xs sm:text-sm text-gray-600">Add-ons</span>
                    <span class="text-xs sm:text-sm font-medium">{{ formatAmount(bookingDetails?.[0]?.add_ones_amount ||
                      0) }}</span>
                  </div>
                  <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                    <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                    <span class="text-sm sm:text-lg font-bold text-primary">{{ formatAmount(amount) }}</span>
                  </div>
                </div>
              </div>
            </div>
  <div class="bg-amber-50 border border-amber-200 rounded p-4">
              <div class="flex items-center gap-3">
                <ClockIcon class="h-5 w-5 text-amber-600" />
                <div>
                  <p class="text-sm font-medium text-amber-800">Booking expires in</p>
                  <p class="text-lg font-bold text-amber-900">{{getRemainingTime( bookingDetails?.[0]?.expiry_time) }}</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
    <div v-if="isLowBalanceDialogOpen"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="isLowBalanceDialogOpen = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-start justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
          <button @click="isConfirmDialogOpen = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="mt-2">
          <p class="text-sm text-gray-500">
            You balance is insufitient to confirm this booking. Please add funds to your
            account.
          </p>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button @click="isLowBalanceDialogOpen = false"
            class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            Cancel
          </button>
          <button @click="$router.push({
            name: 'CustomerProfile',
            query: {
              tab: 'deposits'
            }

          })"
            class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            Go To Desposit
          </button>
        </div>
      </div>
    </div>
    <div v-if="showPaymentDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium mb-4">Pay with Card</h3>
        <div id="card-element" class="mb-4 p-3 border rounded bg-gray-50"></div>
        <div v-if="paymentError" class="text-red-500 text-sm mb-3">{{ paymentError }}</div>
        <div class="flex justify-end gap-3">
          <Button @click="closePaymentDialog">Cancel</Button>
          <Button @click="handlePayment" :disabled="processing" class="bg-primary text-white">
            <Spinner v-if="processing" class="mr-2 h-4 w-4" />
            Pay {{ formatAmount(amount) }}
          </Button>
        </div>
      </div>
    </div>
    <!-- Confirm Dialog -->
    <div v-if="isConfirmDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isConfirmDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                                <button @click="isConfirmDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to confrim this booking?
                                </p>

                                <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isConfirmDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="confirmBooking"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Booking
                                </button>
                            </div>
                        </div>
                    </div>
    <!-- Payment Modal (Dummy) -->
    <Teleport to="body">
      <div v-if="showPaymentModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
        @click.self="showPaymentModal = false">
        <div class="bg-white rounded shadow-2xl max-w-md w-full p-8">
          <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-4 bg-primary/10 rounded-full flex items-center justify-center">
              {{ selectedMethod?.icon }}
            </div>
            <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ paymentModalTitle }}</h2>
            <p class="text-slate-600 text-sm">{{ paymentModalDescription }}</p>
          </div>

          <div class="bg-slate-50 rounded p-4 mb-6 text-center">
            <p class="text-sm text-slate-600 mb-1">Amount to Pay</p>
            <p class="text-3xl font-bold text-primary">{{ currency }} {{ formatAmount(amount) }}</p>
          </div>

          <div class="space-y-3 mb-6">
            <input type="email" placeholder="Email Address"
              class="w-full px-4 py-3 border border-slate-300 rounded focus:outline-none focus:ring-2 focus:ring-primary" />
            <div v-if="selectedMethod?.id !== 'wallet'" class="text-xs text-slate-600 bg-blue-50 p-3 rounded">
              You will be redirected to the payment gateway. Please do not close the window.
            </div>
          </div>

          <div class="flex gap-3">
            <button @click="showPaymentModal = false"
              class="flex-1 px-4 py-3 border border-slate-300 rounded font-semibold text-slate-900 hover:bg-slate-50 transition-colors">
              Cancel
            </button>
            <button @click="completePayment" :disabled="isProcessing"
              class="flex-1 px-4 py-3 bg-primary hover:bg-primary/90 disabled:bg-slate-300 text-white rounded font-semibold transition-colors">
              {{ isProcessing ? 'Processing...' : 'Confirm Payment' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>

// State
const selectedMethod = ref(null)
const showFareDetails = ref(false)
const showPaymentModal = ref(false)
const isProcessing = ref(false)
const paymentMethod = ref('wallet')

// Fare data
const currency = ref('PKR')
const passengerFare = ref(0)
const seatCharge = ref(26279)
const serviceFee = ref(150)
const totalFare = computed(() => passengerFare.value + seatCharge.value + serviceFee.value)
import Button from "@/components/ui/button/Button.vue";
import { Plane, Users, Briefcase, ClockIcon, PrinterIcon, X, RefreshCcw, Landmark, CreditCard, Wallet, ExternalLink } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import { computed, onMounted, ref, nextTick, watch } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import html2pdf from "html2pdf.js";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";
import { loadStripe } from "@stripe/stripe-js";
import axios from "axios";
import {
  FETCH_BOOKING_DETAILS,
  FETCH_AGENT_DATA,
  FETCH_AGENT_LEDGER,
  SEND_EMAIL,
  CANCEL_BOOKING,
  CONFIRM_BOOKING,
  VOID_REQUEST,
  SEND_PAYMENT_REQUEST,
  FETCH_ANCILLARIES,
  PATCH_ANCILLARIES,
  FETCH_CUSTOMER_MARGIN,
  INITIALIZE_ABHI_PAY,
  CHECK_PAYMENT_STATUS,
} from "@/services/store/actions.type";
import { cn, formatAmount, calculateFinalPrice, calculateTypeMargin } from "@/lib/utils";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

import { toast } from "vue3-toastify";
import { reactive } from "vue";
import { c } from "naive-ui"

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Reactive state
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const isLoading = computed(() =>
  store.getters["flight/isLoading"]
);
const isPaymentLoading = computed(() =>
  store.getters["payment/isLoading"]
);

const error = ref(null);
const custEmail = ref("");
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const isChatOpen = ref(false);

const isDetailsInfoVisible = ref(true);

// Payment-related state
const showPaymentDialog = ref(false);
const processing = ref(false);
const paymentError = ref("");
const clientSecret = ref("");
const amount = ref(0);
const loading = ref(false);
const extraTotal = ref(0);
const typeMargin = ref(0);
const customerMarginAmt = ref(0);
const selectedFares = ref([]);
const baseAmount = ref(0);
const payment = ref(route?.query?.payment || null);

// Stripe
const stripe = ref(null);
const elements = ref(null);
const cardElement = ref(null);
const billId = ref();
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);

// Computed
const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const isConfirmed = computed(() => store.getters["flight/isConfirmed"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const airportMargins = computed(() => store.getters["airport/airportMargin"] || {});
const abhiPayResponse = computed(() => store.getters["payment/abhiPayResponse"]);
const paymentStatus = computed(() => store.getters["payment/paymentStatus"]);

// Extra Services Dialog State - FIXED
const isExtraServicesOpen = ref(false);
// Initialize selectedAncillaries as a ref with proper structure
const selectedAncillaries = ref({}); // { passengerIndex: { groupCode: itemCode } }
const selectedAncillariesList = reactive([])

// Ancillaries computed properties - FIXED
const ancillariesData = computed(() => {
  try {
    return ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult || null;
  } catch {
    return null;
  }
});
const ancillarySegments = computed(() => {
  try {
    const data = ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult?.AncillaryItemResponses?.AncillaryItemResponse;
    return Array.isArray(data) ? data : (data ? [data] : []);
  } catch (e) {
    console.error("Failed to parse ancillary segments", e);
    return [];
  }
});
const CustomerMargin = computed(
  () => store.getters["customerMargin/customerMargin"],
);
const ancillaryGroups = computed(() => {
  // if (!ancillariesData.value?.AncillaryItemResponses?.AncillaryItemResponse?.AncillaryItemSets?.AncillaryItemSet) {
  //   return [];
  // }

  const sets = ancillariesData.value.AncillaryItemResponses.AncillaryItemResponse;
  return Array.isArray(sets) ? sets : [sets];
});

const passengers = computed(() => getPassengers(bookingDetails.value?.[0]) || []);
function fetchCustomerMarginValues() {
  store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}
// Extra Services Total - FIXED


// Helper function to find ancillary item by code
const findAncillaryItem = (itemCode) => {
  for (const group of ancillaryGroups.value) {
    const items = group.AncillaryItems?.AncillaryItem;
    if (items) {
      const itemArray = Array.isArray(items) ? items : [items];
      const foundItem = itemArray.find(item => item['@attributes'].ItemCode === itemCode);
      if (foundItem) return foundItem;
    }
  }
  return null;
};

const pnrData = ref(null);
const sooperResponse = ref(null);
const flightData = ref(null);
let bookingSource = route.query.booking_source;
let flight_provider = route.query.flight_provider || route.query.provider || null;
let booking_id = route.query.booking_id;
let pnr = route.query.pnr;
const now = ref(Date.now())
const passengerCount = ref(1);
const agentAmount = ref(0);
const agentDiscount = ref(0);
const margin = ref(0);
const airportMargin = ref(0);
const savedMarginTotal = computed(() => {
  return (agentAmount.value + margin.value + airportMargin.value - agentDiscount.value) || 0;
});

onMounted(() => {
  setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

const getRemainingTime = (expiry) => {
  if (!expiry) return 'N/A'

  // Parse expiry and get difference
  const expiryTime = new Date(expiry.replace(' ', 'T')).getTime()
  const diff = expiryTime - now.value
  if (diff <= 0) return 'Expired'

  // Calculate days, hours, minutes, seconds
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  const seconds = Math.floor((diff % (1000 * 60)) / 1000)

  let result = ''
  if (days > 0) result += `${days}d `
  if (hours > 0 || days > 0) result += `${hours}h `
  result += `${minutes}m ${seconds.toString().padStart(2, '0')}s`

  return result
}
// Fetch functions
async function fetchAgent() {
  if (!user_id.value) return;
  try {
    await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: user_id.value });
  } finally {
    isAgentLoading.value = false;
  }
}

async function fetchAgentLedger() {
  if (!user_id.value) return;
  await store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, { userId: user_id.value });
}

async function fetchBookingDetails() {
  if (!booking_id) {
    error.value = "No booking ID provided.";
    isBookingDetailsLoading.value = false;
    return;
  }
  try {
    await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, {
      bookingId: booking_id,
      bookingSource: route.query.booking_source,
    });
    parseResponses();
    flightData.value = parseFlightData(bookingDetails.value[0]?.flight_data);
    calculateGrandTotal();
    // fetchAncillaries();
  } catch (err) {
    error.value = "Failed to fetch booking details.";
    toast.error(error.value);
  } finally {
    isBookingDetailsLoading.value = false;
  }
}


function toggleSegmentAncillary(segIdx, pIdx, groupCode, item, event, paxType) {
  const itemCode = item['@attributes'].ItemCode;
  const amount = Number(item['@attributes'].ChargeAmount || 0);
  const currency = item['@attributes'].ChargeCurrency || 'PKR';

  const key = { segIdx, pIdx, groupCode, itemCode };

  if (event.target.type === 'checkbox') {
    if (event.target.checked) {
      selectedAncillariesList.push({ ...key, item, amount, currency, paxType });
    } else {
      const idx = selectedAncillariesList.findIndex(x =>
        x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
      );
      if (idx > -1) selectedAncillariesList.splice(idx, 1);
    }
  } else {
    // Radio: clear others in same group, same passenger, same segment
    for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
      if (selectedAncillariesList[i].segIdx === segIdx &&
        selectedAncillariesList[i].pIdx === pIdx &&
        selectedAncillariesList[i].groupCode === groupCode) {
        selectedAncillariesList.splice(i, 1);
      }
    }
    selectedAncillariesList.push({ ...key, item, amount, currency, paxType });
  }
}
function getItemsArray(items) {
  return Array.isArray(items) ? items : [items];
}

// Updated total
const extraServicesTotal = computed(() => {
  return selectedAncillariesList.reduce((sum, anc) => sum + anc.amount, 0);
});
function hasAnySelectionInGroup(segIdx, pIdx, groupCode) {
  return selectedAncillariesList.some(x =>
    x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode
  );
}

// Apply and send to backend
function applyAncillariesAndClose() {
  patchAncillaryCharges();
  isExtraServicesOpen.value = false;
}
function isSegmentSelected(segIdx, pIdx, groupCode, itemCode) {
  return selectedAncillariesList.some(x =>
    x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
  );
}
function clearSegmentGroup(segIdx, pIdx, groupCode) {
  for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
    if (selectedAncillariesList[i].segIdx === segIdx &&
      selectedAncillariesList[i].pIdx === pIdx &&
      selectedAncillariesList[i].groupCode === groupCode) {
      selectedAncillariesList.splice(i, 1);
    }
  }
}
function clearRadioSelection(pIdx, groupCode) {
  for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
    if (selectedAncillariesList[i].pIdx === pIdx && selectedAncillariesList[i].groupCode === groupCode) {
      selectedAncillariesList.splice(i, 1)
    }
  }
}

function removeSelectedSeat(segmentRPH, passengerIndex) {
  const key = `${segmentRPH}-${passengerIndex}`
  delete selectedSeats.value[key]
}

function parseResponses() {
  const booking = bookingDetails.value?.[0];
  if (!booking) return;
  try {
    const parsed = JSON.parse(booking.fare_reference || "[]");
    selectedFares.value = Array.isArray(parsed) ? parsed : [];
  } catch {
    selectedFares.value = [];
  }

  try { pnrData.value = JSON.parse(booking.pnr_response || null); } catch { }
  try { sooperResponse.value = JSON.parse(booking.sooper_response || null); } catch { }
}

function parseFlightData(data) {
  try { return JSON.parse(data); } catch { return {}; }
}

// Fare Calculations
function calculatePassengerFare(passenger, flightIndex) {
  const flight = flightData.value?.original?.leg?.flights?.[flightIndex] ?? flightData.value?.leg?.flights?.[flightIndex];
  const fare = flight?.fares?.[0];
  if (!fare) return 0;

  const basePrice = parseFloat(passenger?.base_price || 0);
  const systemFare = calculateFinalPrice(basePrice, fare.margin_amount, fare.margin_type, fare.amount_type);
  const agentMargin = parseFloat(agentData.value?.agent_data?.margin_amount || 0);
  const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0);

  return systemFare + agentMargin - agentDiscount;
}

function calculatePassengerTaxes(passenger) {
  return (
    parseFloat(passenger?.fee || 0) +
    parseFloat(passenger?.taxes || 0) +
    parseFloat(passenger?.surcharge || 0) +
    parseFloat(passenger?.service_charges || 0) +
    parseFloat(passenger?.ancillaries_charges || 0)
  );
}

function calculatePassengerFinalFare(passenger, flightIndex) {
  return calculatePassengerFare(passenger, flightIndex) + calculatePassengerTaxes(passenger);
}

function calculatePnrFinalFare() {
  const legs = pnrData.value?.data?.providers?.[0]?.legs || [];
  if (!legs.length) return 0;

  const flights = flightData.value?.original?.leg?.flights ?? flightData.value?.leg?.flights ?? [];
  const uniqueCarriers = {};
  flights.forEach(f => { if (f?.marketing_carrier?.name) uniqueCarriers[f.marketing_carrier.name] = f; });

  const systemFare = legs.reduce((legSum, leg) => {
    return legSum + (leg.passengers || []).reduce((pSum, p) => {
      const base = parseFloat(p?.base_price || 0);
      const carrierFare = Object.values(uniqueCarriers).reduce((cSum, f) => {
        const fare = f?.fares?.[0];
        return fare ? cSum + calculateFinalPrice(base, fare.margin_amount, fare.margin_type, fare.amount_type) : cSum;
      }, 0);
      return pSum + carrierFare;
    }, 0);
  }, 0);

  const extraCharges = legs.reduce((total, leg) => total + (leg.passengers || []).reduce((s, p) => s + calculatePassengerTaxes(p), 0), 0);
  const passengerCount = legs[0]?.passengers?.length || 0;
  const agentMargin = parseFloat(agentData.value?.agent_data?.margin_amount || 0) * legs.length * passengerCount;
  const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0) * legs.length * passengerCount;

  return systemFare + extraCharges + agentMargin - agentDiscount;
}

// Helpers
const getFlightLegs = (booking) => {
  const data = parseFlightData(booking?.flight_data);
  return data?.original?.leg?.flights ?? data?.leg?.flights ?? [];
};

const getPassengers = (booking) => {
  if (sooperResponse.value?.data?.booking?.booking?.providers?.[0]?.legs) {
    return sooperResponse.value.data.booking.booking.providers[0].legs
      .flatMap(l => l.passengers.map(p => p.passenger));
  }
  return booking?.pessangers ?? [];
};

const getFareLegs = () => pnrData.value?.data?.providers?.[0]?.legs ?? [];
const getBaggageLegs = () => pnrData.value?.data?.providers?.[0]?.legs ?? [];
const getBaggage = (pax, type) => pax.baggage_policies?.find(bp => bp.type === type)?.description ?? 'N/A';

const formatDateTime = (date) => moment.parseZone(date).format('DD MMM YYYY, HH:mm');
const formatDuration = (minutes) => moment.utc(moment.duration(minutes, 'minutes').asMilliseconds()).format('HH[h] mm[m]');
const formatPassengerName = (passenger) => {
  return [passenger?.title, passenger?.first_name, passenger?.last_name]
    .filter(Boolean)
    .join(' ');
};
const formatFlightDuration = (segment) => {
  const diff = moment(segment.arrival_at).diff(moment(segment.departure_at), 'minutes');
  return moment.utc(moment.duration(diff, 'minutes').asMilliseconds()).format('HH[h] mm[m]');
};

const addExtraServicesToBooking = () => {
  const selected = extraServices.value.filter(s => s.selected);

  extraTotal.value = extraServicesTotal.value;

  // update booking total

  isExtraServicesOpen.value = false;

};
const isSeatMapOpen = ref(false)

// Selected seats state
const selectedSeats = ref({})

// Process seat map data
const processedSeatMapData = computed(() => {
  if (!ancillaries.value?.seatMap) return []

  const seatMapResponse = ancillaries.value.seatMap.Body?.AirSeatMapResponse?.AirSeatMapResult?.SeatMapResponses?.SeatMapResponse

  if (!seatMapResponse) return []

  // Convert to array if it's an object
  return Array.isArray(seatMapResponse) ? seatMapResponse : [seatMapResponse]
})

// Get seat CSS classes
const getSeatClasses = (seat, segmentIndex, traveler, rowNumber, seatNumber) => {
  const classes = []
  const TravelerRefNumber =
    traveler?.TravelerRefNumber?.["@attributes"]?.RPH
  // Base classes
  if (seat.Availability === 'NoSeatHere') {
    classes.push('bg-gray-100 border-gray-300 cursor-default')
  } else if (seat.Availability === 'SeatOccupied') {
    classes.push('bg-red-100 border-red-500 cursor-not-allowed')
  } else if (isSeatSelected(segmentIndex, TravelerRefNumber, rowNumber, seatNumber)) {
    classes.push('bg-primary/20 border-primary shadow-sm')
  } else {
    classes.push('bg-green-100 border-green-500 hover:bg-green-200')
  }

  // Feature-based classes
  if (seat.Features) {
    if (seat.Features.includes('ExitRow')) {
      classes.push('border-orange-300')
    }
    if (seat.Features.includes('Overwing')) {
      classes.push('border-blue-300')
    }
  }

  return classes.join(' ')
}

// Get seat tooltip
const getSeatTooltip = (seat) => {
  if (seat.Availability === 'NoSeatHere') return 'No seat'

  const features = []
  let price = 'Free'

  if (seat.Features) {
    if (seat.Features.includes('Window')) features.push('Window')
    if (seat.Features.includes('Aisle')) features.push('Aisle')
    if (seat.Features.includes('ExitRow')) features.push('Exit Row')
    if (seat.Features.includes('Overwing')) features.push('Overwing')
  }

  if (seat.Service?.Fee?.['@attributes']?.Amount) {
    price = `${seat.Service.Fee['@attributes'].Amount} ${seat.Service.Fee['@attributes'].CurrencyCode}`
  }

  const status = seat.Availability === 'SeatOccupied' ? 'Occupied' : 'Available'
  const featureText = features.length > 0 ? `Features: ${features.join(', ')}` : ''

  return `${status} | ${price}${featureText ? ` | ${featureText}` : ''}`
}

// Check if seat is selected
const isSeatSelected = (segmentIndex, passengerIndex, rowNumber, seatNumber) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber
}

// Select seat
const selectSeat = (segmentRPH, traveler, rowNumber, seatNumber, seatData) => {
  if (seatData.Availability === 'NoSeatHere' || seatData.Availability === 'SeatOccupied') {
    return
  }
  const TravelerRefNumber =
    traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null;
  const key = `${segmentRPH}-${TravelerRefNumber}`

  // If already selected, deselect it
  if (selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber) {
    delete selectedSeats.value[key]
  } else {
    // Select new seat
    selectedSeats.value[key] = {
      row: rowNumber,
      seat: seatNumber,
      travelerRefNumber: TravelerRefNumber,
      RPH: segmentRPH,
      price: seatData.Service?.Fee?.['@attributes']?.Amount || 0,
      currency: seatData.Service?.Fee?.['@attributes']?.CurrencyCode || 'PKR',
      features: seatData.Features ? seatData.Features.filter(f => !f.includes('Other_')).join(', ') : ''
    }
  }
}

// Get selected seat info
const getSelectedSeat = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  const selected = selectedSeats.value[key]
  return selected ? `${selected.row}${selected.seat}` : null
}

const getSelectedSeatPrice = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.price || 0
}

const getSelectedSeatFeatures = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.features || ''
}

// Total seats cost
const totalSeatsCost = computed(() => {
  return Object.values(selectedSeats.value).reduce((total, seat) => {
    return total + (parseFloat(seat.price) || 0)
  }, 0)
})

// Get passenger traveler reference
const getPassengerTravelerRef = (pIdx) => {
  if (!pnrData.value?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler) return null;

  const travelers = pnrData.value.Body.AirBookResponse.AirBookResult.AirReservation.TravelerInfo.AirTraveler;
  const traveler = Array.isArray(travelers) ? travelers[pIdx] : travelers;

  return traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null;
}

// Format date time

// Dialog methods
const openSeatMap = () => {
  isSeatMapOpen.value = true
}

const closeSeatMap = () => {
  isSeatMapOpen.value = false
}

// Confirm seat selection
const confirmSeatSelection = () => {
  const seatData = {
    selectedSeats: selectedSeats.value,
    totalCost: totalSeatsCost.value,
    segments: processedSeatMapData.value.map((segment, index) => ({
      segmentIndex: index,
      flightNumber: segment.FlightSegmentInfo['@attributes'].FlightNumber,
      departure: segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode,
      arrival: segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode
    }))
  }

  store.dispatch("flight/" + PATCH_ANCILLARIES, {
    flight_provider: flight_provider,
    pnr: pnrData.value,
    selectedSeats: seatData,
  })
  closeSeatMap()
}

// Reset selections when dialog closes
watch(isSeatMapOpen, (newVal) => {
  if (!newVal) {
    // Optional: Keep selections or reset them
    // selectedSeats.value = {}
  }
})
// Initialize selectedAncillaries when passengers change - NEW
watch(passengers, (newPassengers) => {
  if (newPassengers.length > 0) {
    // Initialize structure for each passenger
    const initialSelections = {};
    newPassengers.forEach((_, index) => {
      initialSelections[index] = {};
    });
    selectedAncillaries.value = initialSelections;
  }
}, { immediate: true });

watch(payment, () => {
  console.log("Payment query param changed:", payment.value);
  if (payment.value == true) {
    checkPaymentStatus();
  }
})

watch(paymentMethod, () => {
  // Recalculate using the latest base amount, then apply payment-method adjustments.
  calculateGrandTotal();
});

// Payment Methods Handler
function handlePaymentMethod(type) {
  paymentMethod.value = type;
  // Ensure `amount` is up-to-date before any payment initialization call.
  calculateGrandTotal();

  if (type === "wallet") {
    //  confirmBooking();
    handleConfirmDialogOpen();
  } else if (type === "stripe") {
    openPaymentDialog();
  } else if (type === "alrajhi") {
    payNow();
  } else if (type === "abhipay" || type === "abhipay-bank") {
    initializeAbhiPay();
  }

}
async function initializeAbhiPay() {
  store.dispatch('payment/' + INITIALIZE_ABHI_PAY, {
    amount: amount.value,
    currency: 'PKR',
    booking_id: booking_id,
    paymentMethod: paymentMethod.value,
    callback_url: `${window.location.origin}/customer-payment-view?booking_id=${booking_id}&flight_mode=${route.query.flight_mode}&flight_id=${route.query.flight_id}&flight_provider=${route.query.flight_provider}&booking_source=${route.query.booking_source}&pnr=${route.query.pnr}&payment=true`,
  })
}

watch(abhiPayResponse, () => {
  if (paymentMethod?.value === "abhipay") {
    const url = abhiPayResponse.value?.response?.payload?.paymentUrl;
    if (url) {
      window.location.href = url;
    }
  } else if (paymentMethod?.value === "abhipay-bank") {
    billId.value = abhiPayResponse?.value?.response?.payload?.consumerNumber
  }
});

const copyBillId = () => {
  if (!billId.value) return;
  navigator.clipboard.writeText(billId.value);
};
// Wallet Confirmation
function handleConfirmDialogOpen() {


  if (agentLedger.value?.balance < amount?.value) {
    isLowBalanceDialogOpen.value = true;
  } else {
    isConfirmDialogOpen.value = true;
  }
}

watch(paymentStatus, () => {
  if (paymentStatus.value?.response === 'APPROVED' || paymentStatus.value?.response === 'PAID') {
    confirmBooking();
  }
});


function confirmBooking() {
    error.value = '';
    isLoading.value = true;
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: route.query.pnr,
        bookingId: bookingDetails.value[0].id,
        TUI: pnrData.value?.TUI ?? "null",
        TransactionID: pnrData.value?.TransactionID ?? "null",
        net_amount: pnrData.value?.NetAmount ?? "null",
        booking_status: "ticketed",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
    });

    // Close dialog after successful cancellation
    isConfirmDialogOpen.value = false;
    fetchBookingDetails();
}

// Alrajhi Payment
const payNow = async () => {
  loading.value = true;
  try {
    const { data } = await axios.post('/api/arb/initiate', {
      amount: amount.value || amount.value,
      flight_id: route.query.flight_id || flightData.value?.id || null,
      booking_id: bookingDetails?.value?.[0]?.id || booking_id,
      booking_uuid: pnrData.value?.data?.uuid ?? "null",
      pnr: bookingDetails?.value?.[0]?.itinerary_ref || pnr,
      flight_mode: "B2B",
      booking_source: route.query.booking_source,
      flight_provider: route.query.flight_provider || route.query.provider || null,
    });
    if (data.redirect_url) {
      window.location.href = data.redirect_url;
    } else {
      toast.error('Payment initialization failed.');
    }
  } catch (error) {
    console.error(error);
    toast.error('Error initiating Alrajhi payment');
  } finally {
    loading.value = false;
  }
};

watch(isConfirmed, () => {
  console.log("Booking confirmation status changed:", isConfirmed.value);
  if (isConfirmed.value == true) {
    router.push({
      name: "BookingsDetails",
      query: {
        flight_id: route.query.flight_id,
        booking_id: bookingDetails?.value?.[0].id,
        pnr: bookingDetails?.value?.[0].itinerary_ref,
        flight_mode: "B2C",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
      },
    });
  }
});
watch(bookingDetails, () => {
  billId.value = bookingDetails?.value?.[0]?.invoice_id;
  const booking = bookingDetails?.value?.[0] || null;
  if (booking) {
    passengerCount.value = parseInt(booking?.pessangers?.length || 1);
    agentAmount.value = parseFloat(booking?.agent_markup || 0);
    agentDiscount.value = parseFloat(booking?.agent_discount || 0);
    margin.value = parseFloat(booking?.agent_margin || 0);
    airportMargin.value = parseFloat(booking?.airport_margin_amount || 0);
  }
  if (bookingDetails?.value?.[0]?.flight_data) {
    flightData.value = parseFlightData(bookingDetails.value[0]?.flight_data);
    calculateGrandTotal();
  }
  if (payment.value == 'true') {
    checkPaymentStatus('abhipay');
  }
  // if (billId.value ) {
  //   checkPaymentStatus('abhipay-bank');
  // }
}, { immediate: true })

function checkPaymentStatus(type) {
  store?.dispatch('payment/' + CHECK_PAYMENT_STATUS, {
    paymentMethod: type,
    booking_id: bookingDetails?.value?.[0]?.id,
  })
}
// Stripe Payment
const openPaymentDialog = async () => {
  showPaymentDialog.value = true;
  await nextTick();
  await initializeStripe();

  const container = document.getElementById('card-element');
  if (container && !container.hasChildNodes()) {
    cardElement.value.mount('#card-element');
  }
};

const closePaymentDialog = () => {
  showPaymentDialog.value = false;
  paymentError.value = "";
  if (cardElement.value) cardElement.value.clear();
};

const initializeStripe = async () => {
  if (stripe.value) return;

  try {
    stripe.value = await loadStripe(publicKey.value);
    if (!stripe.value) throw new Error("Stripe failed to load");

    elements.value = stripe.value.elements();
    cardElement.value = elements.value.create('card', {
      style: {
        base: {
          fontSize: '16px',
          color: '#32325d',
          '::placeholder': { color: '#aab7c4' },
        },
      },
    });

    cardElement.value.on('change', (event) => {
      paymentError.value = event.error ? event.error.message : '';
    });
  } catch (err) {
    console.error(err);
    toast.error("Failed to load payment form.");
  }
};

const handlePayment = async () => {
  if (!stripe.value || !cardElement.value) {
    toast.error("Payment form not ready.");
    return;
  }

  processing.value = true;

  try {
    const response = await store.dispatch('flight/' + SEND_PAYMENT_REQUEST, {
      amount: Math.round(amount.value * 100),
      currency: 'sar',
      booking_id: booking_id,
    });

    clientSecret.value = response?.clientSecret;
    if (!clientSecret.value) throw new Error("No client secret");

    const result = await stripe.value.confirmCardPayment(clientSecret.value, {
      payment_method: { card: cardElement.value },
    });

    if (result.error) {
      paymentError.value = result.error.message;
      toast.error(result.error.message);
    } else {
      toast.success("Payment successful!");
      closePaymentDialog();
      confirmBooking();
    }
  } catch (err) {
    toast.error("Payment failed. Try again.");
  } finally {
    processing.value = false;
  }
};

// Void & Print
function voidRequest() {
  store.dispatch("flight/" + VOID_REQUEST, {
    pnr: pnr,
    booking_uuid: pnrData.value?.data?.uuid ?? "null",
    billable_price: pnrData.value?.data?.billable_price ?? "null",
    currency: pnrData.value?.data?.currency?.code ?? "null",
    bookingId: bookingDetails.value[0].id,
    booking_status: "voided",
    booking_source: route.query.booking_source,
  });
  isVoidDialogOpen.value = false;
  fetchBookingDetails();
}
function patchAncillaryCharges() {
  store.dispatch("flight/" + PATCH_ANCILLARIES, {
    flight_provider: flight_provider,
    pnr: pnrData.value,
    selectedAncillaries: selectedAncillariesList.map(item => ({
      segIdx: item.segIdx,
      segmentRPH: ancillarySegments.value[item.segIdx]?.FlightSegmentInfo['@attributes'].RPH,
      pIdx: item.pIdx,
      TravelerRefNumber: getPassengerTravelerRef(item.pIdx),
      groupCode: item.groupCode,
      itemCode: item.itemCode,
      amount: item.amount,
      currency: item.currency
    }))
  });
}

function calculateTaxes(fare) {
  return (
    parseFloat(fare?.taxes || 0) +
    parseFloat(fare?.surchage || 0) +
    parseFloat(fare?.fees || 0) +
    parseFloat(fare?.service_charges || 0) +
    parseFloat(fare?.ancillaries_charges || 0)
  );
}
const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
  // console.log("customer margin",{price,discountPercentage,marginPercentage})
  const total = parseFloat(price) || 0;
  const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
  const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

  // If discount is provided, return negative discount value, else return margin value
  if (discountPercentage && parseFloat(discountPercentage) > 0) {
    // console.log("Applying discount:", -discount);
    return -discount;
  }
  customerMarginAmt.value = margin;
  // console.log("Applying margin:", margin);
  return margin;
};
const calculateFareMargin = (basePrice, marginAmount, marginType, amountType) => {
  const price = parseFloat(basePrice) || 0;
  let margin = 0;

  if (marginType === "discount") {
    if (amountType === "percent") {
      margin = -((price * (parseFloat(marginAmount) || 0)) / 100);
    } else {
      margin = -(parseFloat(marginAmount) || 0);
    }
  } else if (marginType === "markup") {
    // Margin can be percent or amount
    if (amountType === "percent") {
      margin = (price * (parseFloat(marginAmount) || 0)) / 100;
    } else {
      margin = parseFloat(marginAmount) || 0;
    }
  }
  return margin;
};


const marginPerFlight = computed(() => {
  const flightCount = flightData?.value?.leg?.flights?.length || 0;
  if (!flightCount) return 0;
  return (savedMarginTotal.value / flightCount) || 0;
});
function calculateTotalFare(fare) {
  // 3️⃣ Airline margin (markup/discount according to API)
  const airlineMargin = parseFloat(
    calculateFareMargin(
      fare.base_price,
      fare.margin_amount,
      fare.margin_type,
      fare.amount_type
    )
  );
  // 4️⃣ Taxes + Fees + Service Charges + Ancillaries
  const taxes =
    parseFloat(fare.surchage || 0) +
    parseFloat(fare.taxes || 0) +
    parseFloat(fare.fees || 0) +
    parseFloat(fare.service_charges || 0) +
    parseFloat(fare.ancillaries_charges || 0);

  // 5️⃣ The main billable fare
  const billable =
    parseFloat(fare.base_price || 0) +
    taxes +
    airlineMargin * passengerCount.value;
  return billable;
}

function calculateGrandTotal() {
  let total = 0;

  flightData.value?.leg?.flights?.forEach((flightItem, flightIndex) => {
    flightItem?.fares?.forEach(fare => {
      if (selectedFares.value?.includes?.(fare.ref_id)) {
        total += calculateTotalFare(fare);
      }
    });
  });

  // Base amount is the real payable total (without payment-gateway adjustments).
  const computedBase =
    total +
    savedMarginTotal.value +
    parseFloat(bookingDetails?.value?.[0]?.add_ones_amount || 0) +
    extraServicesTotal.value +
    totalSeatsCost.value;

  baseAmount.value = computedBase;

  // AbhiPay card adds 3% on top of the base amount.
  amount.value = paymentMethod.value === "abhipay" ? computedBase * 1.03 : computedBase;
  return amount.value;
}

const getPassengerExtraBaggage = (pIdx, travelerType) => {
  return selectedAncillariesList.filter(x =>
    x.pIdx === pIdx &&
    x.groupCode.toLowerCase().includes("bag") &&   // XBAG matches
    x.type?.toUpperCase() === travelerType.toUpperCase()
  )
}

function fetchAncillaries() {
  store.dispatch("flight/" + FETCH_ANCILLARIES, {
    bookingId: bookingDetails?.value?.[0]?.id || booking_id,
    flight_provider: flight_provider,
  });
}

function parseData(data) {
  try {
    return JSON.parse(data);
  } catch (e) {
    console.error("Failed to parse data:", e);
    return [];
  }
}

const printBooking = () => window.print();
const downloadPDF = () => {
  const element = document.getElementById("print-section");
  html2pdf().from(element).set({
    margin: 5,
    filename: `booking_${booking_id}.pdf`,
    html2canvas: { scale: 2, useCORS: true },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }).save();
};
// Add this computed property
const allPassengersHaveSeats = computed(() => {
  if (!passengers.value?.length) return false;

  // Check each passenger has a seat selected for each segment
  for (let pIdx = 0; pIdx < passengers.value.length; pIdx++) {
    const travelerRef = getPassengerTravelerRef(pIdx);
    // Check if passenger has seat in every segment
    const hasSeatInAllSegments = processedSeatMapData.value.every(segment => {
      const segmentRPH = segment.FlightSegmentInfo['@attributes'].RPH;
      const key = `${segmentRPH}-${travelerRef}`;
      return selectedSeats.value[key] !== undefined;
    });

    if (!hasSeatInAllSegments) return false;
  }
  return true;
});


// Lifecycle
onMounted(async () => {
  if (!user.value) await authStore.fetchUser();
  payment.value = route.query.payment;
  bookingSource = route.query.booking_source;
  flight_provider = route.query.flight_provider || route.query.provider || null;
  booking_id = route.query.booking_id;
  pnr = route.query.pnr;
  await Promise.all([fetchAgent(), fetchAgentLedger(), fetchBookingDetails(), fetchCustomerMarginValues(),
  ]);
});
// Payment methods

// Flight data
const outboundFlight = ref({
  route: 'Dubai to Jeddah',
  date: 'Tue, 17 Mar, 2026',
  departure: '19:30',
  departureCode: 'DXB',
  arrival: '21:55',
  arrivalCode: 'JED',
  duration: '3h 25m',
  stops: 'Non Stop',
  flightCode: 'XY - 510',
})

const returnFlight = ref({
  route: 'Jeddah to Dubai',
  date: 'Tue, 31 Mar, 2026',
  departure: '14:55',
  departureCode: 'JED',
  arrival: '21:55',
  arrivalCode: 'DXB',
  duration: '6h 0m',
  stops: '1 Stop',
  flightCode: 'XY - 10 & XY - 209',
})



function proceedToPayment() {
  if (!selectedMethod.value) return
  showPaymentModal.value = true
}

function completePayment() {
  isProcessing.value = true
  // Simulate payment processing
  setTimeout(() => {
    isProcessing.value = false
    showPaymentModal.value = false
    alert(`Payment of ${currency.value} ${formatAmount(amount.value)} via ${selectedMethod.value.name} completed successfully!`)
  }, 2000)
}



// Initialize with first payment method selected
const paymentModalTitle = computed(() => {
  const titles = {
    paypal: 'Bank Transfer Payment',
    bank: 'Abhi Pay Transfer',
    hbl: 'HBL Card Payment',
    abhipay: 'Abhipay Card Payment',
    wallet: 'Wallet Payment',
  }
  return titles[selectedMethod.value?.id] || 'Payment Confirmation'
})

const paymentModalDescription = computed(() => {
  const descriptions = {
    paypal: 'Complete your bank transfer payment',
    bank: 'Proceed with Abhi Pay transfer',
    hbl: 'Enter your HBL card details',
    abhipay: 'Complete Abhipay payment',
    wallet: 'Confirm wallet payment',
  }
  return descriptions[selectedMethod.value?.id] || 'Confirm your payment details'
})
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:deep(*) {
  font-family: 'Inter', sans-serif;
}

/* Smooth transitions */
.transition-all {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Payment method hover effect */
.hover\:shadow-sm {
  transition: box-shadow 0.3s ease;
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
