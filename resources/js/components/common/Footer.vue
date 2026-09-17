<script setup>
import { Mail, Phone, MapPin, Globe } from "lucide-vue-next";

const offices = [
  {
    code: 'LHE',
    tag: 'Head Office',
    city: 'Lahore, Pakistan',
    address: 'Office 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Gulberg III, Lahore.',
    phone: '+92 300 7690691',
    tel: '+923007690691',
  },
  {
    code: 'DXB',
    tag: 'Regional Office',
    city: 'Dubai, UAE',
    address: 'Office 14, 1st Floor, DNI Bldg, Opp Deira City Centre, Port Saeed, Dubai.',
    phone: '+971 54 5299909',
    tel: '+971545299909',
  },
  {
    code: 'MNL',
    tag: 'Regional Office',
    city: 'Manila, Philippines',
    address: 'Corporate Plaza, High Street South, Makati City, Metro Manila.',
    phone: '+63 908 3986939',
    tel: '+639083986939',
  },
];

const socials = [
  { name: 'TikTok', href: 'https://www.tiktok.com/@user8107574618184', icon: '/assets/tiktok.png' },
  { name: 'Facebook', href: 'https://www.facebook.com/share/1CDZXjBz3Y/', icon: '/assets/fb.png' },
  { name: 'Instagram', href: 'https://www.instagram.com/jetze.pk', icon: '/assets/instagram.png' },
  { name: 'YouTube', href: '#', icon: '/assets/yt.png' },
];
</script>

<template>
  <footer class="bg-white text-gray-700 font-roboto border-t border-gray-200/90 antialiased w-full">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-7 sm:py-8">

      <!-- Top row: brand + contact + socials -->
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5 pb-6 border-b border-gray-200/80">
        
        <!-- Brand & Tagline -->
        <div class="flex items-center gap-3.5">
          <img src="/public/assets/logo.png" class="h-9 w-auto object-contain shrink-0" alt="Jetze Logo" />
          <div class="h-7 w-px bg-gray-200 hidden sm:block"></div>
          <p class="hidden sm:block text-xs text-gray-500 leading-snug max-w-xs font-medium">
            Your Journey, Simplified. Flights, stays, Umrah packages & fast-track visas.
          </p>
        </div>

        <!-- Contact Links & Social Media Badges -->
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-xs font-medium">
          <a href="mailto:support@jetze.pk" class="flex items-center gap-2 text-gray-600 hover:text-primary transition-colors">
            <div class="w-6 h-6 rounded-md bg-orange-50 border border-orange-200/60 flex items-center justify-center shrink-0">
              <Mail class="w-3.5 h-3.5 text-orange-500" />
            </div>
            <span>support@jetze.pk</span>
          </a>
          
          <a href="tel:+923007690691" class="flex items-center gap-2 text-gray-600 hover:text-primary transition-colors">
            <div class="w-6 h-6 rounded-md bg-primary/10 border border-primary/20 flex items-center justify-center shrink-0">
              <Phone class="w-3.5 h-3.5 text-primary" />
            </div>
            <span>UAN: (+92) 300 7690691</span>
          </a>

          <!-- Social Buttons -->
          <div class="flex items-center gap-2 pl-1 border-l border-gray-200/80">
            <a
              v-for="s in socials"
              :key="s.name"
              :href="s.href"
              target="_blank"
              rel="noopener noreferrer"
              :aria-label="s.name"
              class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-primary/10 border border-gray-200/80 flex items-center justify-center transition-all hover:scale-105 shadow-2xs group"
            >
              <img :src="s.icon" :alt="s.name" class="w-4 h-4 object-contain" />
            </a>
          </div>
        </div>
      </div>

      <!-- Offices: compact multi-column cards with icons -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5 py-6">
        <div
          v-for="office in offices"
          :key="office.code"
          class="flex items-start gap-3 bg-slate-50/70 border border-gray-200/80 rounded-xl px-4 py-3.5 text-xs hover:border-primary/40 hover:bg-white transition-all shadow-2xs group"
        >
          <span class="shrink-0 text-[10px] font-mono font-extrabold bg-primary/10 text-primary border border-primary/20 px-2 py-0.5 rounded-md mt-0.5">
            {{ office.code }}
          </span>
          <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center justify-between gap-1.5">
              <span class="font-bold text-gray-900 text-xs sm:text-sm group-hover:text-primary transition-colors">
                {{ office.city.split(',')[0] }}
              </span>
              <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">
                {{ office.tag }}
              </span>
            </div>
            <p class="text-[11px] text-gray-500 leading-snug line-clamp-2" :title="office.address">
              {{ office.address }}
            </p>
            <div class="pt-0.5">
              <a :href="`tel:${office.tel}`" class="text-[11px] font-extrabold text-primary hover:text-primary-dark inline-flex items-center gap-1.5">
                <Phone class="w-3 h-3 text-primary shrink-0" />
                <span>{{ office.phone }}</span>
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom bar: Copyright & Accreditation -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-5 border-t border-gray-200/80 text-[11px] sm:text-xs text-gray-500 font-medium">
        <div>
          © {{ new Date().getFullYear() }} <strong class="text-gray-900 font-bold">Jetze</strong>. All Rights Reserved.
        </div>
        <div class="flex items-center gap-2 bg-slate-50 border border-gray-200/70 px-3 py-1 rounded-full">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-gray-600 font-semibold">IATA Accredited • DTS Licensed</span>
        </div>
      </div>

    </div>
  </footer>
</template>