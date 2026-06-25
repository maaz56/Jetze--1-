import { clsx } from "clsx";
import moment from "moment";
import { twMerge } from "tailwind-merge";
import PakAirports from "../locales/PakAirports.json";
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

export function formatAmount(amount) {
    const numericAmount = Number.parseFloat(amount);
    const safeAmount = Number.isFinite(numericAmount) ? numericAmount : 0;

    const formatter = new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "PKR",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    return formatter.format(safeAmount);
}
// export function formatAmount(amount) {
//   if (isNaN(amount)) return "₨0.00";

//   const rounded = Math.round(amount * 100) / 100; // round to 2 decimals

//   const formatter = new Intl.NumberFormat("en-US", {
//     style: "currency",
//     currency: "PKR",
//     minimumFractionDigits: 2,
//     maximumFractionDigits: 2,
//   });

//   return formatter.format(rounded);
// }

export const formatDate = (date) =>
    new Date(date).toLocaleDateString("en-US", {
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
        timeZone: "Asia/Karachi", // ✅ FIX
    });



export function formatTime(time) {
    return moment(time).format();
}

export const calculateFinalPrice = (
    basePrice,
    marginAmount,
    marginType,
    amountType,
) => {
    let finalPrice = parseFloat(basePrice) || 0;
    let margin = parseFloat(marginAmount) || 0;

    if (amountType === "percent") {
        margin = (finalPrice * margin) / 100;
    }

    if (marginType === "markup") {
        finalPrice += margin;
    } else if (marginType === "discount") {
        finalPrice -= margin;
    }

    return finalPrice;
};

export const calculateAirlineMargin = (
    basePrice,
    marginAmount,
    marginType,
    amountType,
) => {
    let price = parseFloat(basePrice) || 0;
    let margin = parseFloat(marginAmount) || 0;

    if (amountType === "percent") {
        margin = (price * margin) / 100;
    }

    // Only return the margin amount, do not add or subtract from base price
    return margin;
};

export const calculateTypeMargin = (user, margins) => {
    const previousSearch =
        JSON.parse(localStorage.getItem("previous_search")) || {};
    const origin = previousSearch.origin;
    const destination = previousSearch.destination;


    // Extract Pakistan airports using country = "PK"
    const pakistanAirports = PakAirports
        .filter((airport) => airport.country === "PK")
        .map((airport) => airport.iata);

    // Determine if domestic
    const isDomestic =
        pakistanAirports.includes(origin) &&
        pakistanAirports.includes(destination);
        localStorage.setItem("isDomestic", JSON.stringify(isDomestic));
    // Select margin
    let appliedMargin = 0;

    if (isDomestic) {
        appliedMargin = Number(margins.domestic || 0);
    } else {
        appliedMargin = Number(margins.international || 0   );
    }

    // Apply mode logic
    if (user && user.mode === "B2B") {
        appliedMargin =0; // B2B gets half margin
    }
    // If no user, treat as full margin (B2C)

  
    return appliedMargin;
};
export const getFlightType = () => {
  const previousSearch =
    JSON.parse(localStorage.getItem("previous_search")) || {};

  const origin = previousSearch.origin;
  const destination = previousSearch.destination;

  // Extract Pakistan airports using country = "PK"
  const pakistanAirports = PakAirports
    .filter((airport) => airport.country === "PK")
    .map((airport) => airport.iata);

  // Determine flight type
  const isDomestic =
    pakistanAirports.includes(origin) &&
    pakistanAirports.includes(destination);

  return isDomestic ? "domestic" : "international";
};




export function getFormattedDates(departureTime, departureDate, arrivalTime) {
    // Format departure time and date properly
    let departureDateObj = moment(departureDate, "YYYY-MM-DD");
    let formattedDepartureDate = departureDateObj.format("DD MMM YYYY"); // Short month name
    let formattedDepartureTime = moment(departureTime, "hh:mm").format("HH:mm");

    // Format arrival time and date properly
    let formattedArrivalTime = moment(arrivalTime, "hh:mm").format("HH:mm");
    let arrivalDateObj = departureDateObj.clone();

    // If arrival time is earlier than departure, add a day
    if (
        moment(
            `${formattedArrivalTime} ${arrivalDateObj.format("YYYY-MM-DD")}`,
            "HH:mm YYYY-MM-DD",
        ).isBefore(
            moment(
                `${formattedDepartureTime} ${departureDateObj.format("YYYY-MM-DD")}`,
                "HH:mm YYYY-MM-DD",
            ),
        )
    ) {
        arrivalDateObj.add(1, "days");
    }
    let formattedArrivalDate = arrivalDateObj.format("DD MMM YYYY");

    return {
        departure: {
            time: formattedDepartureTime,
            date: formattedDepartureDate,
        },
        arrival: {
            time: formattedArrivalTime,
            date: formattedArrivalDate,
        },
    };
}

export const formatDuration = (minutes) => moment.utc(moment.duration(minutes, 'minutes').asMilliseconds()).format('HH[h] mm[m]');

export function calculateLayoverDetails(currentStop, nextStop) {
    ////console.log(currentStop.arrival.time, nextStop.departure.time);

    const arrivalTime = moment(currentStop, "hh:mm:ssA");
    const departureTime = moment(nextStop, "hh:mm:ssA");
    if (departureTime.isBefore(arrivalTime)) {
        // Add 24 hours to departure time
        departureTime.add(1, "day");
    }
    const duration = moment.duration(departureTime.diff(arrivalTime));

    const hours = duration.hours();
    const minutes = duration.minutes();

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}

export function calculateLayover(currentStop, nextStop) {
    ////console.log(currentStop.arrival.time, nextStop.departure.time);

    const arrivalTime = moment(currentStop.arrival.time, "hh:mm:ssA");
    const departureTime = moment(nextStop.departure.time, "hh:mm:ssA");
    if (departureTime.isBefore(arrivalTime)) {
        // Add 24 hours to departure time
        departureTime.add(1, "day");
    }
    const duration = moment.duration(departureTime.diff(arrivalTime));

    const hours = duration.hours();
    const minutes = duration.minutes();

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
}

export function estimateTakeoffLanding(offer) {
    const departingTime = moment.utc(offer.slices[0].segments[0].departing_at);
    const arrivingTime = moment.utc(offer.slices[0].segments[0].arriving_at);
    const segments = offer.slices[0].segments;

    const flightDurationMs = arrivingTime.diff(departingTime);

    const flightDuration = moment.duration(flightDurationMs);

    const takeoffTimeFormatted = departingTime.format("HH:mm");
    const landingTimeFormatted = arrivingTime.format("HH:mm");

    const flightDurationHours = Math.floor(flightDuration.asHours());
    const flightDurationMinutes = flightDuration.minutes();
    const flightDurationFormatted = `${flightDurationHours}h ${flightDurationMinutes}m`;

    return {
        estimatedTakeoffTime: takeoffTimeFormatted,
        estimatedLandingTime: landingTimeFormatted,
        flightDuration: flightDurationFormatted,
    };
}

export function formatDateTime(rawDateTime) {
    const date = new Date(rawDateTime);
    return date.toLocaleString("en-GB", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
}

export const getDuration = (departure, arrival) => {
    if (!departure || !arrival) return "N/A"; // Handle undefined cases
    const depTime = new Date(departure).getTime();
    const arrTime = new Date(arrival).getTime();
    const diffMs = arrTime - depTime; // Difference in milliseconds

    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    return `${hours}h ${minutes}m`;
};

export const getLayoverTime = (arrivalTime, nextDepartureTime) => {
    if (!arrivalTime || !nextDepartureTime) return "Invalid Time";

    const arrival = new Date(arrivalTime);
    const departure = new Date(nextDepartureTime);

    if (departure <= arrival) return "Invalid Layover Time";

    const diffMs = departure - arrival; // Difference in milliseconds
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    return `${hours}h ${minutes}m`;
};

export const calculateLayoverTime = (
    arrivalDateTime,
    nextDepartureDateTime,
) => {
    const arrival = moment(arrivalDateTime);
    const nextDeparture = moment(nextDepartureDateTime);
    const layover = moment.duration(nextDeparture.diff(arrival));

    const hours = Math.floor(layover.asHours());
    const minutes = layover.minutes();

    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    } else {
        return `${minutes}m`;
    }
};

export function adjustDateTime(date, time, adjustment) {
    if (!date || !time) return { formattedDate: null, formattedTime: null };

    try {
        const dateTime = new Date(`${date}T${time}`);
        if (adjustment) {
            dateTime.setDate(dateTime.getDate() + adjustment);
        }

        // Format date as DD-MM-YYYY
        const day = String(dateTime.getDate()).padStart(2, "0");
        const month = String(dateTime.getMonth() + 1).padStart(2, "0");
        const year = dateTime.getFullYear();
        const formattedDate = `${day}-${month}-${year}`;

        // Format time as HH:mm (24-hour format)
        const hours = String(dateTime.getHours()).padStart(2, "0");
        const minutes = String(dateTime.getMinutes()).padStart(2, "0");
        const formattedTime = `${hours}:${minutes}`;

        return { formattedDate, formattedTime };
    } catch (e) {
        return { formattedDate: null, formattedTime: null };
    }
}

export const getAdjustedDateTime = (
    date,
    timeWithTimezone,
    dateAdjustment = 0,
) => {
    // Combine the date and time
    const combined = `${date}T${timeWithTimezone}`;
    let datetime = new Date(combined);

    // Apply date adjustment (adds full day if adjustment is 1)
    if (dateAdjustment === 1) {
        datetime.setUTCDate(datetime.getUTCDate() + 1);
    }

    // Format date and time separately
    const formattedDate = datetime.toLocaleDateString("en-GB", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    });

    const formattedTime = datetime.toLocaleTimeString("en-GB", {
        hour: "2-digit",
        minute: "2-digit",
    });

    return {
        date: formattedDate,
        time: formattedTime,
    };
};

export const calculateCustomerPrice = (
    price,
    discountPercentageOrData,
    marginPercentage,
    otherCharges = 0,
) => {
    const total = parseFloat(price) || 0;

    let discountPercentage = 0;
    let marginPercentageValue = 0;
    let otherChargesValue = 0;

    if (
        discountPercentageOrData &&
        typeof discountPercentageOrData === "object"
    ) {
        discountPercentage = parseFloat(discountPercentageOrData.discount || 0);
        marginPercentageValue = parseFloat(
            discountPercentageOrData.margin_amount || 0,
        );
        otherChargesValue = parseFloat(
            discountPercentageOrData.other_charges || 0,
        );
    } else {
        discountPercentage = parseFloat(discountPercentageOrData || 0);
        marginPercentageValue = parseFloat(marginPercentage || 0);
        otherChargesValue = parseFloat(otherCharges || 0);
    }

    const discount = (total * discountPercentage) / 100;
    const margin = (total * marginPercentageValue) / 100;
    const customerPrice = total - discount + margin + otherChargesValue;
    return customerPrice;
};

export const calculateCustomerMarginAmount = (basePrice, customerMargin) => {
    const total = parseFloat(basePrice) || 0;
    const discountPercentage = parseFloat(customerMargin?.discount || 0);
    const marginPercentage = parseFloat(customerMargin?.margin_amount || 0);

    const discount = (total * discountPercentage) / 100;
    const margin = (total * marginPercentage) / 100;

    return margin - discount;
};

export const fetchRate = (amount) => {
    //console.log('Fetching exchange rate for PKR to AED...', amount);

    const exchangeRate = { value: null };

    return fetch(
        "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/pkr.json",
    )
        .then((res) => res.json())
        .then((data) => {
            exchangeRate.value = data.pkr.aed;
            const result = (amount * exchangeRate.value).toFixed(0);
            //console.log('Exchange Rate PKR to AED:', result);
            return result;
        })
        .catch((err) => {
            console.error("Currency API error:", err);
            exchangeRate.value = null;
            return null;
        });
};
