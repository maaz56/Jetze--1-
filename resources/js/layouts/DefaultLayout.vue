<script setup>
import Footer from "../components/common/Footer.vue";
import Nav from "../components/shared/Nav.vue";
import Header from "../components/shared/Header.vue";
import WhatsappButton from "@/components/common/WhatsappButton.vue";
import { onMounted } from "vue";
import LoginMini from "@/pages/LoginMini.vue";
import { useStore } from "vuex";
import { computed } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import { useRoute } from "vue-router";

const store = useAuthStore();
const route = useRoute();
const isDialogOpen = computed(() => store.isDialogOpen);
// Only pages represented in the shared public navigation may render the
// application footer. About, Contact, and Blog currently use Blade templates
// and render their marketing footer there; their names are retained here for
// when those pages are served by this layout in the future.
const sharedNavFooterRoutes = new Set([
  "Home",
  "FlightSearch",
  "AboutUs",
  "ClientContactUs",
  "ContactUs",
  "Blogs",
  "Blog",
  "BlogBySlug",
]);
const showFooter = computed(() => sharedNavFooterRoutes.has(String(route.name)));
async function fetchCountryCode() {
  try {
    const res = await fetch(import.meta.env.VITE_IPAPI_URL);
    const data = await res.json();
    localStorage.setItem("country", data?.country);
  } catch (error) {
    console.error("Error fetching data:", error);
  }
}

function testGeolocation() {
  if (!navigator.geolocation) {
    console.warn("Geolocation is not supported by this browser.");
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const { latitude, longitude, accuracy } = position.coords;
      localStorage.setItem("latitude", String(latitude));
      localStorage.setItem("longitude", String(longitude));

      window.dispatchEvent(
        new CustomEvent("user-geolocation-updated", {
          detail: { latitude, longitude },
        })
      );

     
    },                  
    (error) => {
      console.error("Geolocation error:", error.message, error);
    },
    {
       enableHighAccuracy: false, // ✅ faster
      timeout: 20000,                                                                                                                       // ✅ increase time
      maximumAge: 60000, 
    }
  );
}

onMounted(() => {
  fetchCountryCode();
  testGeolocation();
});
</script>



<template>
  <section>
    <Nav />
    <main>
      <LoginMini v-if="isDialogOpen" class="login-dialog" />
      <!-- extending pages here -->
      <router-view></router-view>
    </main>
    <!-- <Footer v-if="showFooter" /> -->
  </section>

  <WhatsappButton />
</template>
<style scoped>
.login-dialog {
  position: fixed;
  top: 55%;
  left: 50%;
  z-index: 200;
  transform: translate(-50%, -50%);
  background: white;
  border-radius: 15px;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  animation: popupIn 0.3s ease;
}


@keyframes popupIn {
  0% {
    transform: translate(-50%, -40%);
    opacity: 0;
  }

  100% {
    transform: translate(-50%, -50%);
    opacity: 1;
  }
}

@keyframes popupPulse {
  0% {
    transform: scale(1);
    opacity: 0.2;
  }

  70% {
    transform: scale(1.1);
    opacity: 0;
  }

  100% {
    transform: scale(1.1);
    opacity: 0;
  }
}
</style>
