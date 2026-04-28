<script setup>
import { ref } from 'vue';
import { MessageCircle, MapPin } from 'lucide-vue-next';

const searchQuery = ref('');
const selectedHotel = ref(null);
const showDialog = ref(false);

const hotels = [
  { id: 1, name: "Luxury Beach Resort", image: "https://images.unsplash.com/photo-1582719508461-905c673771fd?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 299, discount: "20% OFF", location: "Palm Jumeirah", description: "Enjoy a luxurious beachfront resort with private beach access, spa services, and gourmet dining." },
  { id: 2, name: "Downtown Luxury Hotel", image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 199, discount: "15% OFF", location: "Downtown Dubai", description: "Escape to a peaceful retreat with stunning views of Burj Khalifa and Dubai Fountain." },
  { id: 3, name: "Urban Boutique Hotel", image: "https://images.unsplash.com/photo-1578683010236-d716f9a3f461?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 179, discount: "10% OFF", location: "Dubai Marina", description: "Stay in a stylish boutique hotel in the heart of the city, close to shopping, dining, and attractions." },
  { id: 4, name: "Seaside Villa", image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 349, discount: "25% OFF", location: "Jumeirah Beach", description: "Experience luxury in a private villa with panoramic ocean views and direct beach access." },
  { id: 5, name: "Historic Inn", image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 159, discount: "12% OFF", location: "Al Fahidi", description: "Stay in a beautifully restored historic building with traditional architecture and modern amenities." },
  { id: 6, name: "Waterfront Resort", image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 229, discount: "18% OFF", location: "Dubai Creek", description: "Relax at a tranquil waterfront resort with stunning city views and outdoor activities." },
  { id: 7, name: "Desert Oasis", image: "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 189, discount: "15% OFF", location: "Dubai Desert Conservation Reserve", description: "Experience the magic of the desert at this luxurious oasis with traditional decor and modern comforts." },
  { id: 8, name: "Business Hotel", image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 169, discount: "10% OFF", location: "Business Bay", description: "Perfect for business travelers with conference facilities, high-speed internet, and central location." },
  { id: 9, name: "Family Resort", image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 259, discount: "20% OFF", location: "Jumeirah Beach Residence", description: "Ideal for families with kids' activities, swimming pools, and spacious accommodations." },
  { id: 10, name: "Luxury Hotel", image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 149, discount: "15% OFF", location: "Sheikh Zayed Road", description: "Sustainable accommodation in the heart of Dubai with organic food and eco-friendly practices." },
  { id: 11, name: "Luxury Glamping", image: "https://images.unsplash.com/photo-1521783988139-89397d761dce?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 219, discount: "18% OFF", location: "Al Qudra Desert", description: "Experience luxury camping with all the comforts of a hotel in a stunning natural setting." },
  { id: 12, name: "Waterfront Cabins", image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 179, discount: "12% OFF", location: "Deira", description: "Cozy cabins along the waterfront with private terraces and beautiful views." },
  { id: 13, name: "Luxury Beach Resort", image: "https://images.unsplash.com/photo-1582719508461-905c673771fd?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 299, discount: "20% OFF", location: "Palm Jumeirah", description: "Enjoy a luxurious beachfront resort with private beach access, spa services, and gourmet dining." },
  { id: 14, name: "Downtown Luxury Hotel", image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 199, discount: "15% OFF", location: "Downtown Dubai", description: "Escape to a peaceful retreat with stunning views of Burj Khalifa and Dubai Fountain." },
  { id: 15, name: "Urban Boutique Hotel", image: "https://images.unsplash.com/photo-1578683010236-d716f9a3f461?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 179, discount: "10% OFF", location: "Dubai Marina", description: "Stay in a stylish boutique hotel in the heart of the city, close to shopping, dining, and attractions." },
  { id: 16, name: "Seaside Villa", image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 349, discount: "25% OFF", location: "Jumeirah Beach", description: "Experience luxury in a private villa with panoramic ocean views and direct beach access." },
  { id: 17, name: "Historic Inn", image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 159, discount: "12% OFF", location: "Al Fahidi", description: "Stay in a beautifully restored historic building with traditional architecture and modern amenities." },
  { id: 18, name: "Waterfront Resort", image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 229, discount: "18% OFF", location: "Dubai Creek", description: "Relax at a tranquil waterfront resort with stunning city views and outdoor activities." },
  { id: 19, name: "Desert Oasis", image: "https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 189, discount: "15% OFF", location: "Dubai Desert Conservation Reserve", description: "Experience the magic of the desert at this luxurious oasis with traditional decor and modern comforts." },
  { id: 20, name: "Business Hotel", image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 169, discount: "10% OFF", location: "Business Bay", description: "Perfect for business travelers with conference facilities, high-speed internet, and central location." },
  { id: 21, name: "Family Resort", image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 259, discount: "20% OFF", location: "Jumeirah Beach Residence", description: "Ideal for families with kids' activities, swimming pools, and spacious accommodations." },
  { id: 22, name: "Luxury Hotel", image: "https://images.unsplash.com/photo-1564501049412-61c2a3083791?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 149, discount: "15% OFF", location: "Sheikh Zayed Road", description: "Sustainable accommodation in the heart of Dubai with organic food and eco-friendly practices." },
  { id: 23, name: "Luxury Glamping", image: "https://images.unsplash.com/photo-1521783988139-89397d761dce?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 219, discount: "18% OFF", location: "Al Qudra Desert", description: "Experience luxury camping with all the comforts of a hotel in a stunning natural setting." },
  { id: 24, name: "Waterfront Cabins", image: "https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80", price: 179, discount: "12% OFF", location: "Deira", description: "Cozy cabins along the waterfront with private terraces and beautiful views." },
];

const openHotelDetails = (hotel) => {
  selectedHotel.value = hotel;
  showDialog.value = true;
};

const closeDialog = () => {
  showDialog.value = false;
};
</script>

<template>
  <div class="min-h-screen bg-gray-10">
    <div class="relative h-[400px]">
      <img src="https://cdn.pixabay.com/photo/2015/12/28/10/19/hotel-1111199_960_720.jpg" alt="Dubai Luxury Hotel" class="w-full h-full object-cover" />
    </div>

    <div class="container mx-auto px-4 py-12">
      <h2 class="text-2xl font-semibold text-center mb-8">Find Your Perfect Stay in Dubai</h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <div
          v-for="hotel in hotels"
          :key="hotel.id"
          @click="openHotelDetails(hotel)"
          class="bg-white rounded-lg overflow-hidden shadow hover:shadow-lg transition-shadow duration-300 cursor-pointer"
        >
          <div class="relative">
            <img :src="hotel.image" :alt="hotel.name" class="w-full h-48 object-cover" />
            <div v-if="hotel.discount" class="absolute top-3 right-3 bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded">
              {{ hotel.discount }}
            </div>
          </div>
          <div class="p-4">
            <h3 class="font-semibold text-lg mb-1 truncate">{{ hotel.name }}</h3>
            <div class="flex items-center mb-2">
              <MapPin class="h-3 w-3 text-gray-500 mr-1" />
              <p class="text-xs text-gray-500 truncate">{{ hotel.location }}</p>
            </div>
            <!-- <div class="text-[#a89666] font-bold">
              AED {{ hotel.price }} <span class="text-xs font-normal text-gray-500">/night</span>
            </div> -->
          </div>
        </div>
      </div>
    </div>

    <div v-if="showDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-lg w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6" v-if="selectedHotel">
          <div class="flex justify-between items-start mb-4">
            <div>
              <h2 class="text-xl font-semibold">{{ selectedHotel.name }}</h2>
              <div class="flex items-center text-sm text-gray-500">
                <MapPin class="h-3 w-3 mr-1" />
                {{ selectedHotel.location }}
              </div>
            </div>
            <button @click="closeDialog" class="text-gray-500 hover:text-gray-700">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <img :src="selectedHotel.image" :alt="selectedHotel.name" class="w-full h-64 object-cover rounded-md mb-4" />
          <p class="text-sm text-gray-600 mb-4">{{ selectedHotel.description }}</p>

          <!-- <div class="border-t border-gray-200 pt-4 mt-4">
            <div class="flex justify-between mb-2">
              <span class="text-gray-600">Price per night</span>
              <span class="font-medium">AED {{ selectedHotel.price }}</span>
            </div> -->
            <!-- <div class="flex justify-between mb-2">
              <span class="text-gray-600">Taxes & fees</span>
              <span class="font-medium">AED {{ Math.round(selectedHotel.price * 0.15) }}</span>
            </div>
            <div class="flex justify-between font-bold pt-2 border-t border-gray-200 mt-2">
              <span>Total</span>
              <span>AED {{ selectedHotel.price + Math.round(selectedHotel.price * 0.15) }}</span>
            </div> -->
          

          <div class="flex gap-4 mt-6">
            <button @click="closeDialog" class="flex-1 bg-[#a89666] hover:bg-[#8e7d55] text-white font-medium py-2 px-4 rounded">
              Book Now
            </button>
            <a href="https://wa.me/+923334419634" target="_blank" rel="noopener noreferrer"
              class="flex-1 flex items-center justify-center bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded">
              <MessageCircle class="h-4 w-4 mr-2" />
              WhatsApp
            </a>
          </div>
        </div>
      </div>
    </div>

    <a href="https://wa.me/+923334419634" target="_blank" rel="noopener noreferrer"
      class="fixed bottom-6 right-6 bg-green-500 hover:bg-green-600 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all z-40 animate-bounce-slow">
      <MessageCircle class="h-6 w-6" />
    </a>
  </div>
</template>

<style scoped>
@keyframes bounce-slow {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}
.animate-bounce-slow {
  animation: bounce-slow 2s infinite;
}
</style>
