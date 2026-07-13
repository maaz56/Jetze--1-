import airline from "@/services/store/airline";
import bank from "@/services/store/bank";
import customerMargin from "@/services/store/customerMargin";
import deposit from "@/services/store/deposit";
import fileUploader from "@/services/store/fileUploader";
import groupTicket from "@/services/store/groupTicket";
import holiday from "@/services/store/holiday";
import ledger from "@/services/store/ledger";
import notification from "@/services/store/notification";
import promoImage from "@/services/store/promoImage";
import promotion from "@/services/store/promotion";
import settings from "@/services/store/settings";
import transaction from "@/services/store/transaction";
import traveller from "@/services/store/traveller";
import umrahPackage from "@/services/store/umrahPackage";
import visa from "@/services/store/visa";
import { createStore } from "vuex";
import airport from "../services/store/airport";
import auth from "../services/store/auth";
import city from "../services/store/city";
import country from "../services/store/country";
import flight from "../services/store/flight";
import user from "../services/store/user";
import zoho from "@/services/store/zoho";
import activityLog from "@/services/store/activityLog";
import offlineBooking from "@/services/store/offlineBooking";
import customer from "@/services/store/customer";
import currency from "@/services/store/currency";
import modifyRequest from "@/services/store/modifyRequest";
import cms from "@/services/store/cms";
import blog from "@/services/store/blog";
import payment from "@/services/store/payment";
import segmentMargin from "@/services/store/segmentMargin";
import review from "@/services/store/review";
import newsletter from "@/services/store/newsletter";
import hotDeals from "@/services/store/hotDeals";


export default createStore({
    modules: {
        fileUploader: fileUploader,
        auth: auth,
        country: country,
        city: city,
        airline: airline,
        airport: airport,
        flight: flight,
        user: user,
        transaction: transaction,
        visa: visa,
        holiday: holiday,
        umrahPackage: umrahPackage,
        groupTicket: groupTicket,
        deposit: deposit,
        ledger:ledger,
        bank: bank,
        promoImage: promoImage,
        promotion: promotion,
        traveller:traveller,
        settings: settings,
        customerMargin: customerMargin,
        notification: notification,
        zoho:zoho,
        activityLog: activityLog,
        offlineBooking: offlineBooking,
        customer: customer,
        currency: currency,
        modifyRequest: modifyRequest,
        cms: cms,
        blog: blog,
        payment:payment,
        segmentMargin: segmentMargin,
        review: review,
        newsletter : newsletter,
        hotDeals : hotDeals,
    },
});
