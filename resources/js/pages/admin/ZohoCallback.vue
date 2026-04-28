<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useStore } from 'vuex'
import { GET_ZOHO_TOKEN } from '@/services/store/actions.type'

const route = useRoute()
const router = useRouter()
const store = useStore();

onMounted(async () => {
  const code = route.query.code
  if (code) {
    try {
      // Send code to your Laravel backend
      await store.dispatch('zoho/' + GET_ZOHO_TOKEN, code)
      router.push('/admin/setting') // Redirect after success
    } catch (error) {
      console.error(error)
    }
  } else {
    alert('No auth code found in URL')
  }
})
</script>

<template>
  <div class="p-4">Authorizing with Zoho...</div>
</template>
