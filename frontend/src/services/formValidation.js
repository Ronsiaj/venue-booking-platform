/**
 * Standard Reusable Form Validation Helper for Customer-Side Modules
 * Enforces mandatory field checks and provides consistent "Please fill this field." messaging.
 * 
 * @param {Object} formData - Object containing form field values
 * @param {Array<string>} requiredFields - List of mandatory field keys (e.g. ['customer_name', 'customer_phone'])
 * @returns {{ isValid: boolean, errors: Record<string, string> }}
 */
export const validateRequiredFields = (formData = {}, requiredFields = []) => {
  const errors = {};

  requiredFields.forEach((field) => {
    const value = formData?.[field];
    if (
      value === undefined ||
      value === null ||
      (typeof value === 'string' && value.trim() === '') ||
      (Array.isArray(value) && value.length === 0)
    ) {
      errors[field] = 'Please fill this field.';
    }
  });

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
  };
};

export default validateRequiredFields;
