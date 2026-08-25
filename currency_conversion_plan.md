CURRENCY CONVERSION PLAN (Flights App)
======================================

Goal
----
Admin ka base/accounting currency AED rahega. Supplier API PKR, AED, USD ya
kisi bhi currency mein price bhej sakti hai. Agent/customer switcher selected
currency mein correctly converted prices show karega.

Golden Rule
-----------
Booking, payment, invoice aur ledger ki old amount kabhi rate update ki wajah
se change nahi hogi.

Admin rate update ke baad:
- New searches aur new quotes updated rate use karengi.
- Old booked/paid/invoiced record same rahega.
- Old booking ka current conversion dikhana ho to usay "Current indicative
  equivalent" naam se separately dikhayen. Yeh actual booking amount nahi.


1. Currency Rate Ka One Clear Meaning
-------------------------------------
Har rate ka format hoga:

  1 currency unit = kitne AED

Examples:
- AED rate = 1
- PKR rate = AED value of 1 PKR
- USD rate = AED value of 1 USD

Formula:

  target amount = source amount x source AED rate / target AED rate


2. Transformer Ka Kaam
----------------------
Har transformer (AtFlightTransformer, PIA, TravelPort, OneApi etc.) sirf
supplier ka original price aur original currency preserve karega.

Example:

  supplier_fare: 125000.00 PKR
  taxes: 5000.00 PKR

Transformer selected UI currency mein conversion nahi karega. Conversion aik
central Pricing/Currency Service karegi. Is se all providers ka behaviour same
rahega.

Important AT fix:
- AtFlightTransformer mein passenger fare currency PKR hard-code hai.
- Is hard-code ko remove karke original supplier currency consistently use
  karni hogi.


3. Search Aur Switcher Flow
---------------------------
1. User/agent currency select kare (for example USD).
2. Backend supplier se flight result receive kare.
3. Transformer original supplier money preserve kare.
4. Central Pricing Service original price ko AED base mein calculate kare.
5. Service AED base se selected USD price calculate kare.
6. Frontend returned converted money ko only format/show kare.

Frontend khud exchange-rate API call karke conversion nahi karega.
Frontend ka formatter symbol change nahi, actual converted money object show
karega.


4. Rate Management (Admin)
--------------------------
Currency ko delete karne ke bajaye disable/archive karein.

Rate update ka matlab old rate overwrite karna nahi hoga. Har update aik new
rate version create karega.

Har rate change mein save karein:
- currency code
- old rate and new rate
- effective date/time
- changed by (admin ID)
- reason for change
- approved by (agar approval flow chahiye)
- source (manual/provider/import)

Admin screen par rate label clearly likhein:

  "1 PKR = 0.0xxx AED"


5. Important New Tables
-----------------------

A. currencies
- code, name, symbol, decimal_places
- is_enabled
- base currency AED fixed

B. currency_rate_versions
- currency_code, base_currency_code (AED), rate_to_base, effective_at
- old_rate, new_rate, changed_by, reason, source

C. price_quotes
- quote ID, provider/supplier, expiry time
- supplier original amount and currency
- selected currency/displayed selling amount
- AED base amount, rate-version IDs
- margins, discounts and add-ons details

D. booking_money_lines
- booking ID
- line type: fare, tax, seat, meal, markup, discount, payment fee, refund
- original supplier amount + currency
- final selling amount + currency
- AED base/accounting amount
- rate-version IDs and rounding detail

E. booking_adjustments
- Original booking amount update nahi hoga.
- Extra charge/discount/manual correction new adjustment row banegi.
- Admin ID, reason and timestamp mandatory honge.


6. Booking Flow (Most Important)
--------------------------------
Frontend booking request mein direct amount trust nahi karni.
Frontend sirf quote_id bheje.

Backend process:
1. Quote load kare.
2. Check kare quote expire to nahi hui.
3. Supplier se reprice/price validation kare where supported.
4. Price change ho to new quote user ko show kare.
5. Same price ho to booking monetary snapshot lock kare.
6. Supplier booking original supplier amount/currency ke saath kare.
7. Payment, invoice aur ledger locked snapshot se banaye.

Supplier ko converted display amount kabhi send nahi karni. Supplier ko uski
original confirmed currency aur amount hi send hogi.


7. Multiple API Calls / Complete Booking Lifecycle
---------------------------------------------------
Single booking mein supplier APIs multiple times call hoti hain. Har stage ko
same currency rules follow karni hongi:

1. Search
   - Supplier ka original fare/currency receive karein.
   - Display quote create karein.

2. Fare details / availability / reprice
   - Supplier ka new response preserve karein.
   - Agar amount ya currency differs ho, new quote version create karein.
   - User ko price change clearly show karein; old quote silently replace na ho.

3. Ancillaries (seat, meal, baggage)
   - Har add-on ki apni amount aur currency save karein.
   - Add-on ko flight fare ki currency assume na karein.
   - Har line separately convert aur lock hogi.

4. Hold / booking / ticketing
   - Provider ko original confirmed supplier amount/currency send karein.
   - Booking ke waqt final locked booking snapshot create karein.

5. Booking ke baad PNR/details fetch
   - Supplier response ko post-booking response history mein save karein.
   - PNR, ticket number, status, schedule aur baggage status update ho sakte
     hain.
   - Locked booked/payment/invoice amount overwrite nahi hogi.
   - Agar supplier final ticketed amount different return kare, difference ko
     reconciliation/adjustment event bana kar admin review mein bhejein.

6. Cancel / void / refund
   - Original supplier currency/amount use karein, as required by provider.
   - Refund ya penalty ko new money line/event ke taur par save karein.
   - Original booking snapshot unchanged rahega.

Har API call ke saath yeh context attach/store hoga:
- booking_id (booking se pehle quote_id)
- provider name
- stage: search, reprice, ancillary, hold, book, ticket, post_booking_fetch,
  cancel, void, refund
- supplier request/response reference or safely redacted payload hash
- returned amount(s), returned currency/currencies
- quote/snapshot version and rate-version IDs
- API timestamp and correlation/request ID

Simple rule:

  API response operational information update kar sakta hai,
  lekin locked financial snapshot ko overwrite nahi kar sakta.


8. Payment, Invoice Aur Ledger
------------------------------
- Payment amount backend locked booking snapshot se aaye.
- Frontend se amount/currency receive karke payment create nahi karna.
- Invoice mein locked selling currency, amount aur rate version show ho.
- Ledger mein AED accounting value aur original transaction currency dono save
  hon.
- Refund/void/cancel aik new financial entry ho; original entry mutate na ho.


9. Precision And Rounding
-------------------------
- PHP/JavaScript float use na karein for financial calculation.
- Database rates: DECIMAL(20,8) ya greater precision.
- Amounts: DECIMAL(20,6) internally; final display/payment currency ke decimal
  rules ke mutabiq round ho.
- Rounding sirf final stage par ho, har intermediate calculation par nahi.


10. Audit Trail Rules
---------------------
Financial audit trail append-only hoga:
- Rate version update/delete allowed nahi.
- Booked price update/delete allowed nahi.
- Manual change separate adjustment/event ke through ho.
- Every event mein user/admin, time, reason, old/new values aur request ID save
  ho.

Existing route activity logs helpful hain, lekin financial audit trail ke liye
sufficient nahi because they can be deleted and they do not store locked money
snapshots.


11. Implementation Phases
-------------------------
Phase 1 - Foundation
- AED base setting confirm karein.
- Currency rate meaning and labels fix karein.
- Rate precision improve karein.
- Currency delete disable karein.
- Rate version and audit tables banayein.

Phase 2 - Central Pricing
- CurrencyConversionService / PricingService banayein.
- PKR, AED, USD conversion tests likhein.
- All transformers ko original money output contract par laayen.
- Start with AtFlightTransformer hard-coded PKR fix.

Phase 3 - Search/UI
- Search response mein supplier money, AED amount and selected display money
  return karein.
- Shared Money formatter/component banayein.
- Old formatAmount(amount) usage gradually replace karein.
- External frontend exchange-rate API remove karein.

Phase 4 - Booking Lock
- price_quotes table introduce karein.
- Booking endpoint quote_id accept kare.
- Booking money snapshot and money lines save karein.
- Reprice/expiry handling add karein.

Phase 5 - Payments And Reports
- Payment intent locked amount se create karein.
- Invoice, ledger, deposits, refunds and reports ko money lines use karwayein.
- Historical records ko legacy currency unknown marker ke saath preserve karein;
  unke historical rates guess na karein.


Definition Of Done
------------------
- PKR/AED/USD supplier result selected currency mein correctly display ho.
- Switcher symbol nahi, real amount conversion kare.
- Supplier booking original supplier currency mein ho.
- Admin rate update future quotes ko affect kare.
- Existing booking/payment/invoice/ledger amount unchanged rahe.
- Har rate change, booking price lock and manual adjustment audit mein visible ho.
- Price mismatch/expired quote safely reprice ho instead of wrong amount booking.
- Post-booking fetch operational details update kare, financial snapshot nahi.
