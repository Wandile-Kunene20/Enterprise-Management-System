// Form validation functions

class FormValidator {
    constructor(formId, rules) {
        this.form = document.getElementById(formId);
        this.rules = rules;
        this.errors = {};
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        // Add submit event listener
        this.form.addEventListener('submit', (e) => this.validateForm(e));
        
        // Add input event listeners for real-time validation
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('blur', () => this.validateField(fieldName));
                field.addEventListener('input', () => this.clearFieldError(fieldName));
            }
        });
    }
    
    validateForm(e) {
        e.preventDefault();
        
        let isValid = true;
        this.errors = {};
        
        // Validate all fields
        Object.keys(this.rules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            this.form.submit();
        } else {
            this.displayErrors();
        }
        
        return isValid;
    }
    
    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const value = field ? field.value.trim() : '';
        const rules = this.rules[fieldName];
        
        this.clearFieldError(fieldName);
        
        for (const rule of rules) {
            const result = this.checkRule(rule, value, field);
            
            if (!result.valid) {
                if (!this.errors[fieldName]) {
                    this.errors[fieldName] = [];
                }
                this.errors[fieldName].push(result.message);
                this.displayFieldError(fieldName, result.message);
                return false;
            }
        }
        
        return true;
    }
    
    checkRule(rule, value, field) {
        switch (rule.type) {
            case 'required':
                return {
                    valid: value.length > 0,
                    message: 'This field is required'
                };
                
            case 'minLength':
                return {
                    valid: value.length >= rule.value,
                    message: `Minimum ${rule.value} characters required`
                };
                
            case 'maxLength':
                return {
                    valid: value.length <= rule.value,
                    message: `Maximum ${rule.value} characters allowed`
                };
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return {
                    valid: emailRegex.test(value),
                    message: 'Please enter a valid email address'
                };
                
            case 'pattern':
                return {
                    valid: rule.value.test(value),
                    message: rule.message || 'Invalid format'
                };
                
            case 'match':
                const matchField = this.form.querySelector(`[name="${rule.field}"]`);
                return {
                    valid: value === (matchField ? matchField.value.trim() : ''),
                    message: rule.message || 'Fields do not match'
                };
                
            case 'custom':
                return rule.validator(value, field);
                
            default:
                return { valid: true, message: '' };
        }
    }
    
    displayFieldError(fieldName, message) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = `
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        `;
        
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <span>${message}</span>
        `;
        
        field.parentNode.appendChild(errorDiv);
        field.classList.add('is-invalid');
    }
    
    clearFieldError(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        if (!field) return;
        
        const errorDiv = field.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.classList.remove('is-invalid');
    }
    
    displayErrors() {
        // Focus on first error field
        const firstErrorField = Object.keys(this.errors)[0];
        if (firstErrorField) {
            const field = this.form.querySelector(`[name="${firstErrorField}"]`);
            if (field) {
                field.focus();
            }
        }
        
        // Show toast notification
        const errorCount = Object.keys(this.errors).length;
        if (errorCount > 0) {
            Toast.show(`Please fix ${errorCount} error${errorCount > 1 ? 's' : ''} in the form`, 'error');
        }
    }
}

// Common validation rules
const ValidationRules = {
    username: [
        { type: 'required' },
        { type: 'minLength', value: 3 },
        { type: 'maxLength', value: 50 },
        { 
            type: 'pattern', 
            value: /^[a-zA-Z0-9_]+$/,
            message: 'Only letters, numbers, and underscores allowed'
        }
    ],
    
    email: [
        { type: 'required' },
        { type: 'email' }
    ],
    
    password: [
        { type: 'required' },
        { type: 'minLength', value: 8 },
        {
            type: 'pattern',
            value: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/,
            message: 'Must contain uppercase, lowercase, and number'
        }
    ],
    
    confirmPassword: [
        { type: 'required' },
        { 
            type: 'match', 
            field: 'password',
            message: 'Passwords do not match'
        }
    ],
    
    name: [
        { type: 'required' },
        { type: 'minLength', value: 2 },
        { type: 'maxLength', value: 100 }
    ],
    
    phone: [
        {
            type: 'pattern',
            value: /^[\+]?[1-9][\d]{0,15}$/,
            message: 'Please enter a valid phone number'
        }
    ]
};

// Initialize validators on page load
document.addEventListener('DOMContentLoaded', () => {
    // Auto-initialize forms with data-validate attribute
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        const formId = form.id || `form-${Math.random().toString(36).substr(2, 9)}`;
        form.id = formId;
        
        // Collect rules from data attributes
        const rules = {};
        const inputs = form.querySelectorAll('[data-validate-rules]');
        
        inputs.forEach(input => {
            const fieldName = input.name;
            if (fieldName) {
                const ruleString = input.getAttribute('data-validate-rules');
                try {
                    rules[fieldName] = JSON.parse(ruleString);
                } catch (e) {
                    console.error('Invalid validation rules for', fieldName, e);
                }
            }
        });
        
        if (Object.keys(rules).length > 0) {
            new FormValidator(formId, rules);
        }
    });
});

// Export to window object
window.FormValidator = FormValidator;
window.ValidationRules = ValidationRules;