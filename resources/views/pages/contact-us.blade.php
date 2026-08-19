@extends('layouts.marketing')

@section('title', 'Contact Us')
@section('description', 'Contact Jetze for bookings, travel inquiries, and 24/7 support.')

@section('content')
<section class="h-64 bg-white">
    <div class="container mx-auto flex h-full items-center px-6">
        <div>
            <h1 class="text-4xl font-bold text-secondary md:text-5xl">{{ $seo?->h1 ?: 'Contact Jetze' }}</h1>
            <div class="mt-4 h-1 w-24 bg-primary"></div>
            <p class="mt-6 max-w-2xl text-lg text-gray-900">Professional travel solutions with 24/7 support. Reach out for bookings, inquiries, or assistance.</p>
        </div>
    </div>
</section>

<section class="bg-white py-16">
    <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <aside>
                <div class="mb-8">
                    <div class="mb-6 flex items-center">
                        <div class="mr-3 flex h-10 w-10 items-center justify-center bg-primary text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 00-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900">Quick Contact</h2>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-5 transition hover:bg-gray-100">
                            <div class="mb-1 text-sm font-medium text-gray-500">24/7 Support Line (UAN)</div>
                            <a href="tel:+923007690691" class="text-lg font-bold text-gray-900">+92 300 7690691</a>
                        </div>
                        <div class="bg-gray-50 p-5 transition hover:bg-gray-100">
                            <div class="mb-1 text-sm font-medium text-gray-500">Email Support</div>
                            <a href="mailto:support@Jetze.pk" class="break-all text-lg font-bold text-gray-900">support@Jetze.pk</a>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h3 class="mb-4 text-lg font-bold text-gray-900">Office Hours</h3>
                    <div class="bg-gray-50 p-5 text-sm">
                        <div class="flex justify-between border-b border-gray-200 py-3"><span class="text-gray-700">Monday - Friday</span><span class="font-medium text-gray-900">24/7</span></div>
                        <div class="flex justify-between border-b border-gray-200 py-3"><span class="text-gray-700">Saturday - Sunday</span><span class="font-medium text-gray-900">24/7</span></div>
                        <div class="flex justify-between py-3"><span class="text-gray-700">Emergency Support</span><span class="font-medium text-green-600">Always Available</span></div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-4 text-lg font-bold text-gray-900">Our Offices</h3>
                    <div class="space-y-3">
                        @php
                            $offices = [
                                [
                                    'code' => 'LHE',
                                    'tag' => 'Head Office',
                                    'address' => 'Office No. 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Main Boulevard, Gulberg III, Lahore 54660, Pakistan.',
                                    'phone' => '+92 300 7690691',
                                    'tel' => '+923007690691',
                                ],
                                [
                                    'code' => 'DXB',
                                    'tag' => 'Regional Office',
                                    'address' => 'Office 14, First Floor, Dubai National Insurance Building, Opposite Deira City Centre, Port Saeed, Deira, Dubai, United Arab Emirates.',
                                    'phone' => '+971 54 5299909',
                                    'tel' => '+971545299909',
                                ],
                                [
                                    'code' => 'MNL',
                                    'tag' => 'Regional Office',
                                    'address' => 'Corporate Plaza, High Street South, Makati City 1630, Metro Manila, Philippines.',
                                    'phone' => '+63 908 3986939',
                                    'tel' => '+639083986939',
                                ],
                            ];
                        @endphp
                        @foreach ($offices as $office)
                            <div class="bg-gray-50 p-5">
                                <div class="flex items-start">
                                    <div class="mr-3 mt-1 flex h-8 w-8 shrink-0 items-center justify-center bg-primary text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="mb-1 flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900">{{ $office['code'] }}</span>
                                            <span class="rounded bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{{ $office['tag'] }}</span>
                                        </div>
                                        <p class="text-sm leading-relaxed text-gray-700">{{ $office['address'] }}</p>
                                        <a href="tel:{{ $office['tel'] }}" class="mt-1 inline-block text-sm font-medium text-gray-900 transition hover:text-primary">{{ $office['phone'] }}</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            <div class="lg:col-span-2">
                <div class="mb-10">
                    <h2 class="mb-4 text-3xl font-bold text-gray-900">Send Us a Message</h2>
                    <p class="text-gray-600">Fill out the form below and our team will get back to you within 1 hour. For urgent matters, please call our 24/7 support line.</p>
                </div>
                <form id="contact-form" class="space-y-6">
                    <input name="website" type="text" autocomplete="off" tabindex="-1" class="hidden" aria-hidden="true">
                    <input id="form-started-at" name="form_started_at" type="hidden">
                    <div id="contact-success" class="hidden border border-green-200 bg-green-50 p-4 text-green-700">Your message has been sent successfully. Our team will contact you shortly.</div>
                    <div id="contact-error" class="hidden border border-red-200 bg-red-50 p-4 text-red-700"></div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Full Name<input name="name" type="text" required maxlength="120" class="mt-2 w-full border border-gray-300 px-4 py-3 outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Enter your full name"></label>
                        <label class="block text-sm font-medium text-gray-700">Email Address<input name="email" type="email" required maxlength="150" class="mt-2 w-full border border-gray-300 px-4 py-3 outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="you@example.com"></label>
                    </div>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <label class="block text-sm font-medium text-gray-700">Phone Number<input name="phone" type="tel" required maxlength="40" class="mt-2 w-full border border-gray-300 px-4 py-3 outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="+92 300 1234567"></label>
                        <label class="block text-sm font-medium text-gray-700">Subject<select name="subject" required class="mt-2 w-full border border-gray-300 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-1 focus:ring-primary"><option value="">Select a topic</option><option>Flight Booking</option><option>Visa Services</option><option>Hotel Booking</option><option>Group Travel</option><option>Customer Support</option><option>Other</option></select></label>
                    </div>
                    <label class="block text-sm font-medium text-gray-700">Message<textarea name="message" rows="6" required minlength="10" maxlength="5000" class="mt-2 w-full resize-none border border-gray-300 px-4 py-3 outline-none focus:border-primary focus:ring-1 focus:ring-primary" placeholder="Please describe your inquiry in detail..."></textarea></label>
                    <div class="flex flex-col gap-4 pt-2 sm:flex-row">
                        <button id="contact-submit" type="submit" class="flex items-center justify-center gap-3 bg-primary px-8 py-4 font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.77 59.77 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                            <span id="contact-submit-label">Send Message</span>
                        </button>
                        <button id="contact-reset" type="reset" class="border border-gray-300 px-8 py-4 font-medium text-gray-700 transition hover:bg-gray-50">Clear Form</button>
                    </div>
                </form>

                <div class="mt-16">
                    <h3 class="mb-6 text-xl font-bold text-gray-900">Other Ways to Contact Us</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <a href="https://wa.me/00923007690691" target="_blank" rel="noopener" class="flex items-center gap-3 border border-green-100 bg-green-50 p-3 transition hover:bg-green-100">
                            <img src="{{ asset('assets/whatsapp.png') }}" alt="WhatsApp" class="h-16 w-16 shrink-0 object-contain" />
                            <span><strong class="block text-gray-900">WhatsApp</strong><small class="text-gray-600">Instant chat support</small></span>
                        </a>
                        <a href="mailto:support@Jetze.pk" class="flex items-center gap-3 border border-blue-100 bg-blue-50 p-3 transition hover:bg-blue-100">
                            <img src="{{ asset('assets/email.png') }}" alt="Email" class="h-10 w-10 shrink-0 object-contain" />
                            <span class="min-w-0"><strong class="block text-gray-900">Email</strong><small class="break-all text-gray-600">support@Jetze.pk</small></span>
                        </a>
                        <a href="tel:+00923007690691" class="flex items-center gap-3 border border-purple-100 bg-purple-50 p-3 transition hover:bg-purple-100">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 00-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                            </span>
                            <span><strong class="block text-gray-900">Phone Call</strong><small class="text-gray-600">+92 300 7690691</small></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-16">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-bold text-gray-900">Find Our Office</h2>
                <div class="inline-flex self-start bg-gray-100 p-1" id="office-map-tabs">
                    <button type="button" data-office="LHE" class="office-map-tab bg-primary px-4 py-2 text-sm font-medium text-white transition" data-address="Office No. 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Main Boulevard, Gulberg III, Lahore 54660, Pakistan">LHE — Lahore</button>
                    <button type="button" data-office="DXB" class="office-map-tab px-4 py-2 text-sm font-medium text-gray-600 transition hover:text-primary" data-address="Office 14, First Floor, Dubai National Insurance Building, Opposite Deira City Centre, Port Saeed, Deira, Dubai, United Arab Emirates">DXB — Dubai</button>
                    <button type="button" data-office="MNL" class="office-map-tab px-4 py-2 text-sm font-medium text-gray-600 transition hover:text-primary" data-address="Corporate Plaza, High Street South, Makati City 1630, Metro Manila, Philippines">MNL — Manila</button>
                </div>
            </div>
            <div class="h-96">
                <iframe id="office-map-frame" src="https://www.google.com/maps?q={{ urlencode('Office No. 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Main Boulevard, Gulberg III, Lahore 54660, Pakistan') }}&output=embed" class="h-full w-full" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Jetze Office Location"></iframe>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contact-form');
    const startedAt = document.getElementById('form-started-at');
    const submit = document.getElementById('contact-submit');
    const submitLabel = document.getElementById('contact-submit-label');
    const success = document.getElementById('contact-success');
    const errorBox = document.getElementById('contact-error');
    const resetStartedAt = () => { startedAt.value = Date.now(); };
    resetStartedAt();

    form.addEventListener('reset', () => {
        success.classList.add('hidden');
        errorBox.classList.add('hidden');
        setTimeout(resetStartedAt);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        success.classList.add('hidden');
        errorBox.classList.add('hidden');
        submit.disabled = true;
        submitLabel.textContent = 'Sending Message...';
        const payload = Object.fromEntries(new FormData(form).entries());
        payload.form_started_at = Number(payload.form_started_at);

        try {
            const response = await fetch('{{ url('/api/contact-messages') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validationMessage || data.message || 'Unable to send your message right now. Please try again.');
            }
            success.classList.remove('hidden');
            form.reset();
            success.classList.remove('hidden');
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('hidden');
        } finally {
            submit.disabled = false;
            submitLabel.textContent = 'Send Message';
        }
    });

    // Office map switcher
    const tabs = document.querySelectorAll('.office-map-tab');
    const mapFrame = document.getElementById('office-map-frame');
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((t) => {
                t.classList.remove('bg-primary', 'text-white');
                t.classList.add('text-gray-600');
            });
            tab.classList.add('bg-primary', 'text-white');
            tab.classList.remove('text-gray-600');
            mapFrame.src = `https://www.google.com/maps?q=${encodeURIComponent(tab.dataset.address)}&output=embed`;
        });
    });
});
</script>
@endpush
