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

const store = useAuthStore();
const isDialogOpen = computed(() => store.isDialogOpen);
async function fetchCountryCode() {
  try {
    const res = await fetch(import.meta.env.VITE_IPAPI_URL);
    const data = await res.json();
    localStorage.setItem("country", data?.country);
  } catch (error) {
    console.error("Error fetching data:", error);
  }
}


onMounted(() => {
  fetchCountryCode();
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
    <Footer />
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
