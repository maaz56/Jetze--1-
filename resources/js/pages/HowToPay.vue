<template>
    <div class="payment-page">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-overlay"></div>
            <div class="hero-content">
                <h1 class="hero-title">
                    How to pay with
                    <img src="https://1link.net.pk/assets/images/logo.png" alt="1 BILL" class="hero-logo">
                    BILL
                </h1>
                <p class="hero-subtitle">
                    Select your transfer method below to view instructions on how to pay.
                </p>
            </div>
        </section>

        <!-- Payment Details Section -->
        <section class="payment-detail">
            <div class="container">
                <div class="payment-card">
                    <!-- Transfer Method Selection -->
                    <div class="method-selector">
                        <label class="form-label">Select Transfer Method</label>
                        <select v-model="selectedMethod" class="method-select" @change="handleMethodChange">
                            <option value="" disabled>Select your Transfer Method</option>
                            <option value="mobile">Bank Transfer via Mobile App</option>
                            <option value="internet">Bank Transfer via Internet Banking</option>
                            <option value="wallet">Transfer via Digital Wallets</option>
                            <option value="atm">ATM</option>
                            <option value="counter">Over the counter/Pay at TCS</option>
                        </select>
                    </div>

                    <!-- Mobile App Section -->
                    <div v-if="selectedMethod === 'mobile'" class="method-section">
                        <div class="section-header">
                            <h3>Bank Transfer via Mobile App</h3>
                        </div>
                        <div class="banks-grid">
                            <!-- Allied Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'abl-mobile' }"
                                    @click="toggleAccordion('abl-mobile')">
                                    <span class="bank-name">Allied Bank (ABL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'abl-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'abl-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Manage Payees & Billers from Banking Portal</li>
                                        <li>Select Billers from Manage and click on Add new button</li>
                                        <li>Select Category as 1BILL and Biller Name as the Invoice</li>
                                        <li>Enter your Reference Number and Nick Name</li>
                                        <li>Click on validate button to fetch details then click on Add</li>
                                        <li>Click on Confirm</li>
                                        <li>OTP will be sent to Email ID for verification</li>
                                        <li>Enter OTP to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Askari Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'askari-mobile' }"
                                    @click="toggleAccordion('askari-mobile')">
                                    <span class="bank-name">Askari Bank (ASK)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'askari-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'askari-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Habib - Method 1 -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'habib1-mobile' }"
                                    @click="toggleAccordion('habib1-mobile')">
                                    <span class="bank-name">Bank Al Habib (BAHL) - Method 1</span>
                                    <span class="toggle-icon">{{ openAccordion === 'habib1-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'habib1-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Habib - Method 2 -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'habib2-mobile' }"
                                    @click="toggleAccordion('habib2-mobile')">
                                    <span class="bank-name">Bank Al Habib (BAHL) - Method 2</span>
                                    <span class="toggle-icon">{{ openAccordion === 'habib2-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'habib2-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Click on the downwards arrow on the homepage</li>
                                        <li>Click on the Payments tab</li>
                                        <li>Click on 1Bill</li>
                                        <li>Select Payment type as Invoice Payment and Invoice No as Others</li>
                                        <li>Enter your Consumer Number in Invoice No and click on Fetch Details</li>
                                        <li>Select Proceed and Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank of Punjab -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'bop-mobile' }"
                                    @click="toggleAccordion('bop-mobile')">
                                    <span class="bank-name">Bank of Punjab (BOP)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'bop-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'bop-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Dubai Islamic Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'dib-mobile' }"
                                    @click="toggleAccordion('dib-mobile')">
                                    <span class="bank-name">Dubai Islamic Bank (DIB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'dib-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'dib-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select option Transfer & Payments in Mobile App</li>
                                        <li>Select Payments option</li>
                                        <li>Select your biller and enter your Consumer Number</li>
                                        <li>Tap Get Bill</li>
                                        <li>Tap Pay Bill and insert FPIN to complete payment!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Faysal Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'faysal-mobile' }"
                                    @click="toggleAccordion('faysal-mobile')">
                                    <span class="bank-name">Faysal Bank (FBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'faysal-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'faysal-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select option Transfer & Payments in Mobile App</li>
                                        <li>Select Payments option</li>
                                        <li>Enable payment to Non Beneficiary</li>
                                        <li>Select Digital payments</li>
                                        <li>Select 1BILL Invoice</li>
                                        <li>Enter your Consumer Number and tap on Fetch Bill</li>
                                        <li>Generate OTP and input to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Habib Bank Limited -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'hbl-mobile' }"
                                    @click="toggleAccordion('hbl-mobile')">
                                    <span class="bank-name">Habib Bank Limited (HBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'hbl-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'hbl-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to More in Mobile App</li>
                                        <li>Go to 1Bill under Payments</li>
                                        <li>Select 1Bill Invoice as Company</li>
                                        <li>Enter your Consumer Number and click Next</li>
                                        <li>Check Bill details and click on next to Generate OTP</li>
                                        <li>Enter OTP and HPIN to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Habib Metro Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'hmb-mobile' }"
                                    @click="toggleAccordion('hmb-mobile')">
                                    <span class="bank-name">Habib Metro Bank (HMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'hmb-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'hmb-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to the Add Biller section in Mobile App</li>
                                        <li>Under this tab and select 1BILL Invoices and enter your Consumer Number along with Mobile Token and register the biller</li>
                                        <li>After registration Select Payment and Transfers</li>
                                        <li>Select the relevant bill</li>
                                        <li>Enter OTP and Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- JS Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'jsb-mobile' }"
                                    @click="toggleAccordion('jsb-mobile')">
                                    <span class="bank-name">JS Bank (JSB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'jsb-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'jsb-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Bill Payments in Mobile App</li>
                                        <li>Select Bill Registration and select Others</li>
                                        <li>Under others select 1BILL payments and enter your Consumer Number along with your Mobile Token and get registered</li>
                                        <li>Pay the bill</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Meezan Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'meezan-mobile' }"
                                    @click="toggleAccordion('meezan-mobile')">
                                    <span class="bank-name">Meezan Bank (MBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'meezan-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'meezan-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Bills & Top up in Mobile App</li>
                                        <li>Select Add Bills & Mobile Numbers</li>
                                        <li>Select 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number and click Next</li>
                                        <li>Enter Nickname and tap add button</li>
                                        <li>Click on the newly added bill and Pay</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- NRSP Microfinance Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'nrsp-mobile' }"
                                    @click="toggleAccordion('nrsp-mobile')">
                                    <span class="bank-name">NRSP Microfinance Bank (NRSPMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'nrsp-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'nrsp-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Mobile App</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Samba Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'samba-mobile' }"
                                    @click="toggleAccordion('samba-mobile')">
                                    <span class="bank-name">Samba Bank (SAM)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'samba-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'samba-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select My Account in Mobile App</li>
                                        <li>Select Make a Payment from the I'd Like to Option</li>
                                        <li>Select Tab 1BILL Payment</li>
                                        <li>Select Invoice/Voucher Payment Option</li>
                                        <li>Enter your Reference Number into the provided field</li>
                                        <li>Voucher details will appear on the screen and click on proceed</li>
                                        <li>Enter IPIN for Final Transaction and complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Silk Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'silk-mobile' }"
                                    @click="toggleAccordion('silk-mobile')">
                                    <span class="bank-name">Silk Bank (SLK)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'silk-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'silk-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payments in Mobile App</li>
                                        <li>Select Bill Type as 1BILL</li>
                                        <li>Enter Consumer Number</li>
                                        <li>Tap on Next Button to fetch Bill</li>
                                        <li>Generate OTP and input OTP to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Standard Chartered -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'scb-mobile' }"
                                    @click="toggleAccordion('scb-mobile')">
                                    <span class="bank-name">Standard Chartered (SCB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'scb-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'scb-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Tap to Move option in Mobile App</li>
                                        <li>Tap on Add Payee option, and then Tap Add Bill Payee</li>
                                        <li>Search and Select 1BILL after selection there should be an Invoice/ Vouchers option</li>
                                        <li>Enter your Reference Number into the Provided Field & Nickname then Tap Next</li>
                                        <li>Enter Passcode which you will receive on your SMS or on your bank accounts registered email address and Tap Next</li>
                                        <li>Bill will get registered, Tap Pay Now</li>
                                        <li>Validate Bill details and Tap Next to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Summit Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'summit-mobile' }"
                                    @click="toggleAccordion('summit-mobile')">
                                    <span class="bank-name">Summit Bank (SUM)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'summit-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'summit-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Tap to Move option in Mobile App</li>
                                        <li>Tap on Payment & Transfers option, and then Tap Payments</li>
                                        <li>Select 1BILL after selection there should be a field of Company Name: Invoices/ Voucher and Voucher/ Token Number</li>
                                        <li>Select 1BILL Type (Topup/Invoice/Credit Card) , enter your Consumer Number and Tap on the Confirm button</li>
                                        <li>Enter your Passcode and you will receive an SMS or an email, and then Tap Next</li>
                                        <li>Tap on Confirm button to confirm the transaction</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- The First Microfinance Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'tfmb-mobile' }"
                                    @click="toggleAccordion('tfmb-mobile')">
                                    <span class="bank-name">The First Microfinance Bank (TFMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'tfmb-mobile' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'tfmb-mobile'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Mobile App</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Internet Banking Section -->
                    <div v-if="selectedMethod === 'internet'" class="method-section">
                        <div class="section-header">
                            <h3>Bank Transfer via Internet Banking</h3>
                        </div>
                        <div class="banks-grid">
                            <!-- Allied Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'abl-internet' }"
                                    @click="toggleAccordion('abl-internet')">
                                    <span class="bank-name">Allied Bank (ABL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'abl-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'abl-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Manage Payees & Billers from Banking Portal</li>
                                        <li>Select Billers from Manage and click on Add new button</li>
                                        <li>Select Category as 1BILL and Biller Name as the Invoice</li>
                                        <li>Enter your Reference Number and Nick Name</li>
                                        <li>Click on validate button to fetch details then click on Add</li>
                                        <li>Click on Confirm</li>
                                        <li>OTP will be sent to Email ID for verification</li>
                                        <li>Enter OTP to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Askari Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'askari-internet' }"
                                    @click="toggleAccordion('askari-internet')">
                                    <span class="bank-name">Askari Bank (ASK)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'askari-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'askari-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Habib -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'habib-internet' }"
                                    @click="toggleAccordion('habib-internet')">
                                    <span class="bank-name">Bank Al Habib (BAHL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'habib-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'habib-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Add/Manage Payees & Billers in Banking Portal</li>
                                        <li>Select Billers from Manage</li>
                                        <li>Click on Add New Biller button</li>
                                        <li>Select Bill Category as 1BILL and Biller Name as Invoice voucher Payment</li>
                                        <li>Enter your Consumer Number and Nick Name and click on Add button</li>
                                        <li>Once details are fetched click on Confirm</li>
                                        <li>Get OTP from email ID and input to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Falah -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'bafl-internet' }"
                                    @click="toggleAccordion('bafl-internet')">
                                    <span class="bank-name">Bank Al Falah (BAFL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'bafl-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'bafl-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Dubai Islamic Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'dib-internet' }"
                                    @click="toggleAccordion('dib-internet')">
                                    <span class="bank-name">Dubai Islamic Bank (DIB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'dib-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'dib-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Option Bill from registered bills in Banking Portal</li>
                                        <li>Select your registered Consumer Number and enter your Reference Number</li>
                                        <li>Select button Get Bill</li>
                                        <li>Select button Pay Bill and enter FPIN to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Faysal Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'faysal-internet' }"
                                    @click="toggleAccordion('faysal-internet')">
                                    <span class="bank-name">Faysal Bank (FBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'faysal-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'faysal-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to payments and select Bill Payments in Banking Portal</li>
                                        <li>Select Digital Payments from Payment category</li>
                                        <li>Select 1BILL Invoice or Top up from Payment Company</li>
                                        <li>Select Account</li>
                                        <li>Select Consumer type as Non Beneficiary</li>
                                        <li>Enter Consumer Number and click on fetch bill</li>
                                        <li>Generate OTP and input to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- First Women Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'fwb-internet' }"
                                    @click="toggleAccordion('fwb-internet')">
                                    <span class="bank-name">First Women Bank (FWB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'fwb-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'fwb-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Habib Bank Limited -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'hbl-internet' }"
                                    @click="toggleAccordion('hbl-internet')">
                                    <span class="bank-name">Habib Bank Limited (HBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'hbl-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'hbl-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Pay</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Select 1Bill Invoice or 1Bill Top Up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Habib Metro Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'hmb-internet' }"
                                    @click="toggleAccordion('hmb-internet')">
                                    <span class="bank-name">Habib Metro Bank (HMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'hmb-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'hmb-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to the Add Biller Section. Under this section select 1BILL invoices and input your Reference Number along with the Mobile Token and register the biller</li>
                                        <li>After registration Select Payment and Transfers</li>
                                        <li>Select the relevant bill</li>
                                        <li>Enter OTP and complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- JS Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'jsb-internet' }"
                                    @click="toggleAccordion('jsb-internet')">
                                    <span class="bank-name">JS Bank (JSB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'jsb-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'jsb-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Bill Payments in Mobile App</li>
                                        <li>Select Bill Registration and select Others</li>
                                        <li>Under others select 1BILL payments and enter your Consumer Number along with your Mobile Token and get registered</li>
                                        <li>Pay the bill</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Meezan Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'meezan-internet' }"
                                    @click="toggleAccordion('meezan-internet')">
                                    <span class="bank-name">Meezan Bank (MBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'meezan-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'meezan-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Bill Payments in Banking Portal</li>
                                        <li>Click Add Bills & Mobile No</li>
                                        <li>Search and Select 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number & Nickname then Click Next</li>
                                        <li>Enter Passcode and Click Next</li>
                                        <li>Bill will get registered, Click Pay Now</li>
                                        <li>Validate Bill details and Click Next to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Mobilink Microfinance Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'mmbl-internet' }"
                                    @click="toggleAccordion('mmbl-internet')">
                                    <span class="bank-name">Mobilink Microfinance Bank (MMBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'mmbl-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'mmbl-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- National Bank of Pakistan -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'nbp-internet' }"
                                    @click="toggleAccordion('nbp-internet')">
                                    <span class="bank-name">National Bank of Pakistan (NBP)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'nbp-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'nbp-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Banking Portal</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- NRSP Microfinance Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'nrsp-internet' }"
                                    @click="toggleAccordion('nrsp-internet')">
                                    <span class="bank-name">NRSP Microfinance Bank (NRSPMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'nrsp-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'nrsp-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payment in Mobile App</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number</li>
                                        <li>Pay!</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Samba Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'samba-internet' }"
                                    @click="toggleAccordion('samba-internet')">
                                    <span class="bank-name">Samba Bank (SAM)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'samba-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'samba-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select My Account in Internet Banking</li>
                                        <li>Select Make a Payment from the I'd Like to Option</li>
                                        <li>Select Tab 1BILL Payment</li>
                                        <li>Select Invoice/Voucher Payment Option</li>
                                        <li>Enter your Consumer Number into the provided field</li>
                                        <li>After verifying details click Proceed</li>
                                        <li>Enter IPIN for completing payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Silk Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'silk-internet' }"
                                    @click="toggleAccordion('silk-internet')">
                                    <span class="bank-name">Silk Bank (SLK)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'silk-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'silk-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Bill Payments in Mobile App</li>
                                        <li>Select Bill Type as 1BILL</li>
                                        <li>Enter Consumer Number</li>
                                        <li>Tap on Next Button to fetch Bill</li>
                                        <li>Generate OTP and input OTP to complete payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Standard Chartered -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'scb-internet' }"
                                    @click="toggleAccordion('scb-internet')">
                                    <span class="bank-name">Standard Chartered (SCB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'scb-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'scb-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select Payments and select 1BILL In Bill Payee Type in Banking Portal</li>
                                        <li>Select Voucher / Invoice Payment in Bill Payee Name</li>
                                        <li>Click Add Bills / Add Beneficiary</li>
                                        <li>Enter your Consumer Number into the Provided Field & Nickname then Click Next</li>
                                        <li>Enter Passcode received on your SMS or on your bank accounts registered email address and Click Next</li>
                                        <li>Bill will get registered, Click Pay Now</li>
                                        <li>Validate Bill details and Click Next to Pay</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Summit Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'summit-internet' }"
                                    @click="toggleAccordion('summit-internet')">
                                    <span class="bank-name">Summit Bank (SUM)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'summit-internet' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'summit-internet'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Select My Account in Banking Portal</li>
                                        <li>Select Bill Payment option from Menu</li>
                                        <li>Select Option Bill Payment Registration</li>
                                        <li>Enter your Consumer Number into the Provided Field</li>
                                        <li>Bill details will appear on screen</li>
                                        <li>Enter FPIN to complete transaction</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Digital Wallets Section -->
                    <div v-if="selectedMethod === 'wallet'" class="method-section">
                        <div class="section-header">
                            <h3>Transfer via Digital Wallets</h3>
                        </div>
                        <div class="banks-grid">
                            <!-- JazzCash -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'jazzcash' }"
                                    @click="toggleAccordion('jazzcash')">
                                    <span class="bank-name">JazzCash</span>
                                    <span class="toggle-icon">{{ openAccordion === 'jazzcash' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'jazzcash'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Click on other payments & services in JazzCash App</li>
                                        <li>Then select corporate payments</li>
                                        <li>Select 1Bill (Invoice and Voucher)</li>
                                        <li>Enter Consumer Number in voucher number field and click on Continue</li>
                                        <li>Click on confirm transaction</li>
                                        <li>Then enter your 4-digit pin code to complete transaction</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- EasyPaisa -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'easypaisa' }"
                                    @click="toggleAccordion('easypaisa')">
                                    <span class="bank-name">EasyPaisa</span>
                                    <span class="toggle-icon">{{ openAccordion === 'easypaisa' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'easypaisa'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Click on More in EasyPaisa App</li>
                                        <li>Then click on Others</li>
                                        <li>Select 1Bill Invoice/Voucher</li>
                                        <li>Enter Consumer Number field and click on next</li>
                                        <li>Your bill will be fetched, click on pay now to make the transaction</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Konnect -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'konnect' }"
                                    @click="toggleAccordion('konnect')">
                                    <span class="bank-name">Konnect</span>
                                    <span class="toggle-icon">{{ openAccordion === 'konnect' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'konnect'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to Make Payments in Konnect app</li>
                                        <li>Go to 1BILL Invoice or Top-up</li>
                                        <li>Select ABHI Top Up</li>
                                        <li>Enter your Consumer Number to Pay</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ATM Section -->
                    <div v-if="selectedMethod === 'atm'" class="method-section">
                        <div class="section-header">
                            <h3>ATM</h3>
                        </div>
                        <div class="banks-grid">
                            <!-- Habib Metro Bank -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'hmb-atm' }"
                                    @click="toggleAccordion('hmb-atm')">
                                    <span class="bank-name">Habib Metro Bank (HMB)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'hmb-atm' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'hmb-atm'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to an ATM and insert your card and PIN</li>
                                        <li>Select Other on the Home screen</li>
                                        <li>Select Bill Payment and select 1Bill</li>
                                        <li>Enter your Consumer Number and click Pay</li>
                                        <li>You will be instantly notified on your payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Habib -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'habib-atm' }"
                                    @click="toggleAccordion('habib-atm')">
                                    <span class="bank-name">Bank Al Habib (BAHL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'habib-atm' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'habib-atm'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to your bank's ATM and insert your card and PIN</li>
                                        <li>Go to Bill Payment</li>
                                        <li>Go to Foree or 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number and click Pay</li>
                                        <li>You will be instantly notified on your payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- Bank Al Falah -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'bafl-atm' }"
                                    @click="toggleAccordion('bafl-atm')">
                                    <span class="bank-name">Bank Al Falah (BAFL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'bafl-atm' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'bafl-atm'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to your bank's ATM and insert your card and PIN</li>
                                        <li>Go to Bill Payment</li>
                                        <li>Go to Foree or 1BILL Invoice or Top-up</li>
                                        <li>Enter your Consumer Number and click Pay</li>
                                        <li>You will be instantly notified on your payment</li>
                                    </ol>
                                </div>
                            </div>

                            <!-- United Bank Limited -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'ubl-atm' }"
                                    @click="toggleAccordion('ubl-atm')">
                                    <span class="bank-name">United Bank Limited (UBL)</span>
                                    <span class="toggle-icon">{{ openAccordion === 'ubl-atm' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'ubl-atm'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Go to your bank's ATM and insert your card and PIN</li>
                                        <li>Select Bill Payments and then Select Others</li>
                                        <li>Select 1BILL and click on New Bill</li>
                                        <li>Enter your Consumer Number and Click Yes to Continue</li>
                                        <li>Confirm details and click Pay</li>
                                        <li>You will be instantly notified on your payment</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Over the Counter Section -->
                    <div v-if="selectedMethod === 'counter'" class="method-section">
                        <div class="section-header">
                            <h3>Over the counter/Pay at TCS</h3>
                        </div>
                        <div class="banks-grid">
                            <!-- TCS -->
                            <div class="bank-item">
                                <div class="bank-header" :class="{ active: openAccordion === 'tcs' }"
                                    @click="toggleAccordion('tcs')">
                                    <span class="bank-name">Over the counter/Pay at TCS</span>
                                    <span class="toggle-icon">{{ openAccordion === 'tcs' ? '−' : '+' }}</span>
                                </div>
                                <div class="bank-content" v-show="openAccordion === 'tcs'">
                                    <h4>Steps to Pay:</h4>
                                    <ol>
                                        <li>Take a hardcopy of your bill/challan to a bank/TCS branch</li>
                                        <li>Present the bill to the teller and tell him that it's a 1Bill Invoice payment</li>
                                        <li>Teller will pay the bill and collect cash from you</li>
                                        <li>Take a paid receipt from teller</li>
                                        <li>You will be instantly notified on the payment</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Selection Message -->
                    <div v-if="!selectedMethod" class="no-selection">
                        <p>Please select a transfer method to view payment instructions</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue'

// State
const selectedMethod = ref('')
const openAccordion = ref(null)

// Methods
const handleMethodChange = () => {
    openAccordion.value = null // Reset accordion when method changes
}

const toggleAccordion = (accordionId) => {
    openAccordion.value = openAccordion.value === accordionId ? null : accordionId
}
</script>

<style scoped>
/* Base Styles */
.payment-page {
    min-height: 100vh;
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

/* Hero Section */
.hero-section {
    background:  #003a74;
    color: #ffffff;
    padding: 3rem 1rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid #e2e8f0;
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 70%, rgba(0, 75, 141, 0.03) 0%, transparent 50%);
    pointer-events: none;
}

.hero-content {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
}

.hero-title {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
    color: #ffffff;
}

.hero-logo {
    height: 50px;
    margin: 0.5rem 0;
}

.hero-subtitle {
    font-size: 1.1rem;
    color: #fdfdfd;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Container */
.container {
    width: 100%;
    padding-right: 1rem;
    padding-left: 1rem;
    margin-right: auto;
    margin-left: auto;
}

/* Payment Card */
.payment-detail {
    padding: 2rem 1rem;
}

.payment-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    border: 1px solid #f1f5f9;
    animation: slideUp 0.5s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Method Selector */
.method-selector {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    background: white;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    color: #334155;
    font-size: 0.95rem;
    font-weight: 600;
}

.method-select {
    width: 100%;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
    background: white;
    color: #0f172a;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1rem;
}

.method-select:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}

.method-select:focus {
    outline: none;
    border-color: #004b8d;
    box-shadow: 0 0 0 4px rgba(0, 75, 141, 0.05);
    background: white;
}

/* Method Section */
.method-section {
    padding: 1.5rem;
    background: white;
}

.section-header {
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.section-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #004b8d;
    margin: 0;
}

/* Banks Grid */
.banks-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Bank Item */
.bank-item {
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.bank-item:hover {
    border-color: #e2e8f0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    background: #fafafa;
}

/* Bank Header */
.bank-header {
    padding: 1rem 1.25rem;
    background: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    user-select: none;
}

.bank-header:hover {
    background: #f8fafc;
}

.bank-header.active {
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}

.bank-name {
    font-weight: 500;
    font-size: 0.95rem;
    color: #334155;
    flex: 1;
    padding-right: 1rem;
}

.toggle-icon {
    font-size: 1.25rem;
    font-weight: 400;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    transition: all 0.3s ease;
}

.bank-header.active .toggle-icon {
    background: #004b8d;
    color: white;
}

/* Bank Content */
.bank-content {
    padding: 1.25rem;
    background: #fafafa;
    border-top: 1px solid #f1f5f9;
}

.bank-content h4 {
    color: #004b8d;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.bank-content ol {
    margin: 0;
    padding-left: 1.25rem;
}

.bank-content li {
    margin-bottom: 0.5rem;
    line-height: 1.5;
    color: #475569;
    font-size: 0.9rem;
}

.bank-content li:last-child {
    margin-bottom: 0;
}

/* No Selection */
.no-selection {
    padding: 2rem 1.5rem;
    text-align: center;
    color: #94a3b8;
    font-size: 0.95rem;
    background: #fafafa;
    margin: 0 1.5rem 1.5rem;
    border-radius: 12px;
    border: 1px dashed #e2e8f0;
}

/* Responsive Breakpoints */

/* Small devices (landscape phones, 576px and up) */
@media (min-width: 576px) {
    .container {
        max-width: 540px;
    }
    
    .hero-title {
        font-size: 2.2rem;
        flex-direction: row;
    }
    
    .hero-logo {
        margin: 0 0.5rem;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
    }
}

/* Medium devices (tablets, 768px and up) */
@media (min-width: 768px) {
    .container {
        max-width: 720px;
    }
    
    .hero-section {
        padding: 4rem 2rem;
    }
    
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-logo {
        height: 60px;
    }
    
    .method-selector,
    .method-section {
        padding: 2rem;
    }
    
    .bank-header {
        padding: 1.25rem 1.5rem;
    }
    
    .bank-name {
        font-size: 1rem;
    }
    
    .bank-content {
        padding: 1.5rem;
    }
    
    .bank-content li {
        font-size: 0.95rem;
    }
    
    .no-selection {
        margin: 0 2rem 2rem;
        padding: 3rem 2rem;
    }
}

/* Large devices (desktops, 992px and up) */
@media (min-width: 992px) {
    .container {
        max-width: 960px;
    }
    
    .payment-detail {
        padding: 3rem 1rem;
    }
    
    .hero-title {
        font-size: 2.8rem;
    }
    
    .hero-logo {
        height: 68px;
    }
}

/* Extra large devices (large desktops, 1200px and up) */
@media (min-width: 1200px) {
    .container {
        max-width: 1140px;
    }
}

/* Landscape mode for mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .hero-section {
        padding: 2rem 1rem;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .hero-logo {
        height: 45px;
    }
}

/* Small phones */
@media (max-width: 380px) {
    .hero-title {
        font-size: 1.5rem;
    }
    
    .hero-logo {
        height: 35px;
    }
    
    .hero-subtitle {
        font-size: 0.9rem;
    }
    
    .method-selector,
    .method-section {
        padding: 1rem;
    }
    
    .bank-header {
        padding: 0.875rem 1rem;
    }
    
    .bank-name {
        font-size: 0.9rem;
    }
    
    .bank-content {
        padding: 1rem;
    }
    
    .bank-content li {
        font-size: 0.85rem;
    }
}

/* Touch device optimizations */
@media (hover: none) {
    .bank-header:hover {
        background: white;
    }
    
    .bank-item:hover {
        border-color: #f1f5f9;
        box-shadow: none;
        background: white;
    }
}

/* Print styles */
@media print {
    .hero-section {
        background: none;
        color: black;
        padding: 1rem;
        border: none;
    }
    
    .hero-logo {
        filter: none;
    }
    
    .payment-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .method-selector,
    .bank-header {
        break-inside: avoid;
    }
    
    .bank-content {
        display: block !important;
        background: white;
    }
}

/* Accessibility */
.method-select:focus-visible,
.bank-header:focus-visible {
    outline: 2px solid #004b8d;
    outline-offset: 2px;
}

/* Reduced motion preference */
@media (prefers-reduced-motion: reduce) {
    .payment-card,
    .bank-header,
    .bank-content,
    .toggle-icon {
        animation: none;
        transition: none;
    }
}

/* Subtle hover effects for desktop */
@media (hover: hover) {
    .bank-item:hover .bank-name {
        color: #004b8d;
    }
    
    .toggle-icon:hover {
        background: #e2e8f0;
    }
}

/* Selection styling */
::selection {
    background: rgba(0, 75, 141, 0.1);
    color: #004b8d;
}
</style>