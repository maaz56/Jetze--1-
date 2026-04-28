<script setup>
import { ref } from 'vue';
import Nav from "@/components/shared/Nav.vue";
import { MapPin, Phone, Mail, Send, Building } from 'lucide-vue-next';
import SocialLinks from '@/components/common/SocialLinks.vue';

const form = ref({
  email: '',
  subject: '',
  message: ''
});

const isSubmitting = ref(false);
const isSuccess = ref(false);

const handleSubmit = async () => {
  isSubmitting.value = true;
  // Simulate form submission
  await new Promise(resolve => setTimeout(resolve, 1000));
  isSubmitting.value = false;
  isSuccess.value = true;

  // Reset form after showing success message
  setTimeout(() => {
    form.value = { email: '', subject: '', message: '' };
    isSuccess.value = false;
  }, 3000);
};
</script>

<template>
  <!-- <Nav isNavTransparent /> -->

  <!-- Hero Section with Background Image -->
  <div class="relative h-64 md:h-80 lg:h-96 overflow-hidden">
    <img src="https://cdn.pixabay.com/photo/2018/03/29/06/51/illuminated-3271512_1280.jpg" alt="Travel Destination"
      class="w-full h-full object-cover" />
    <div class="absolute inset-0 bg-gradient-to-b from-transparent to-black/70 flex items-end">
      <div class="container mx-auto px-6 pb-12">
        <h1 class="text-4xl md:text-5xl font-bold text-white">Contact Us</h1>
        <p class="text-white/80 mt-2 max-w-2xl">We're here to help plan your perfect journey. Reach out to our team for
          personalized travel assistance.</p>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <section class="bg-white py-12 md:py-20">
    <div class="container mx-auto px-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Contact Form -->
        <div class="bg-white rounded-xl shadow-lg p-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Get in Touch</h2>
          <p class="text-gray-600 mb-8">
            Have questions about our travel packages? Want to customize your trip?
            Our team is ready to assist you with all your travel needs.
          </p>

          <form @submit.prevent="handleSubmit" class="space-y-6">
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Your Email</label>
              <input v-model="form.email" type="email" id="email"
                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"
                placeholder="you@example.com" required />
            </div>

            <div>
              <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
              <input v-model="form.subject" type="text" id="subject"
                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"
                placeholder="How can we help you?" required />
            </div>

            <div>
              <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Your Message</label>
              <textarea v-model="form.message" id="message" rows="5"
                class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-teal-500 focus:border-teal-500 transition"
                placeholder="Tell us about your travel plans..." required></textarea>
            </div>

            <div>
              <button type="submit" :disabled="isSubmitting"
                class="w-full md:w-auto px-6 py-3 bg-primary hover:bg-\[#dbcaa4]  text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                <Send v-if="!isSubmitting" class="h-4 w-4" />
                <span v-if="isSubmitting"
                  class="inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                {{ isSubmitting ? 'Sending...' : 'Send Message' }}
              </button>
            </div>

            <div v-if="isSuccess" class="p-4 bg-green-50 text-green-700 rounded-lg">
              Thank you for your message! We'll get back to you soon.
            </div>
          </form>
        </div>

        <!-- Contact Information -->
        <div class="flex flex-col">
          <div class="bg-primary rounded-xl shadow-lg p-8 text-white mb-8">
            <h2 class="text-2xl font-bold mb-6">Contact Information</h2>

            <div class="space-y-6">
              <div class="flex items-start gap-4">
                <Phone class="h-6 w-6 mt-1 flex-shrink-0" />
                <div>
                  <h3 class="font-medium">Phone</h3>
                  <p class="mt-1">(+92) 3111711123</p>
                  <p class="mt-1">(+92) 3111711123</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <Mail class="h-6 w-6 mt-1 flex-shrink-0" />
                <div>
                  <h3 class="font-medium">Email</h3>
                  <p class="mt-1">support@Jetze.pk</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <MapPin class="h-6 w-6 mt-1 flex-shrink-0" />
                <div>
                  <h3 class="font-medium">Address</h3>
                  <p class="mt-1"> F-16 AliZai Tower,<br>
                    Mardan Road Charsadda.</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <Building class="h-6 w-6 mt-1 flex-shrink-0" />
                <div>
                  <h3 class="font-medium">Company Info</h3>
                  <p class="mt-1">Jetze TRAVEL AGENCY</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Map Embed -->
          <div class="bg-white rounded-xl shadow-lg overflow-hidden h-64 md:h-80">
            <iframe
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3612.1722383157424!2d55.37894!3d25.122676!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3e5f6418d3695c2b%3A0x87f5a5d97e196dd1!2sDubai%20Digital%20Park%2C%20Dubai%20Silicon%20Oasis!5e0!3m2!1sen!2sae!4v1725705000000!5m2!1sen!2sae"
              width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
              referrerpolicy="no-referrer-when-downgrade">
            </iframe>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action -->
  <section class="bg-gray-50 py-12">
    <div class="container mx-auto px-6 text-center">
      <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-4">Ready to Start Your Journey?</h2>
      <p class="text-gray-600 max-w-2xl mx-auto mb-8">
        Discover amazing destinations and create unforgettable memories with Jetze.
      </p>
      <button class="px-6 py-3 bg-primary hover:bg-[#dbcaa4]  text-white font-medium rounded-lg transition-colors">
        Explore Our Packages
      </button>
    </div>
    <SocialLinks class="mt-4  relative top-" />

  </section>
</template>

<style scoped>
/* Any additional custom styles can go here */
</style>