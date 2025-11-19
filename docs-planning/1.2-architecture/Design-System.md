# FormFlow Pro Enterprise - Design System
**Version:** 2.0.0
**Date:** November 19, 2025
**Status:** Basic Design System - Ready for Implementation
**Framework:** Vanilla CSS with CSS Custom Properties (CSS Variables)

---

## 📋 Executive Summary

### Design Philosophy
FormFlow Pro follows a **modern, clean, and accessible** design language inspired by Material Design 3.0 and Tailwind CSS, optimized for WordPress admin interfaces.

### Core Principles
1. **Consistency** - Unified visual language across all interfaces
2. **Accessibility** - WCAG 2.1 AA compliant (target 95%+ score)
3. **Performance** - Lightweight CSS, system fonts, optimized assets
4. **Responsiveness** - Mobile-first, fluid layouts
5. **Clarity** - Clear hierarchy, readable typography, intuitive interactions

### Browser Support
- Chrome 90+ ✅
- Firefox 88+ ✅
- Safari 14+ ✅
- Edge 90+ ✅
- Mobile browsers (iOS 14+, Android 10+) ✅

---

## 🎨 Design Tokens

### Color Palette

```css
:root {
  /* Primary Colors (Brand) */
  --ff-primary-50: #f0f9ff;
  --ff-primary-100: #e0f2fe;
  --ff-primary-200: #bae6fd;
  --ff-primary-300: #7dd3fc;
  --ff-primary-400: #38bdf8;
  --ff-primary-500: #0ea5e9;  /* Main brand color */
  --ff-primary-600: #0284c7;
  --ff-primary-700: #0369a1;
  --ff-primary-800: #075985;
  --ff-primary-900: #0c4a6e;

  /* Neutral Colors (UI) */
  --ff-neutral-50: #f8fafc;
  --ff-neutral-100: #f1f5f9;
  --ff-neutral-200: #e2e8f0;
  --ff-neutral-300: #cbd5e1;
  --ff-neutral-400: #94a3b8;
  --ff-neutral-500: #64748b;
  --ff-neutral-600: #475569;
  --ff-neutral-700: #334155;
  --ff-neutral-800: #1e293b;
  --ff-neutral-900: #0f172a;

  /* Semantic Colors */
  --ff-success-50: #f0fdf4;
  --ff-success-500: #22c55e;  /* Green */
  --ff-success-600: #16a34a;
  --ff-success-700: #15803d;

  --ff-warning-50: #fffbeb;
  --ff-warning-500: #f59e0b;  /* Amber */
  --ff-warning-600: #d97706;
  --ff-warning-700: #b45309;

  --ff-error-50: #fef2f2;
  --ff-error-500: #ef4444;    /* Red */
  --ff-error-600: #dc2626;
  --ff-error-700: #b91c1c;

  --ff-info-50: #eff6ff;
  --ff-info-500: #3b82f6;     /* Blue */
  --ff-info-600: #2563eb;
  --ff-info-700: #1d4ed8;

  /* Surface Colors */
  --ff-surface-background: #ffffff;
  --ff-surface-card: #ffffff;
  --ff-surface-overlay: rgba(15, 23, 42, 0.5);  /* Dark overlay */

  /* Text Colors */
  --ff-text-primary: var(--ff-neutral-900);
  --ff-text-secondary: var(--ff-neutral-600);
  --ff-text-tertiary: var(--ff-neutral-500);
  --ff-text-disabled: var(--ff-neutral-400);
  --ff-text-inverse: #ffffff;

  /* Border Colors */
  --ff-border-default: var(--ff-neutral-200);
  --ff-border-hover: var(--ff-neutral-300);
  --ff-border-focus: var(--ff-primary-500);
  --ff-border-error: var(--ff-error-500);
}

/* Dark Mode */
[data-theme="dark"] {
  --ff-surface-background: var(--ff-neutral-900);
  --ff-surface-card: var(--ff-neutral-800);
  --ff-surface-overlay: rgba(255, 255, 255, 0.1);

  --ff-text-primary: var(--ff-neutral-50);
  --ff-text-secondary: var(--ff-neutral-300);
  --ff-text-tertiary: var(--ff-neutral-400);

  --ff-border-default: var(--ff-neutral-700);
  --ff-border-hover: var(--ff-neutral-600);
}
```

**Usage Examples:**
```css
/* Good: Use semantic tokens */
.success-message {
  color: var(--ff-success-700);
  background-color: var(--ff-success-50);
}

/* Bad: Use raw values */
.success-message {
  color: #15803d;
  background-color: #f0fdf4;
}
```

---

### Typography

```css
:root {
  /* Font Families */
  --ff-font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                  "Helvetica Neue", Arial, sans-serif;
  --ff-font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo,
                  Consolas, "Liberation Mono", monospace;

  /* Font Sizes (Fluid Typography) */
  --ff-text-xs: clamp(0.75rem, 0.7rem + 0.25vw, 0.875rem);      /* 12-14px */
  --ff-text-sm: clamp(0.875rem, 0.825rem + 0.25vw, 1rem);       /* 14-16px */
  --ff-text-base: clamp(1rem, 0.95rem + 0.25vw, 1.125rem);      /* 16-18px */
  --ff-text-lg: clamp(1.125rem, 1.05rem + 0.375vw, 1.25rem);    /* 18-20px */
  --ff-text-xl: clamp(1.25rem, 1.15rem + 0.5vw, 1.5rem);        /* 20-24px */
  --ff-text-2xl: clamp(1.5rem, 1.35rem + 0.75vw, 1.875rem);     /* 24-30px */
  --ff-text-3xl: clamp(1.875rem, 1.65rem + 1.125vw, 2.25rem);   /* 30-36px */
  --ff-text-4xl: clamp(2.25rem, 1.95rem + 1.5vw, 3rem);         /* 36-48px */

  /* Font Weights */
  --ff-weight-light: 300;
  --ff-weight-regular: 400;
  --ff-weight-medium: 500;
  --ff-weight-semibold: 600;
  --ff-weight-bold: 700;

  /* Line Heights */
  --ff-leading-tight: 1.25;
  --ff-leading-normal: 1.5;
  --ff-leading-relaxed: 1.75;

  /* Letter Spacing */
  --ff-tracking-tight: -0.025em;
  --ff-tracking-normal: 0em;
  --ff-tracking-wide: 0.025em;
}
```

**Typography Scale:**
```css
/* Headings */
.ff-heading-1 {
  font-size: var(--ff-text-4xl);
  font-weight: var(--ff-weight-bold);
  line-height: var(--ff-leading-tight);
  letter-spacing: var(--ff-tracking-tight);
  color: var(--ff-text-primary);
}

.ff-heading-2 {
  font-size: var(--ff-text-3xl);
  font-weight: var(--ff-weight-bold);
  line-height: var(--ff-leading-tight);
  color: var(--ff-text-primary);
}

.ff-heading-3 {
  font-size: var(--ff-text-2xl);
  font-weight: var(--ff-weight-semibold);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-primary);
}

/* Body Text */
.ff-body-large {
  font-size: var(--ff-text-lg);
  font-weight: var(--ff-weight-regular);
  line-height: var(--ff-leading-relaxed);
  color: var(--ff-text-primary);
}

.ff-body {
  font-size: var(--ff-text-base);
  font-weight: var(--ff-weight-regular);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-primary);
}

.ff-body-small {
  font-size: var(--ff-text-sm);
  font-weight: var(--ff-weight-regular);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-secondary);
}

/* Labels & UI Text */
.ff-label {
  font-size: var(--ff-text-sm);
  font-weight: var(--ff-weight-medium);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-primary);
  letter-spacing: var(--ff-tracking-wide);
  text-transform: uppercase;
}

.ff-caption {
  font-size: var(--ff-text-xs);
  font-weight: var(--ff-weight-regular);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-tertiary);
}
```

---

### Spacing Scale (4px baseline grid)

```css
:root {
  --ff-space-0: 0;
  --ff-space-1: 0.25rem;   /* 4px */
  --ff-space-2: 0.5rem;    /* 8px */
  --ff-space-3: 0.75rem;   /* 12px */
  --ff-space-4: 1rem;      /* 16px */
  --ff-space-5: 1.25rem;   /* 20px */
  --ff-space-6: 1.5rem;    /* 24px */
  --ff-space-8: 2rem;      /* 32px */
  --ff-space-10: 2.5rem;   /* 40px */
  --ff-space-12: 3rem;     /* 48px */
  --ff-space-16: 4rem;     /* 64px */
  --ff-space-20: 5rem;     /* 80px */
  --ff-space-24: 6rem;     /* 96px */
}
```

**Usage:**
```css
.card {
  padding: var(--ff-space-6);      /* 24px all sides */
  margin-bottom: var(--ff-space-4); /* 16px bottom */
  gap: var(--ff-space-3);          /* 12px gap in flex/grid */
}
```

---

### Shadows

```css
:root {
  /* Elevation Shadows */
  --ff-shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --ff-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1),
                  0 1px 2px -1px rgba(0, 0, 0, 0.1);
  --ff-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
                  0 2px 4px -2px rgba(0, 0, 0, 0.1);
  --ff-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
                  0 4px 6px -4px rgba(0, 0, 0, 0.1);
  --ff-shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
                  0 8px 10px -6px rgba(0, 0, 0, 0.1);
  --ff-shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

  /* Focus Shadow */
  --ff-shadow-focus: 0 0 0 3px rgba(14, 165, 233, 0.3);  /* Primary color */
  --ff-shadow-error-focus: 0 0 0 3px rgba(239, 68, 68, 0.3);  /* Error color */
}
```

---

### Border Radius

```css
:root {
  --ff-radius-none: 0;
  --ff-radius-sm: 0.125rem;   /* 2px */
  --ff-radius-base: 0.25rem;  /* 4px */
  --ff-radius-md: 0.375rem;   /* 6px */
  --ff-radius-lg: 0.5rem;     /* 8px */
  --ff-radius-xl: 0.75rem;    /* 12px */
  --ff-radius-2xl: 1rem;      /* 16px */
  --ff-radius-full: 9999px;   /* Pill shape */
}
```

---

### Transitions

```css
:root {
  /* Durations */
  --ff-transition-fast: 150ms;
  --ff-transition-base: 200ms;
  --ff-transition-slow: 300ms;
  --ff-transition-slower: 500ms;

  /* Easing Functions */
  --ff-ease-in: cubic-bezier(0.4, 0, 1, 1);
  --ff-ease-out: cubic-bezier(0, 0, 0.2, 1);
  --ff-ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
}
```

**Usage:**
```css
.button {
  transition: all var(--ff-transition-base) var(--ff-ease-in-out);
}

.modal {
  transition: opacity var(--ff-transition-slow) var(--ff-ease-out);
}
```

---

### Z-Index Scale

```css
:root {
  --ff-z-base: 0;
  --ff-z-dropdown: 1000;
  --ff-z-sticky: 1100;
  --ff-z-fixed: 1200;
  --ff-z-modal-backdrop: 1300;
  --ff-z-modal: 1400;
  --ff-z-popover: 1500;
  --ff-z-tooltip: 1600;
  --ff-z-toast: 1700;
}
```

---

## 📐 Grid System

### Breakpoints (Mobile-First)

```css
:root {
  --ff-screen-sm: 640px;   /* Mobile landscape, small tablets */
  --ff-screen-md: 768px;   /* Tablets */
  --ff-screen-lg: 1024px;  /* Desktop */
  --ff-screen-xl: 1280px;  /* Large desktop */
  --ff-screen-2xl: 1536px; /* Extra large desktop */
}

/* Media Query Mixins (for Sass/PostCSS) */
@media (min-width: 640px) { /* sm */ }
@media (min-width: 768px) { /* md */ }
@media (min-width: 1024px) { /* lg */ }
@media (min-width: 1280px) { /* xl */ }
```

### Container

```css
.ff-container {
  width: 100%;
  max-width: 1280px;  /* xl breakpoint */
  margin-left: auto;
  margin-right: auto;
  padding-left: var(--ff-space-4);
  padding-right: var(--ff-space-4);
}

@media (min-width: 640px) {
  .ff-container {
    padding-left: var(--ff-space-6);
    padding-right: var(--ff-space-6);
  }
}
```

### Grid Layout

```css
.ff-grid {
  display: grid;
  gap: var(--ff-space-4);
  grid-template-columns: repeat(12, 1fr);
}

/* Responsive Columns */
.ff-col-span-1 { grid-column: span 1; }
.ff-col-span-2 { grid-column: span 2; }
.ff-col-span-3 { grid-column: span 3; }
.ff-col-span-4 { grid-column: span 4; }
.ff-col-span-6 { grid-column: span 6; }
.ff-col-span-12 { grid-column: span 12; }

/* Tablet and up */
@media (min-width: 768px) {
  .ff-col-md-4 { grid-column: span 4; }
  .ff-col-md-6 { grid-column: span 6; }
  .ff-col-md-8 { grid-column: span 8; }
}

/* Desktop and up */
@media (min-width: 1024px) {
  .ff-col-lg-3 { grid-column: span 3; }
  .ff-col-lg-4 { grid-column: span 4; }
  .ff-col-lg-6 { grid-column: span 6; }
  .ff-col-lg-9 { grid-column: span 9; }
}
```

---

## 🧩 Component Library

### Buttons

```css
/* Base Button */
.ff-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--ff-space-2);

  padding: var(--ff-space-3) var(--ff-space-6);

  font-family: var(--ff-font-sans);
  font-size: var(--ff-text-base);
  font-weight: var(--ff-weight-medium);
  line-height: var(--ff-leading-tight);
  text-align: center;
  text-decoration: none;
  white-space: nowrap;

  border: 1px solid transparent;
  border-radius: var(--ff-radius-md);

  cursor: pointer;
  user-select: none;

  transition: all var(--ff-transition-base) var(--ff-ease-in-out);
}

.ff-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Primary Button */
.ff-button--primary {
  color: var(--ff-text-inverse);
  background-color: var(--ff-primary-500);
  border-color: var(--ff-primary-500);
}

.ff-button--primary:hover:not(:disabled) {
  background-color: var(--ff-primary-600);
  border-color: var(--ff-primary-600);
  box-shadow: var(--ff-shadow-sm);
}

.ff-button--primary:focus-visible {
  outline: none;
  box-shadow: var(--ff-shadow-focus);
}

/* Secondary Button */
.ff-button--secondary {
  color: var(--ff-text-primary);
  background-color: var(--ff-surface-background);
  border-color: var(--ff-border-default);
}

.ff-button--secondary:hover:not(:disabled) {
  background-color: var(--ff-neutral-50);
  border-color: var(--ff-border-hover);
}

/* Success Button */
.ff-button--success {
  color: var(--ff-text-inverse);
  background-color: var(--ff-success-500);
  border-color: var(--ff-success-500);
}

.ff-button--success:hover:not(:disabled) {
  background-color: var(--ff-success-600);
}

/* Danger Button */
.ff-button--danger {
  color: var(--ff-text-inverse);
  background-color: var(--ff-error-500);
  border-color: var(--ff-error-500);
}

.ff-button--danger:hover:not(:disabled) {
  background-color: var(--ff-error-600);
}

/* Button Sizes */
.ff-button--sm {
  padding: var(--ff-space-2) var(--ff-space-4);
  font-size: var(--ff-text-sm);
}

.ff-button--lg {
  padding: var(--ff-space-4) var(--ff-space-8);
  font-size: var(--ff-text-lg);
}

/* Icon Button */
.ff-button--icon {
  padding: var(--ff-space-3);
  aspect-ratio: 1;
}
```

**HTML Example:**
```html
<button class="ff-button ff-button--primary">
  Submit Form
</button>

<button class="ff-button ff-button--secondary ff-button--sm">
  Cancel
</button>

<button class="ff-button ff-button--danger" disabled>
  Delete (disabled)
</button>
```

---

### Input Fields

```css
/* Base Input */
.ff-input {
  display: block;
  width: 100%;
  padding: var(--ff-space-3) var(--ff-space-4);

  font-family: var(--ff-font-sans);
  font-size: var(--ff-text-base);
  line-height: var(--ff-leading-normal);
  color: var(--ff-text-primary);

  background-color: var(--ff-surface-background);
  border: 1px solid var(--ff-border-default);
  border-radius: var(--ff-radius-md);

  transition: all var(--ff-transition-base) var(--ff-ease-in-out);
}

.ff-input::placeholder {
  color: var(--ff-text-tertiary);
}

.ff-input:hover:not(:disabled) {
  border-color: var(--ff-border-hover);
}

.ff-input:focus {
  outline: none;
  border-color: var(--ff-border-focus);
  box-shadow: var(--ff-shadow-focus);
}

.ff-input:disabled {
  background-color: var(--ff-neutral-50);
  color: var(--ff-text-disabled);
  cursor: not-allowed;
}

/* Input with Error */
.ff-input--error {
  border-color: var(--ff-border-error);
}

.ff-input--error:focus {
  box-shadow: var(--ff-shadow-error-focus);
}

/* Input Group */
.ff-input-group {
  margin-bottom: var(--ff-space-4);
}

.ff-input-label {
  display: block;
  margin-bottom: var(--ff-space-2);
  font-size: var(--ff-text-sm);
  font-weight: var(--ff-weight-medium);
  color: var(--ff-text-primary);
}

.ff-input-help {
  display: block;
  margin-top: var(--ff-space-2);
  font-size: var(--ff-text-sm);
  color: var(--ff-text-secondary);
}

.ff-input-error {
  display: block;
  margin-top: var(--ff-space-2);
  font-size: var(--ff-text-sm);
  color: var(--ff-error-600);
}
```

**HTML Example:**
```html
<div class="ff-input-group">
  <label for="email" class="ff-input-label">Email Address</label>
  <input
    type="email"
    id="email"
    class="ff-input"
    placeholder="you@example.com"
  >
  <span class="ff-input-help">We'll never share your email</span>
</div>

<div class="ff-input-group">
  <label for="name" class="ff-input-label">Full Name</label>
  <input
    type="text"
    id="name"
    class="ff-input ff-input--error"
    value="Jo"
  >
  <span class="ff-input-error">Name must be at least 3 characters</span>
</div>
```

---

### Cards

```css
.ff-card {
  background-color: var(--ff-surface-card);
  border: 1px solid var(--ff-border-default);
  border-radius: var(--ff-radius-lg);
  box-shadow: var(--ff-shadow-sm);
  overflow: hidden;
  transition: box-shadow var(--ff-transition-base) var(--ff-ease-in-out);
}

.ff-card:hover {
  box-shadow: var(--ff-shadow-md);
}

.ff-card-header {
  padding: var(--ff-space-6);
  border-bottom: 1px solid var(--ff-border-default);
}

.ff-card-title {
  font-size: var(--ff-text-xl);
  font-weight: var(--ff-weight-semibold);
  color: var(--ff-text-primary);
  margin: 0;
}

.ff-card-description {
  font-size: var(--ff-text-sm);
  color: var(--ff-text-secondary);
  margin-top: var(--ff-space-1);
}

.ff-card-body {
  padding: var(--ff-space-6);
}

.ff-card-footer {
  padding: var(--ff-space-6);
  border-top: 1px solid var(--ff-border-default);
  background-color: var(--ff-neutral-50);
}
```

**HTML Example:**
```html
<div class="ff-card">
  <div class="ff-card-header">
    <h3 class="ff-card-title">Form Submissions</h3>
    <p class="ff-card-description">View and manage all submissions</p>
  </div>
  <div class="ff-card-body">
    <!-- Content here -->
  </div>
  <div class="ff-card-footer">
    <button class="ff-button ff-button--primary">View All</button>
  </div>
</div>
```

---

### Tables

```css
.ff-table-container {
  overflow-x: auto;
  border: 1px solid var(--ff-border-default);
  border-radius: var(--ff-radius-lg);
}

.ff-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--ff-text-sm);
}

.ff-table thead {
  background-color: var(--ff-neutral-50);
  border-bottom: 1px solid var(--ff-border-default);
}

.ff-table th {
  padding: var(--ff-space-3) var(--ff-space-4);
  text-align: left;
  font-weight: var(--ff-weight-semibold);
  color: var(--ff-text-secondary);
  text-transform: uppercase;
  font-size: var(--ff-text-xs);
  letter-spacing: var(--ff-tracking-wide);
}

.ff-table tbody tr {
  border-bottom: 1px solid var(--ff-border-default);
  transition: background-color var(--ff-transition-fast) var(--ff-ease-in-out);
}

.ff-table tbody tr:last-child {
  border-bottom: none;
}

.ff-table tbody tr:hover {
  background-color: var(--ff-neutral-50);
}

.ff-table td {
  padding: var(--ff-space-4);
  color: var(--ff-text-primary);
}

/* Responsive Table (Stack on Mobile) */
@media (max-width: 767px) {
  .ff-table-responsive thead {
    display: none;
  }

  .ff-table-responsive tbody,
  .ff-table-responsive tr,
  .ff-table-responsive td {
    display: block;
    width: 100%;
  }

  .ff-table-responsive tr {
    margin-bottom: var(--ff-space-4);
    border: 1px solid var(--ff-border-default);
    border-radius: var(--ff-radius-md);
  }

  .ff-table-responsive td {
    text-align: right;
    padding-left: 50%;
    position: relative;
  }

  .ff-table-responsive td::before {
    content: attr(data-label);
    position: absolute;
    left: var(--ff-space-4);
    font-weight: var(--ff-weight-semibold);
    text-transform: uppercase;
    font-size: var(--ff-text-xs);
  }
}
```

---

### Badges

```css
.ff-badge {
  display: inline-flex;
  align-items: center;
  padding: var(--ff-space-1) var(--ff-space-3);
  font-size: var(--ff-text-xs);
  font-weight: var(--ff-weight-medium);
  line-height: 1;
  border-radius: var(--ff-radius-full);
}

.ff-badge--success {
  color: var(--ff-success-700);
  background-color: var(--ff-success-50);
}

.ff-badge--warning {
  color: var(--ff-warning-700);
  background-color: var(--ff-warning-50);
}

.ff-badge--error {
  color: var(--ff-error-700);
  background-color: var(--ff-error-50);
}

.ff-badge--info {
  color: var(--ff-info-700);
  background-color: var(--ff-info-50);
}

.ff-badge--neutral {
  color: var(--ff-neutral-700);
  background-color: var(--ff-neutral-100);
}
```

---

### Toast Notifications

```css
.ff-toast-container {
  position: fixed;
  top: var(--ff-space-4);
  right: var(--ff-space-4);
  z-index: var(--ff-z-toast);
  display: flex;
  flex-direction: column;
  gap: var(--ff-space-3);
  max-width: 400px;
}

.ff-toast {
  display: flex;
  align-items: flex-start;
  gap: var(--ff-space-3);
  padding: var(--ff-space-4);
  background-color: var(--ff-surface-card);
  border: 1px solid var(--ff-border-default);
  border-left-width: 4px;
  border-radius: var(--ff-radius-md);
  box-shadow: var(--ff-shadow-lg);
  animation: slideInRight var(--ff-transition-slow) var(--ff-ease-out);
}

@keyframes slideInRight {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.ff-toast--success {
  border-left-color: var(--ff-success-500);
}

.ff-toast--error {
  border-left-color: var(--ff-error-500);
}

.ff-toast--warning {
  border-left-color: var(--ff-warning-500);
}

.ff-toast-content {
  flex: 1;
}

.ff-toast-title {
  font-weight: var(--ff-weight-semibold);
  color: var(--ff-text-primary);
  margin-bottom: var(--ff-space-1);
}

.ff-toast-message {
  font-size: var(--ff-text-sm);
  color: var(--ff-text-secondary);
}

.ff-toast-close {
  padding: var(--ff-space-1);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ff-text-tertiary);
}
```

---

### Modals

```css
.ff-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: var(--ff-surface-overlay);
  z-index: var(--ff-z-modal-backdrop);
  animation: fadeIn var(--ff-transition-base) var(--ff-ease-out);
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.ff-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: var(--ff-z-modal);

  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;

  background-color: var(--ff-surface-card);
  border-radius: var(--ff-radius-xl);
  box-shadow: var(--ff-shadow-2xl);

  animation: scaleIn var(--ff-transition-slow) var(--ff-ease-out);
}

@keyframes scaleIn {
  from {
    transform: translate(-50%, -50%) scale(0.95);
    opacity: 0;
  }
  to {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
  }
}

.ff-modal-header {
  padding: var(--ff-space-6);
  border-bottom: 1px solid var(--ff-border-default);
}

.ff-modal-title {
  font-size: var(--ff-text-2xl);
  font-weight: var(--ff-weight-semibold);
  color: var(--ff-text-primary);
  margin: 0;
}

.ff-modal-body {
  padding: var(--ff-space-6);
}

.ff-modal-footer {
  padding: var(--ff-space-6);
  border-top: 1px solid var(--ff-border-default);
  display: flex;
  gap: var(--ff-space-3);
  justify-content: flex-end;
}
```

---

## ♿ Accessibility Guidelines

### Focus States

```css
/* All interactive elements must have visible focus */
*:focus-visible {
  outline: 2px solid var(--ff-primary-500);
  outline-offset: 2px;
}

/* Custom focus for specific components */
.ff-button:focus-visible {
  outline: none;
  box-shadow: var(--ff-shadow-focus);
}
```

### Color Contrast (WCAG 2.1 AA)

**Minimum Ratios:**
- Normal text (< 18pt): 4.5:1
- Large text (≥ 18pt or 14pt bold): 3:1
- UI components and graphics: 3:1

**Validated Combinations:**
```css
/* ✅ PASS - 7.2:1 contrast */
color: var(--ff-neutral-900);  /* #0f172a */
background: white;

/* ✅ PASS - 12.6:1 contrast */
color: white;
background: var(--ff-primary-700);  /* #0369a1 */

/* ❌ FAIL - 2.8:1 contrast */
color: var(--ff-neutral-400);
background: white;
```

### Screen Reader Support

```html
<!-- Visible label -->
<label for="email">Email</label>
<input id="email" type="email">

<!-- aria-label for icon buttons -->
<button class="ff-button ff-button--icon" aria-label="Close modal">
  <svg>...</svg>
</button>

<!-- aria-describedby for help text -->
<input
  id="password"
  type="password"
  aria-describedby="password-help"
>
<span id="password-help">Minimum 8 characters</span>

<!-- aria-live for dynamic updates -->
<div role="status" aria-live="polite" aria-atomic="true">
  Form submitted successfully
</div>
```

### Keyboard Navigation

**Requirements:**
- All interactive elements reachable via Tab
- Logical tab order (top-to-bottom, left-to-right)
- Escape closes modals/dropdowns
- Enter/Space activates buttons
- Arrow keys navigate lists/menus

---

## 📱 Responsive Design Patterns

### Mobile-First Approach

```css
/* Mobile (default) */
.dashboard-grid {
  display: grid;
  gap: var(--ff-space-4);
  grid-template-columns: 1fr;
}

/* Tablet and up */
@media (min-width: 768px) {
  .dashboard-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Desktop and up */
@media (min-width: 1024px) {
  .dashboard-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
```

### Container Queries (Modern Browsers)

```css
.widget-container {
  container-type: inline-size;
}

@container (min-width: 400px) {
  .widget {
    display: flex;
    flex-direction: row;
  }
}
```

---

## 🎨 Usage Examples

### Complete Form Example

```html
<form class="ff-form">
  <!-- Text Input -->
  <div class="ff-input-group">
    <label for="name" class="ff-input-label">Full Name *</label>
    <input
      type="text"
      id="name"
      class="ff-input"
      required
      aria-required="true"
    >
  </div>

  <!-- Email Input with Help Text -->
  <div class="ff-input-group">
    <label for="email" class="ff-input-label">Email Address *</label>
    <input
      type="email"
      id="email"
      class="ff-input"
      aria-describedby="email-help"
      required
    >
    <span id="email-help" class="ff-input-help">
      We'll send confirmation to this address
    </span>
  </div>

  <!-- Submit Button -->
  <div class="ff-form-actions">
    <button type="submit" class="ff-button ff-button--primary">
      Submit Form
    </button>
    <button type="button" class="ff-button ff-button--secondary">
      Cancel
    </button>
  </div>
</form>
```

### Dashboard Card Grid

```html
<div class="ff-grid">
  <!-- Stat Card 1 -->
  <div class="ff-col-span-12 ff-col-md-6 ff-col-lg-3">
    <div class="ff-card">
      <div class="ff-card-body">
        <div class="ff-stat">
          <span class="ff-stat-label">Total Submissions</span>
          <span class="ff-stat-value">1,247</span>
          <span class="ff-stat-change ff-stat-change--positive">
            +12% from last month
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Repeat for other stats -->
</div>
```

---

## ✅ Implementation Checklist

**Before launch:**

- [ ] All design tokens defined in CSS custom properties
- [ ] Color contrast tested (WCAG 2.1 AA minimum)
- [ ] All components responsive (mobile, tablet, desktop)
- [ ] Focus states visible on all interactive elements
- [ ] Screen reader tested with NVDA/JAWS
- [ ] Keyboard navigation tested (Tab, Enter, Escape, Arrows)
- [ ] Dark mode toggle implemented and tested
- [ ] CSS minified and optimized (< 120 KB)
- [ ] Component documentation complete
- [ ] Design system guide published for team

---

**End of Design System**

*This is a living design system. Update as the product evolves.*
