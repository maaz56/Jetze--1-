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
                        <div class="mr-3 flex h-10 w-10 items-center justify-center bg-primary text-white">☎</div>
                        <h2 class="text-2xl font-bold text-gray-900">Quick Contact</h2>
                    </div>
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-5 transition hover:bg-gray-100"><div class="mb-1 text-sm font-medium text-gray-500">24/7 Support Line</div><a href="tel:+923111711123" class="text-lg font-bold text-gray-900">+92 00000000</a></div>
                        <div class="bg-gray-50 p-5 transition hover:bg-gray-100"><div class="mb-1 text-sm font-medium text-gray-500">Email Support</div><a href="mailto:support@Jetze.pk" class="break-all text-lg font-bold text-gray-900">support@Jetze.pk</a></div>
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
                    <h3 class="mb-4 text-lg font-bold text-gray-900">Head Office</h3>
                    <div class="bg-gray-50 p-5"><div class="flex items-start"><div class="mr-3 mt-1 flex h-8 w-8 items-center justify-center bg-primary text-white">●</div><p class="text-gray-700">Address line 1<br>Address line 2<br>State</p></div></div>
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
                        <button id="contact-submit" type="submit" class="bg-primary px-8 py-4 font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">Send Message</button>
                        <button id="contact-reset" type="reset" class="border border-gray-300 px-8 py-4 font-medium text-gray-700 transition hover:bg-gray-50">Clear Form</button>
                    </div>
                </form>

                <div class="mt-16">
                    <h3 class="mb-6 text-xl font-bold text-gray-900">Other Ways to Contact Us</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <a href="https://wa.me/923111711123" target="_blank" rel="noopener" class="flex items-center gap-3 border border-green-100 bg-green-50 p-5 transition hover:bg-green-100"><span class="flex h-10 w-10 items-center justify-center bg-green-600 text-white">W</span><span><strong class="block text-gray-900">WhatsApp</strong><small class="text-gray-600">Instant chat support</small></span></a>
                        <a href="mailto:support@Jetze.pk" class="flex items-center gap-3 border border-blue-100 bg-blue-50 p-5 transition hover:bg-blue-100"><span class="flex h-10 w-10 items-center justify-center bg-blue-600 text-white">@</span><span class="min-w-0"><strong class="block text-gray-900">Email</strong><small class="break-all text-gray-600">support@Jetze.pk</small></span></a>
                        <a href="tel:+923111711123" class="flex items-center gap-3 border border-purple-100 bg-purple-50 p-5 transition hover:bg-purple-100"><span class="flex h-10 w-10 items-center justify-center bg-purple-600 text-white">☎</span><span><strong class="block text-gray-900">Phone Call</strong><small class="text-gray-600">+92 00000000</small></span></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-16">
            <h2 class="mb-6 text-2xl font-bold text-gray-900">Find Our Office</h2>
            <div class="h-96"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d105846.83214733186!2d71.39277959726564!3d33.9998919!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x38d917a16b712b9b%3A0x300a41edde3f384e!2sEtimad%20Travels%20Peshawar!5e0!3m2!1sen!2s!4v1772100822225!5m2!1sen!2s" class="h-full w-full" style="border:0" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Jetze Office Location"></iframe></div>
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
        submit.textContent = 'Sending Message...';
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
            submit.textContent = 'Send Message';
        }
    });
});
</script>
@endpush
