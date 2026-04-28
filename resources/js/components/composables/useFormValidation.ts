// validation.ts
import { ref, type Ref } from 'vue'

type ValidationResult = string | null
type Validator = (value: any) => ValidationResult

interface ValidationRule {
  validator: Validator
  message?: string | undefined // Explicitly allow undefined
}

export function useValidation(rules: Ref<ValidationRule[]> | ValidationRule[]) {
  const normalizedRules = Array.isArray(rules) ? ref(rules) : rules

  const validate = (value: unknown): ValidationResult => {
    for (const rule of normalizedRules.value) {
      const result = rule.validator(value)
      if (result !== null) {
        return rule.message ?? result
      }
    }
    return null
  }

  return { validate, rules: normalizedRules }
}

// Validation factory functions with explicit return types
export const required = (message?: string): ValidationRule => ({
  validator: (value) => {
    if (
      value === null ||
      value === undefined ||
      (typeof value === 'string' && value.trim() === '') ||
      (Array.isArray(value) && value.length === 0) ||
      (typeof value === 'object' && Object.keys(value).length === 0)
    ) {
      return message ?? 'This field is required'
    }
    return null
  },
  message
})

export const email = (message?: string, pattern?: RegExp): ValidationRule => ({
  validator: (value) => {
    const regex = pattern || /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return typeof value === 'string' && regex.test(value)
      ? null
      : message ?? 'Invalid email format'
  },
  message
})

export const url = (message?: string, pattern?: RegExp): ValidationRule => ({
  validator: (value) => {
    if(!value) return null;
    const regex = pattern || /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/i
    return typeof value === 'string' && regex.test(value)
      ? null
      : message ?? 'Invalid URL format'
  },
  message
})

export const minLength = (min: number, message?: string): ValidationRule => ({
  validator: (value) => {
    const length = getValueLength(value)
    return length >= min ? null : message ?? `Minimum length is ${min}`
  },
  message
})

export const maxLength = (max: number, message?: string): ValidationRule => ({
  validator: (value) => {
    const length = getValueLength(value)
    return length <= max ? null : message ?? `Maximum length is ${max}`
  },
  message
})

export const custom = (
  validator: (value: any) => boolean | string,
  message?: string
): ValidationRule => ({
  validator: (value) => {
    const result = validator(value)
    if (typeof result === 'string') return result
    return result ? null : message ?? 'Validation failed'
  },
  message
})

// Helper function remains the same
function getValueLength(value: unknown): number {
  if (typeof value === 'string') return value.length
  if (Array.isArray(value)) return value.length
  if (typeof value === 'object' && value !== null) return Object.keys(value).length
  return 0
}