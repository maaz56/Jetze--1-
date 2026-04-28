// src/composables/useCurrency.js
import { ref, onMounted } from 'vue'

const exchangeRate = ref(null)
const isLoading = ref(false)
const error = ref(null)

const fetchRate = async () => {
  isLoading.value = true
  error.value = null
  try {
    const res = await fetch(`https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/pkr.json`)
    const data = await res.json()
    exchangeRate.value = data.pkr.aed
    // //console.log('Exchange Rate PKR to AED:', exchangeRate.value)

  } catch (err) {
    console.error('Currency API error:', err)
    error.value = err.message
    exchangeRate.value = null
  } finally {
    isLoading.value = false
  }
}

onMounted(() => fetchRate())

export function useCurrency() {
  const convertPKRtoAED = (amountPKR) => {
    if (typeof amountPKR !== 'number' || isNaN(amountPKR)) {
      return null
    }
    if (!exchangeRate.value) {
      return null
    }
    return (amountPKR * exchangeRate.value).toFixed(2)
  }

  return {
    exchangeRate,
    isLoading,
    error,
    convertPKRtoAED,
    fetchRate,
  }
}