export function normalizeTravellerType(type) {
  const value = String(type ?? "").trim().toLowerCase();

  if (["adt", "adult"].includes(value)) return "adult";
  if (["cnn", "chd", "child"].includes(value)) return "child";
  if (["inf", "infant"].includes(value)) return "infant";

  return value;
}

export function formatDateInputValue(date) {
  const d = date instanceof Date ? new Date(date.getTime()) : new Date(date);
  if (Number.isNaN(d.getTime())) return "";

  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function getDateYearsAgo(years, baseDate = new Date()) {
  const date = new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate());
  date.setFullYear(date.getFullYear() - years);
  return date;
}

export function getDateDaysFrom(date, days) {
  const nextDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  nextDate.setDate(nextDate.getDate() + days);
  return nextDate;
}

export function getTravellerDobRange(type, baseDate = new Date()) {
  const normalizedType = normalizeTravellerType(type);
  const today = new Date(baseDate.getFullYear(), baseDate.getMonth(), baseDate.getDate());

  if (normalizedType === "infant") {
    return {
      min: formatDateInputValue(getDateDaysFrom(getDateYearsAgo(2, today), 1)),
      max: formatDateInputValue(today),
    };
  }

  if (normalizedType === "child") {
    return {
      min: formatDateInputValue(getDateYearsAgo(12, today)),
      max: formatDateInputValue(getDateYearsAgo(2, today)),
    };
  }

  if (normalizedType === "adult") {
    return {
      min: formatDateInputValue(getDateYearsAgo(100, today)),
      max: formatDateInputValue(getDateYearsAgo(12, today)),
    };
  }

  return {
    min: formatDateInputValue(getDateYearsAgo(100, today)),
    max: formatDateInputValue(today),
  };
}

export function isTravellerDobValid(dob, type) {
  if (!dob) return false;

  const parsed = new Date(dob);
  if (Number.isNaN(parsed.getTime())) return false;

  const normalizedType = normalizeTravellerType(type);
  const today = new Date();
  const birthDate = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
  const currentDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  const age = currentDay.getFullYear() - birthDate.getFullYear();

  const hasBirthdayOccurred =
    currentDay.getMonth() > birthDate.getMonth() ||
    (currentDay.getMonth() === birthDate.getMonth() && currentDay.getDate() >= birthDate.getDate());

  const adjustedAge = hasBirthdayOccurred ? age : age - 1;

  switch (normalizedType) {
    case "adult":
      return adjustedAge >= 12;
    case "child":
      return adjustedAge >= 2 && adjustedAge <= 12;
    case "infant":
      return adjustedAge < 2;
    default:
      return true;
  }
}
