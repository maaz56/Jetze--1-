<script setup>
import { ref } from 'vue';
import { MapPin, Phone, Mail, Send, MessageCircle } from 'lucide-vue-next';
import apiService from "@/services/store/apiService";

const form = ref({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: '',
  website: ''
});

const isSubmitting = ref(false);
const isSuccess = ref(false);
const errorMessage = ref('');
const formStartedAt = ref(Date.now());

const resetForm = () => {
  form.value = {
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    website: ''
  };
  errorMessage.value = '';
  isSuccess.value = false;
  formStartedAt.value = Date.now();
};

const handleSubmit = async () => {
  errorMessage.value = '';
  isSuccess.value = false;
  isSubmitting.value = true;

  try {
    await apiService.submitContactMessage({
      name: form.value.name,
      email: form.value.email,
      phone: form.value.phone,
      subject: form.value.subject,
      message: form.value.message,
      website: form.value.website,
      form_started_at: formStartedAt.value,
    });

    isSuccess.value = true;

    setTimeout(() => {
      resetForm();
    }, 2500);
  } catch (error) {
    errorMessage.value =
      error?.response?.data?.message ||
      error?.response?.data?.errors?.message?.[0] ||
      error?.response?.data?.errors?.email?.[0] ||
      "Unable to send your message right now. Please try again.";
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <!-- Hero Section - Clean & Professional -->
  <section class="relative h-64 bg-white">
    <div class="absolute inset-0 bg-">
      <div class="container mx-auto px-6 h-full flex items-center">
        <div>
          <h1 class="text-4xl md:text-5xl font-bold text-secondary">
            Contact <span class="text-primary">Jetze</span>
          </h1>
          <div class="h-1 w-24 bg-primary mt-4"></div>
          <p class="mt-6 text-lg  max-w-2xl text-gray-900">
            Professional travel solutions with 24/7 support. Reach out for bookings, inquiries, or assistance.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Content - Clean White Squared Layout -->
  <section class="py-16 bg-white">
    <div class="container mx-auto px-6">
      <!-- Two Column Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Contact Information Column -->
        <div class="lg:col-span-1">
          <!-- Quick Contact Cards -->
          <div class="mb-8">
            <div class="flex items-center mb-6">
              <div class="w-10 h-10 bg-primary text-white flex items-center justify-center mr-3">
                <Phone class="w-5 h-5" />
              </div>
              <h2 class="text-2xl font-bold text-gray-900">Quick Contact</h2>
            </div>
            
            <div class="space-y-4">
              <div class="p-5 bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                <div class="text-sm font-medium text-gray-500 mb-1">24/7 Support Line</div>
                <div class="text-lg font-bold text-gray-900">+92 311 1711123</div>
              </div>
              
              <div class="p-5 bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                <div class="text-sm font-medium text-gray-500 mb-1">Email Support</div>
                <div class="text-lg font-bold text-gray-900">support@Jetze.pk</div>
              </div>
              
              <!-- <div class="p-5 bg-gray-50 hover:bg-gray-100 transition-colors duration-200">
                <div class="text-sm font-medium text-gray-500 mb-1">Finance Department</div>
                <div class="text-lg font-bold text-gray-900"></div>
              </div> -->
            </div>
          </div>

          <!-- Office Hours -->
          <div class="mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Office Hours</h3>
            <div class="bg-gray-50 p-5">
              <div class="flex justify-between items-center py-3 border-b border-gray-200 last:border-b-0">
                <span class="text-gray-700">Monday - Friday</span>
                <span class="font-medium text-gray-900">24/7</span>
              </div>
              <div class="flex justify-between items-center py-3 border-b border-gray-200 last:border-b-0">
                <span class="text-gray-700">Saturday - Sunday</span>
                <span class="font-medium text-gray-900">24/7</span>
              </div>
              <div class="flex justify-between items-center py-3">
                <span class="text-gray-700">Emergency Support</span>
                <span class="font-medium text-green-600">Always Available</span>
              </div>
            </div>
          </div>

          <!-- Address -->
          <div>
            <h3 class="text-lg font-bold text-gray-900 mb-4">Head Office</h3>
            <div class="p-5 bg-gray-50">
              <div class="flex items-start">
                <div class="w-8 h-8 bg-primary text-white flex items-center justify-center mr-3 mt-1">
                  <MapPin class="w-4 h-4" />
                </div>
                <div>
                  <p class="text-gray-700">
                    F-16 AliZai Tower<br>
                    Mardan Road, Charsadda<br>
                    Khyber Pakhtunkhwa, Pakistan
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Main Contact Form Column -->
        <div class="lg:col-span-2">
          <div class="mb-10">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Send Us a Message</h2>
            <p class="text-gray-600 mb-8">
              Fill out the form below and our team will get back to you within 1 hour. For urgent matters, please call our 24/7 support line.
            </p>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-6">
            <input
              v-model="form.website"
              type="text"
              autocomplete="off"
              tabindex="-1"
              class="hidden"
              aria-hidden="true"
            />

            <div v-if="isSuccess" class="p-4 bg-green-50 border border-green-200 text-green-700">
              Your message has been sent successfully. Our team will contact you shortly.
            </div>
            <div v-if="errorMessage" class="p-4 bg-red-50 border border-red-200 text-red-700">
              {{ errorMessage }}
            </div>

            <!-- Name & Email Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                <input v-model="form.name" type="text" required
                  class="w-full px-4 py-3 border border-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="Enter your full name" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                <input v-model="form.email" type="email" required
                  class="w-full px-4 py-3 border border-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="you@example.com" />
              </div>
            </div>

            <!-- Phone & Subject Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input v-model="form.phone" type="tel" required
                  class="w-full px-4 py-3 border border-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="+92 300 1234567" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                <select v-model="form.subject" required
                  class="w-full px-4 py-3 border border-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                  <option value="">Select a topic</option>
                  <option value="Flight Booking">Flight Booking</option>
                  <option value="Visa Services">Visa Services</option>
                  <option value="Hotel Booking">Hotel Booking</option>
                  <option value="Group Travel">Group Travel</option>
                  <option value="Customer Support">Customer Support</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <!-- Message -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
              <textarea v-model="form.message" rows="6" required
                class="w-full px-4 py-3 border border-gray-300 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary resize-none"
                placeholder="Please describe your inquiry in detail..."></textarea>
            </div>

            <!-- Submit Button -->
            <div class="flex flex-col sm:flex-row gap-4 pt-2">
              <button type="submit" :disabled="isSubmitting"
                class="px-8 py-4 bg-primary hover:bg-primary/90 text-white font-medium flex items-center justify-center gap-3 transition-colors duration-200">
                <Send v-if="!isSubmitting" class="w-5 h-5" />
                <span v-if="isSubmitting" class="w-5 h-5 border-2 border-white border-t-transparent animate-spin"></span>
                {{ isSubmitting ? 'Sending Message...' : 'Send Message' }}
              </button>
              
              <button type="button" @click="resetForm"
                class="px-8 py-4 border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition-colors duration-200">
                Clear Form
              </button>
            </div>
          </form>

          <!-- Contact Methods Grid -->
          <div class="mt-16">
            <h3 class="text-xl font-bold text-gray-900 mb-6">Other Ways to Contact Us</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <a href="https://wa.me/923111711123" target="_blank"
                class="p-5 bg-green-50 hover:bg-green-100 border border-green-100 flex items-center gap-3 transition-colors duration-200">
                <div class="w-10 h-10 bg-green-600 text-white flex items-center justify-center">
                  <MessageCircle class="w-5 h-5" />
                </div>
                <div>
                  <div class="font-medium text-gray-900">WhatsApp</div>
                  <div class="text-sm text-gray-600">Instant chat support</div>
                </div>
              </a>
              
              <a href="mailto:support@Jetze.pk"
                class="p-5 bg-blue-50 hover:bg-blue-100 border border-blue-100 flex items-center gap-3 transition-colors duration-200">
                <div class="w-10 h-10 bg-blue-600 text-white flex items-center justify-center">
                  <Mail class="w-5 h-5" />
                </div>
                <div>
                  <div class="font-medium text-gray-900">Email</div>
                  <div class="text-sm text-gray-600">support@Jetze.pk</div>
                </div>
              </a>
              
              <a href="tel:+923111711123"
                class="p-5 bg-purple-50 hover:bg-purple-100 border border-purple-100 flex items-center gap-3 transition-colors duration-200">
                <div class="w-10 h-10 bg-purple-600 text-white flex items-center justify-center">
                  <Phone class="w-5 h-5" />
                </div>
                <div>
                  <div class="font-medium text-gray-900">Phone Call</div>
                  <div class="text-sm text-gray-600">+92 311 1711123</div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Map Section -->
      <div class="mt-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Find Our Office</h2>
        <div class="h-96">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d105846.83214733186!2d71.39277959726564!3d33.9998919!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38d917a16b712b9b%3A0x300a41edde3f384e!2sEtimad%20Travels%20Peshawar!5e0!3m2!1sen!2s!4v1772100822225!5m2!1sen!2s"
            width="100%" 
            height="100%" 
            style="border:0;"
            allowfullscreen="" 
            loading="lazy" 
            referrerpolicy="no-referrer-when-downgrade"
            class="w-full h-full"
            title="Jetze Office Location">
          </iframe>
        </div>
      </div>
    </div>
  </section>

 
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
