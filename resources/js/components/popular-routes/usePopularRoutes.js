import { ref, computed, onMounted, onUnmounted } from 'vue';
import apiService from '@/services/store/apiService';
import { getSelectedCurrencyCode } from '@/lib/utils';

const cache = new Map();

export function usePopularRoutes() {
  const routes = ref([]);
  const isLoading = ref(true);
  const error = ref(null);
  const activeTab = ref('all'); // 'all', 'international', 'domestic'

  const currentCurrency = ref(getSelectedCurrencyCode() || 'AED');

  const fetchRoutes = async (force = false) => {
    const currency = getSelectedCurrencyCode() || 'AED';
    currentCurrency.value = currency;
    const cacheKey = `${currency}:limit12`;

    if (!force && cache.has(cacheKey) && (cache.get(cacheKey)?.length > 0)) {
      routes.value = cache.get(cacheKey);
      isLoading.value = false;
      return;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const response = await apiService.getPublicPopularRoutes({
        limit: 12,
        currency,
      });

      const list = response?.data?.data || [];
      routes.value = list;
      if (list.length > 0) {
        cache.set(cacheKey, list);
      }
    } catch (err) {
      console.error('Failed to fetch popular routes:', err);
      error.value = err;
      if (!routes.value.length) {
        routes.value = [];
      }
    } finally {
      isLoading.value = false;
    }
  };

  const handleCurrencyChange = (e) => {
    const newCode = e?.detail?.code || getSelectedCurrencyCode() || 'AED';
    if (newCode !== currentCurrency.value) {
      currentCurrency.value = newCode;
      fetchRoutes(true);
    }
  };

  const filteredRoutes = computed(() => {
    if (activeTab.value === 'all') return routes.value;
    return routes.value.filter(
      (r) => (r.type || '').toLowerCase() === activeTab.value.toLowerCase()
    );
  });

  const counts = computed(() => {
    const total = routes.value.length;
    const intl = routes.value.filter(
      (r) => (r.type || '').toLowerCase() === 'international'
    ).length;
    const dom = routes.value.filter(
      (r) => (r.type || '').toLowerCase() === 'domestic'
    ).length;
    return { all: total, international: intl, domestic: dom };
  });

  // Find lowest price route to tag with 'Best deal'
  const lowestPriceId = computed(() => {
    let minAmount = Infinity;
    let minId = null;
    for (const r of routes.value) {
      const amt = Number(r?.price?.amount);
      if (amt && amt > 0 && amt < minAmount) {
        minAmount = amt;
        minId = r.id;
      }
    }
    return minId;
  });

  let removeCurrencyListener = null;

  onMounted(() => {
    fetchRoutes();
    if (typeof window !== 'undefined') {
      window.addEventListener('currency-changed', handleCurrencyChange);
      removeCurrencyListener = () => {
        window.removeEventListener('currency-changed', handleCurrencyChange);
      };
    }
  });

  onUnmounted(() => {
    if (removeCurrencyListener) {
      removeCurrencyListener();
    }
  });

  return {
    routes,
    filteredRoutes,
    isLoading,
    error,
    activeTab,
    counts,
    lowestPriceId,
    currentCurrency,
    refetch: () => fetchRoutes(true),
  };
}
