<script setup>
import Button from "../components/common/Button.vue";
import ButtonArrBackward from "../components/common/ButtonArrBackward.vue";
import ButtonArrForward from "../components/common/ButtonArrForward.vue";
import Nav from "../components/shared/Nav.vue";
</script>

<template>
    <Nav />
    <div class="container-default pt-6">
        <div class="mb-6">
            <Button title="Back"><ButtonArrBackward /></Button>
            <!-- <Button title="Back" backButton bordered /> -->
        </div>
        <div class="grid md:grid-cols-2 gap-4 mb-4">
            <div
                class="border rounded-2xl overflow-hidden h-[200px] md:h-[400px]"
            >
                <img
                    src="https://img.freepik.com/premium-photo/high-end-clean-atmospheric-hotel-rooms_149197-85.jpg?w=1060"
                    alt=""
                />
            </div>
            <div class="hidden md:grid grid-cols-2 gap-4 h-[400px]">
                <div class="border rounded-2xl overflow-hidden">
                    <img
                        class="h-full w-full object-cover"
                        src="https://img.freepik.com/premium-photo/luxury-room-with-bed-warm-light_24637-603.jpg?w=740"
                        alt=""
                    />
                </div>
                <div class="border rounded-2xl overflow-hidden">
                    <img
                        class="h-full w-full object-cover"
                        src="https://img.freepik.com/free-photo/pillow-bed_1203-2819.jpg?t=st=1714557491~exp=1714561091~hmac=d7c64561e0f34f7d0c1741e92428b5ed81c4d40d91dd0307318dc60244ab311b&w=740"
                        alt=""
                    />
                </div>
                <div class="border rounded-2xl overflow-hidden">
                    <img
                        class="h-full w-full object-cover"
                        src="https://img.freepik.com/premium-photo/luxury-room-with-bed-warm-light_24637-603.jpg?w=740"
                        alt=""
                    />
                </div>
                <div class="border rounded-2xl overflow-hidden">
                    <img
                        class="h-full w-full object-cover"
                        src="https://img.freepik.com/free-photo/pillow-bed_1203-2819.jpg?t=st=1714557491~exp=1714561091~hmac=d7c64561e0f34f7d0c1741e92428b5ed81c4d40d91dd0307318dc60244ab311b&w=740"
                        alt=""
                    />
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <h1 class="section-heading text-3xl text-gray-700">
                Hollywood Historic Hotel
            </h1>
            <div class="flex items-center md:justify-end gap-3">
                <Button title="Book Now" bordered class="text-sm" />
                <Button icon="fa-regular fa-heart" class="w-16" />
            </div>
        </div>
        <p class="text-sm text-gray-500 mt-4 md:mt-0">
            <i class="fa-solid fa-location-dot mr-1"></i> Comfortable Los
            Angeles hotel in Hollywood with 24-hour front desk
        </p>
        <p class="text-sm text-gray-500 my-4">13+ Reviews</p>

        <div class="flex flex-wrap gap-x-3">
            <p
                class="text-sm text-gray-500 bg-gray-100 rounded-full px-4 py-2 w-fit mt-2 whitespace-nowrap"
            >
                <i class="fa-solid fa-check mr-1"></i> Refundable
            </p>
            <p
                class="text-sm text-gray-500 bg-gray-100 rounded-full px-4 py-2 w-fit mt-2 whitespace-nowrap"
            >
                <i class="fa-solid fa-check mr-1"></i> Reserve now, pay later
            </p>
            <p
                class="text-sm text-gray-500 bg-gray-100 rounded-full px-4 py-2 w-fit mt-2 whitespace-nowrap"
            >
                <i class="fa-solid fa-check mr-1"></i> Fully Approved
            </p>
        </div>

        <h1 class="my-4 text-lg font-medium">Comforts</h1>
        <div class="grid md:grid-cols-4 gap-4">
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-solid fa-mug-saucer mr-2"></i>Break Fast
                </h1>
            </div>
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-solid fa-wifi mr-2"></i>Free Wifi
                </h1>
            </div>
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-regular fa-snowflake mr-2"></i>Air Conditioning
                </h1>
            </div>
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-solid fa-car mr-2"></i>Parking
                </h1>
            </div>
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-solid fa-utensils mr-2"></i>Restaurant
                </h1>
            </div>
            <div class="card border rounded-2xl p-4 w-auto">
                <h1 class="text-xl font-medium">
                    <i class="fa-solid fa-wine-glass mr-2"></i>Bar
                </h1>
            </div>
        </div>

        <h1 class="my-4 text-lg font-medium">Location</h1>
        <div class="card border rounded-2xl h-[200px] overflow-hidden">
            <img
                class="w-full h-full object-cover"
                src="https://media.wired.com/photos/59269cd37034dc5f91bec0f1/master/pass/GoogleMapTA.jpg"
                alt=""
            />
        </div>

        <h1 class="my-4 text-lg font-medium">Description</h1>
        <div class="card border rounded-2xl p-4">
            <h1 class="font-medium">Hollywood Historic Hotel</h1>
            <p class="text-sm text-gray-500">
                Historic hotel near Hollywood Boulevard You can look forward to
                a coffee shop/cafe, dry cleaning/laundry services, and a bar at
                Hollywood Historic Hotel. The on-site restaurant, The Edmon,
                features happy hour and light fare. Guests can connect to free
                in-room WiFi. Other perks include: Breakfast (surcharge), self
                parking (surcharge), and a front-desk safe Outdoor furniture,
                smoke-free premises, and luggage storage A 24-hour front desk,
                an elevator, and concierge services Guest reviews speak well of
                the dining options, bar, and helpful staff Room features All 62
                rooms boast comforts such as premium bedding and air
                conditioning, in addition to perks like free WiFi and desk
                chairs. Guest reviews give good marks for the clean, spacious
                rooms at the property. Other amenities include: Bathrooms with
                showers and shower/tub combinations Flat-screen TVs with premium
                channels Refrigerators, daily housekeeping, and desks.

                <br />
                <br />
                <br />

                Historic hotel near Hollywood Boulevard You can look forward to
                a coffee shop/cafe, dry cleaning/laundry services, and a bar at
                Hollywood Historic Hotel. The on-site restaurant, The Edmon,
                features happy hour and light fare. Guests can connect to free
                in-room WiFi. Other perks include: Breakfast (surcharge), self
                parking (surcharge), and a front-desk safe Outdoor furniture,
                smoke-free premises, and luggage storage A 24-hour front desk,
                an elevator, and concierge services Guest reviews speak well of
                the dining options, bar, and helpful staff Room features All 62
                rooms boast comforts such as premium bedding and air
                conditioning, in addition to perks like free WiFi and desk
                chairs. Guest reviews give good marks for the clean, spacious
                rooms at the property. Other amenities include: Bathrooms with
                showers and shower/tub combinations Flat-screen TVs with premium
                channels Refrigerators, daily housekeeping, and desks

                <br />
                <br />

                Historic hotel near Hollywood Boulevard You can look forward to
                a coffee shop/cafe, dry cleaning/laundry services, and a bar at
                Hollywood Historic Hotel. The on-site restaurant, The Edmon,
                features happy hour and light fare. Guests can connect to free
                in-room WiFi. Other perks include: Breakfast (surcharge), self
                parking (surcharge), and a front-desk safe Outdoor furniture,
                smoke-free premises, and luggage storage A 24-hour front desk,
                an elevator, and concierge services Guest reviews speak well of
                the dining options, b
            </p>
        </div>

        <div>
            <h1 class="text-3xl my-12 section-heading">
                Best stays for you nearby
            </h1>
            <ul class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <li
                    class="card border bg-white rounded-2xl p-2 h-[400px] hover:shadow-lg transition-all duration-300 cursor-pointer"
                >
                    <div
                        class="border h-[200px] rounded-2xl overflow-hidden flex items-center justify-center"
                    >
                        <img
                            class="w-full h-full object-cover"
                            src="https://img.freepik.com/free-photo/modern-studio-apartment-design-with-bedroom-living-space_1262-12375.jpg?t=st=1714556244~exp=1714559844~hmac=c4ebfdf927c51f3b8a6b897ea5ee4445e6fe1d87e9254eca0fb2e356cdf4e31f&w=1060"
                            alt=""
                        />
                    </div>
                    <div class="p-2">
                        <p class="text-lg">Brasada Ranch</p>
                        <p class="text-sm text-gray-500 font-semibold">
                            Islamabad
                        </p>
                        <p class="text-sm text-gray-500">
                            4.5 rating - 193+ reviews
                        </p>
                        <p class="text-sm text-gray-500">
                            Includes taxes & fees
                        </p>
                        <div class="flex items-center justify-between">
                            <Button
                                @click="
                                    {
                                        {
                                            $router.push({
                                                name: 'HotelDetails',
                                            });
                                        }
                                    }
                                "
                                title="Details"
                                bordered
                                class="mt-2"
                            />
                            <p class="text-lg">1374$ / night</p>
                        </div>
                    </div>
                </li>
                <li
                    class="card border bg-white rounded-2xl p-2 h-[400px] hover:shadow-lg transition-all duration-300 cursor-pointer"
                >
                    <div
                        class="border h-[200px] rounded-2xl overflow-hidden flex items-center justify-center"
                    >
                        <img
                            class="w-full h-full object-cover"
                            src="https://img.freepik.com/free-photo/pillow-bed_1203-2819.jpg?t=st=1714557491~exp=1714561091~hmac=d7c64561e0f34f7d0c1741e92428b5ed81c4d40d91dd0307318dc60244ab311b&w=740"
                            alt=""
                        />
                    </div>
                    <div class="p-2">
                        <p class="text-lg">Brasada Ranch</p>
                        <p class="text-sm text-gray-500 font-semibold">
                            Islamabad
                        </p>
                        <p class="text-sm text-gray-500">
                            4.5 rating - 193+ reviews
                        </p>
                        <p class="text-sm text-gray-500">
                            Includes taxes & fees
                        </p>
                        <div class="flex items-center justify-between">
                            <Button
                                @click="
                                    {
                                        {
                                            $router.push({
                                                name: 'HotelDetails',
                                            });
                                        }
                                    }
                                "
                                title="Details"
                                bordered
                                class="mt-2"
                            />
                            <p class="text-lg">1374$ / night</p>
                        </div>
                    </div>
                </li>
                <li
                    class="card border bg-white rounded-2xl p-2 h-[400px] hover:shadow-lg transition-all duration-300 cursor-pointer"
                >
                    <div
                        class="border h-[200px] rounded-2xl overflow-hidden flex items-center justify-center"
                    >
                        <img
                            class="w-full h-full object-cover"
                            src="https://img.freepik.com/premium-photo/luxury-room-with-bed-warm-light_24637-603.jpg?w=740"
                            alt=""
                        />
                    </div>
                    <div class="p-2">
                        <p class="text-lg">Brasada Ranch</p>
                        <p class="text-sm text-gray-500 font-semibold">
                            Islamabad
                        </p>
                        <p class="text-sm text-gray-500">
                            4.5 rating - 193+ reviews
                        </p>
                        <p class="text-sm text-gray-500">
                            Includes taxes & fees
                        </p>
                        <div class="flex items-center justify-between">
                            <Button
                                @click="
                                    {
                                        {
                                            $router.push({
                                                name: 'HotelDetails',
                                            });
                                        }
                                    }
                                "
                                title="Details"
                                bordered
                                class="mt-2"
                            />
                            <p class="text-lg">1374$ / night</p>
                        </div>
                    </div>
                </li>
                <li
                    class="card border bg-white rounded-2xl p-2 h-[400px] hover:shadow-lg transition-all duration-300 cursor-pointer"
                >
                    <div
                        class="border h-[200px] rounded-2xl overflow-hidden flex items-center justify-center"
                    >
                        <img
                            class="w-full h-full object-cover"
                            src="https://img.freepik.com/premium-photo/high-end-clean-atmospheric-hotel-rooms_149197-85.jpg?w=1060"
                            alt=""
                        />
                    </div>
                    <div class="p-2">
                        <p class="text-lg line-clamp-2">Cottage on Armstrong</p>
                        <p class="text-sm text-gray-500 font-semibold">
                            Karachi
                        </p>
                        <p class="text-sm text-gray-500">
                            4.5 rating - 193+ reviews
                        </p>
                        <p class="text-sm text-gray-500">
                            Includes taxes & fees
                        </p>
                        <div class="flex items-center justify-between">
                            <Button
                                @click="
                                    {
                                        {
                                            $router.push({
                                                name: 'HotelDetails',
                                            });
                                        }
                                    }
                                "
                                title="Details"
                                bordered
                                class="mt-2"
                            />
                            <p class="text-lg">1374$ / night</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
