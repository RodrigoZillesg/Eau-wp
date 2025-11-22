# 🎨 Design System Guide - EAU Members Portal
## Complete Visual Pattern Guide for AI Implementation

**📋 Use este documento para replicar o visual do EAU Members Portal em outros projetos**

---

## 🎯 DESIGN PHILOSOPHY

**Princípios Fundamentais:**
- ✅ **Clean & Professional** - Design limpo e corporativo
- ✅ **Consistent** - Componentes padronizados e reutilizáveis
- ✅ **Accessible** - WCAG 2.1 AA compliant
- ✅ **Responsive** - Mobile-first approach
- ✅ **Modern** - Tailwind CSS + shadcn/ui inspired

---

## 🎨 COLOR PALETTE

### Primary Brand Color
```css
/* Azul EAU (English Australia) */
--primary: #005EB8;          /* Base - Use for CTAs, links, highlights */
--primary-hover: #004a91;    /* Hover states */
--primary-light: #e6f1ff;    /* Backgrounds */
--primary-dark: #003d7a;     /* Dark mode/accents */
```

### Semantic Colors
```css
/* Success */
--success: #10b981;          /* Green-500 */
--success-bg: #ecfdf5;       /* Green-50 */
--success-text: #059669;     /* Green-600 */

/* Error */
--error: #ef4444;            /* Red-500 */
--error-bg: #fef2f2;         /* Red-50 */
--error-text: #dc2626;       /* Red-600 */

/* Warning */
--warning: #f59e0b;          /* Yellow-500 */
--warning-bg: #fffbeb;       /* Yellow-50 */
--warning-text: #d97706;     /* Yellow-600 */

/* Info */
--info: #3b82f6;             /* Blue-500 */
--info-bg: #eff6ff;          /* Blue-50 */
--info-text: #2563eb;        /* Blue-600 */
```

### Neutral Colors (Gray Scale)
```css
/* Backgrounds */
--bg-app: #f9fafb;           /* Gray-50 - Fundo principal */
--bg-card: #ffffff;          /* White - Cards, modais */
--bg-hover: #f3f4f6;         /* Gray-100 - Hover states */
--bg-secondary: #e5e7eb;     /* Gray-200 - Disabled */

/* Text */
--text-primary: #111827;     /* Gray-900 - Títulos */
--text-secondary: #4b5563;   /* Gray-600 - Corpo */
--text-tertiary: #6b7280;    /* Gray-500 - Hints */
--text-disabled: #9ca3af;    /* Gray-400 - Disabled */

/* Borders */
--border-default: #d1d5db;   /* Gray-300 */
--border-light: #e5e7eb;     /* Gray-200 */
--border-dark: #9ca3af;      /* Gray-400 */
```

### Special Colors
```css
/* Rainbow Gradient (EAU Brand) */
background: linear-gradient(to right,
  #E40303,  /* Red */
  #FF8C00,  /* Orange */
  #FFED00,  /* Yellow */
  #008026,  /* Green */
  #004CFF,  /* Blue */
  #732982   /* Purple */
);
```

**Usage Guidelines:**
- 🎨 **Primary color** para botões principais, links, destaques
- 🌈 **Rainbow gradient** APENAS para branding (header, dividers)
- ⚪ **Gray-50** para background de páginas
- ⚫ **White** para cards, modais, formulários
- 🔴 **Semantic colors** para estados (success, error, warning)

---

## 🔤 TYPOGRAPHY

### Font Family
```css
font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

**Import (Google Fonts):**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
```

### Text Hierarchy

**Page Titles:**
```jsx
<h1 className="text-2xl font-bold text-gray-900 mb-6">
  Dashboard
</h1>
```

**Section Titles:**
```jsx
<h2 className="text-lg font-semibold text-gray-900 mb-4">
  Recent Activity
</h2>
```

**Subsection Titles:**
```jsx
<h3 className="text-base font-medium text-gray-900 mb-3">
  Settings
</h3>
```

**Body Text:**
```jsx
<p className="text-sm text-gray-600">
  This is regular body text
</p>
```

**Small Text / Captions:**
```jsx
<span className="text-xs text-gray-500">
  Last updated 2 hours ago
</span>
```

**Labels (Forms):**
```jsx
<label className="block text-sm font-medium text-gray-700 mb-1">
  Email Address
</label>
```

**Font Weight Scale:**
- `font-normal` (400) - Body text
- `font-medium` (500) - Labels, important text
- `font-semibold` (600) - Subtitles, card titles
- `font-bold` (700) - Page titles, emphasis

---

## 📐 LAYOUT SYSTEM

### Container Widths (CRITICAL!)

**Page Container (Padrão para TODAS as páginas):**
```jsx
<div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  {/* Page content */}
</div>
```

**Variações por Contexto:**
```jsx
// Authentication pages (Login, Register)
<div className="max-w-md mx-auto px-4 py-8">

// Content pages (Articles, Documentation)
<div className="max-w-4xl mx-auto px-4 py-8">

// Forms (Create, Edit)
<div className="max-w-2xl mx-auto px-4 py-8">

// Modals
<div className="max-w-2xl w-full">

// Full width (Tables, Dashboards)
<div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
```

### Page Structure Template
```jsx
// Template padrão para TODAS as páginas
<div className="min-h-screen bg-gray-50">
  {/* Header/Navigation */}
  <nav className="bg-white shadow-sm border-b">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      {/* Nav content */}
    </div>
  </nav>

  {/* Main Content */}
  <main className="py-8">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      {/* Page content */}
    </div>
  </main>
</div>
```

### Grid Layouts

**Dashboard Grid (3 colunas):**
```jsx
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <Card>...</Card>
  <Card>...</Card>
  <Card>...</Card>
</div>
```

**Form Grid (2 colunas):**
```jsx
<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div>
    <label>First Name</label>
    <input />
  </div>
  <div>
    <label>Last Name</label>
    <input />
  </div>
</div>
```

**Sidebar Layout:**
```jsx
<div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
  <aside className="lg:col-span-1">
    {/* Sidebar */}
  </aside>
  <main className="lg:col-span-3">
    {/* Main content */}
  </main>
</div>
```

---

## 🔘 BUTTON SYSTEM

### Button Component (class-variance-authority)

**Primary Button (Ações principais):**
```jsx
<button className="inline-flex items-center justify-center rounded-md text-sm font-medium
                   bg-primary text-white hover:bg-primary/90
                   h-10 px-4 py-2
                   transition-colors focus-visible:outline-none focus-visible:ring-2
                   focus-visible:ring-offset-2 focus-visible:ring-primary
                   disabled:pointer-events-none disabled:opacity-50">
  Save Changes
</button>
```

**Secondary Button:**
```jsx
<button className="inline-flex items-center justify-center rounded-md text-sm font-medium
                   bg-gray-100 text-gray-900 hover:bg-gray-200
                   h-10 px-4 py-2
                   transition-colors focus-visible:outline-none focus-visible:ring-2
                   focus-visible:ring-offset-2
                   disabled:pointer-events-none disabled:opacity-50">
  Cancel
</button>
```

**Outline Button:**
```jsx
<button className="inline-flex items-center justify-center rounded-md text-sm font-medium
                   border border-gray-300 bg-transparent hover:bg-gray-100
                   h-10 px-4 py-2
                   transition-colors focus-visible:outline-none focus-visible:ring-2
                   focus-visible:ring-offset-2
                   disabled:pointer-events-none disabled:opacity-50">
  Export
</button>
```

**Destructive Button (Ações perigosas):**
```jsx
<button className="inline-flex items-center justify-center rounded-md text-sm font-medium
                   bg-red-500 text-white hover:bg-red-600
                   h-10 px-4 py-2
                   transition-colors focus-visible:outline-none focus-visible:ring-2
                   focus-visible:ring-offset-2 focus-visible:ring-red-500
                   disabled:pointer-events-none disabled:opacity-50">
  Delete
</button>
```

**Ghost Button (Ações sutis):**
```jsx
<button className="inline-flex items-center justify-center rounded-md text-sm font-medium
                   hover:bg-gray-100 hover:text-gray-900
                   h-10 px-4 py-2
                   transition-colors focus-visible:outline-none focus-visible:ring-2
                   focus-visible:ring-offset-2
                   disabled:pointer-events-none disabled:opacity-50">
  View More
</button>
```

**Button Sizes:**
```jsx
// Small
className="h-9 px-3 text-sm"

// Default
className="h-10 px-4 py-2 text-sm"

// Large
className="h-11 px-8 text-sm"

// Icon only
className="h-10 w-10"
```

**Button with Icon:**
```jsx
<button className="...">
  <PlusIcon className="w-4 h-4 mr-2" />
  Add New
</button>
```

---

## 📝 FORM COMPONENTS

### Input Fields

**Text Input (Padrão):**
```jsx
<input
  type="text"
  className="flex h-11 w-full rounded-md border border-gray-300 bg-white
             px-3 py-2 text-sm
             placeholder:text-gray-400
             focus-visible:outline-none focus-visible:ring-2
             focus-visible:ring-primary focus-visible:border-primary
             disabled:cursor-not-allowed disabled:opacity-50"
  placeholder="Enter text..."
/>
```

**Form Field with Label:**
```jsx
<div className="space-y-2">
  <label className="block text-sm font-medium text-gray-700">
    Email Address
  </label>
  <input
    type="email"
    className="flex h-11 w-full rounded-md border border-gray-300 bg-white
               px-3 py-2 text-sm placeholder:text-gray-400
               focus-visible:outline-none focus-visible:ring-2
               focus-visible:ring-primary focus-visible:border-primary"
    placeholder="you@example.com"
  />
  <p className="text-xs text-gray-500">We'll never share your email.</p>
</div>
```

**Select Dropdown:**
```jsx
<select
  className="flex h-11 w-full rounded-md border border-gray-300 bg-white
             px-3 py-2 text-sm
             focus-visible:outline-none focus-visible:ring-2
             focus-visible:ring-primary focus-visible:border-primary
             disabled:cursor-not-allowed disabled:opacity-50"
>
  <option>Select an option</option>
  <option value="1">Option 1</option>
  <option value="2">Option 2</option>
</select>
```

**Textarea:**
```jsx
<textarea
  rows={4}
  className="flex w-full rounded-md border border-gray-300 bg-white
             px-3 py-2 text-sm
             placeholder:text-gray-400
             focus-visible:outline-none focus-visible:ring-2
             focus-visible:ring-primary focus-visible:border-primary
             disabled:cursor-not-allowed disabled:opacity-50
             resize-none"
  placeholder="Enter description..."
/>
```

**Checkbox:**
```jsx
<div className="flex items-center space-x-2">
  <input
    type="checkbox"
    id="terms"
    className="h-4 w-4 rounded border-gray-300 text-primary
               focus:ring-2 focus:ring-primary focus:ring-offset-2"
  />
  <label htmlFor="terms" className="text-sm text-gray-700">
    I agree to the terms and conditions
  </label>
</div>
```

**Radio Buttons:**
```jsx
<div className="space-y-2">
  <div className="flex items-center space-x-2">
    <input
      type="radio"
      id="option1"
      name="options"
      className="h-4 w-4 border-gray-300 text-primary
                 focus:ring-2 focus:ring-primary focus:ring-offset-2"
    />
    <label htmlFor="option1" className="text-sm text-gray-700">
      Option 1
    </label>
  </div>
  <div className="flex items-center space-x-2">
    <input
      type="radio"
      id="option2"
      name="options"
      className="h-4 w-4 border-gray-300 text-primary
                 focus:ring-2 focus:ring-primary focus:ring-offset-2"
    />
    <label htmlFor="option2" className="text-sm text-gray-700">
      Option 2
    </label>
  </div>
</div>
```

**Toggle Switch:**
```jsx
<button
  role="switch"
  aria-checked="false"
  className="group relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer
             rounded-full border-2 border-transparent bg-gray-200
             transition-colors duration-200 ease-in-out
             focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2
             data-[state=checked]:bg-primary"
>
  <span
    aria-hidden="true"
    className="pointer-events-none inline-block h-5 w-5 transform rounded-full
               bg-white shadow ring-0 transition duration-200 ease-in-out
               translate-x-0 group-data-[state=checked]:translate-x-5"
  />
</button>
```

---

## 📦 CARD COMPONENTS

### Basic Card Structure
```jsx
<div className="rounded-lg border border-gray-200 bg-white shadow-sm">
  {/* Card Header */}
  <div className="flex flex-col space-y-1.5 p-6">
    <h3 className="text-2xl font-semibold leading-none tracking-tight">
      Card Title
    </h3>
    <p className="text-sm text-gray-500">
      Card description text
    </p>
  </div>

  {/* Card Content */}
  <div className="p-6 pt-0">
    {/* Card body content */}
  </div>

  {/* Card Footer */}
  <div className="flex items-center p-6 pt-0">
    <button className="...">Action</button>
  </div>
</div>
```

### Stats Card (Dashboard)
```jsx
<div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
  <div className="flex items-center justify-between">
    <div>
      <p className="text-sm font-medium text-gray-500">Total Users</p>
      <p className="text-2xl font-bold text-gray-900 mt-2">1,234</p>
    </div>
    <div className="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
      <UsersIcon className="h-6 w-6 text-blue-600" />
    </div>
  </div>
  <p className="text-xs text-gray-500 mt-4">
    <span className="text-green-600 font-medium">+12%</span> from last month
  </p>
</div>
```

### Interactive Card (Hover)
```jsx
<div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm
                transition-all duration-200 hover:shadow-lg hover:border-gray-300
                cursor-pointer">
  {/* Card content */}
</div>
```

---

## 📊 TABLE COMPONENTS

### Standard Table
```jsx
<div className="rounded-lg border border-gray-200 overflow-hidden">
  <table className="min-w-full divide-y divide-gray-200">
    <thead className="bg-gray-50">
      <tr>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
          Name
        </th>
        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
          Email
        </th>
        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
          Actions
        </th>
      </tr>
    </thead>
    <tbody className="bg-white divide-y divide-gray-200">
      <tr className="hover:bg-gray-50 transition-colors">
        <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
          John Doe
        </td>
        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
          john@example.com
        </td>
        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
          <button className="text-primary hover:text-primary/90">Edit</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## 🎭 MODAL / DIALOG

### Modal Overlay & Container
```jsx
{/* Backdrop */}
<div className="fixed inset-0 bg-black bg-opacity-50 z-40
                transition-opacity duration-300" />

{/* Modal Container */}
<div className="fixed inset-0 z-50 flex items-center justify-center p-4">
  <div className="bg-white rounded-lg w-full max-w-2xl max-h-[90vh]
                  overflow-hidden flex flex-col shadow-xl">

    {/* Modal Header */}
    <div className="p-6 border-b border-gray-200">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-gray-900">
          Modal Title
        </h2>
        <button className="text-gray-400 hover:text-gray-600">
          <XIcon className="h-5 w-5" />
        </button>
      </div>
    </div>

    {/* Modal Content */}
    <div className="p-6 overflow-y-auto flex-1">
      {/* Modal body */}
    </div>

    {/* Modal Footer */}
    <div className="p-6 border-t border-gray-200 flex justify-end gap-3">
      <button className="...secondary">Cancel</button>
      <button className="...primary">Save</button>
    </div>
  </div>
</div>
```

---

## 🎯 ICON SYSTEM

### Icon Library: Lucide React
```bash
npm install lucide-react
```

**Standard Icon Usage:**
```jsx
import { User, Calendar, Settings, ChevronRight } from 'lucide-react';

// Small (16px)
<User className="w-4 h-4" />

// Medium (20px)
<Calendar className="w-5 h-5" />

// Large (24px)
<Settings className="w-6 h-6" />

// With color
<User className="w-5 h-5 text-gray-500" />
```

**Icon Button:**
```jsx
<button className="h-10 w-10 rounded-md hover:bg-gray-100
                   flex items-center justify-center">
  <SettingsIcon className="w-5 h-5 text-gray-600" />
</button>
```

**Icon with Text:**
```jsx
<div className="flex items-center space-x-2">
  <CalendarIcon className="w-4 h-4 text-gray-500" />
  <span className="text-sm text-gray-600">Today</span>
</div>
```

---

## 🔄 SPACING SYSTEM

### Padding
```css
p-4   /* 1rem (16px) - Compact */
p-6   /* 1.5rem (24px) - Standard card padding */
p-8   /* 2rem (32px) - Large spacing */
```

### Margin
```css
mt-4, mb-4   /* Vertical spacing between elements */
my-6         /* Standard section spacing */
my-8         /* Large section spacing */
mx-auto      /* Horizontal centering */
```

### Gap (Flexbox/Grid)
```css
gap-2   /* 0.5rem (8px) - Tight */
gap-3   /* 0.75rem (12px) - Small */
gap-4   /* 1rem (16px) - Standard */
gap-6   /* 1.5rem (24px) - Large */
```

---

## 🔔 NOTIFICATION / TOAST

### Toast Position
```jsx
{/* Toast Container - Top Right */}
<div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
  {/* Success Toast */}
  <div className="bg-white border-l-4 border-green-500 rounded-lg shadow-lg p-4
                  flex items-start gap-3 min-w-[320px] max-w-md">
    <div className="flex-shrink-0">
      <CheckCircleIcon className="h-5 w-5 text-green-500" />
    </div>
    <div className="flex-1">
      <p className="text-sm font-medium text-gray-900">Success!</p>
      <p className="text-sm text-gray-600 mt-1">Your changes have been saved.</p>
    </div>
    <button className="flex-shrink-0 text-gray-400 hover:text-gray-600">
      <XIcon className="h-4 w-4" />
    </button>
  </div>
</div>
```

**Toast Variants:**
```jsx
// Success
border-l-4 border-green-500
<CheckCircleIcon className="text-green-500" />

// Error
border-l-4 border-red-500
<XCircleIcon className="text-red-500" />

// Warning
border-l-4 border-yellow-500
<AlertTriangleIcon className="text-yellow-500" />

// Info
border-l-4 border-blue-500
<InfoIcon className="text-blue-500" />
```

---

## 🎨 BADGE / TAG COMPONENTS

### Status Badges
```jsx
{/* Success */}
<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                 bg-green-100 text-green-800">
  Active
</span>

{/* Error */}
<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                 bg-red-100 text-red-800">
  Inactive
</span>

{/* Warning */}
<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                 bg-yellow-100 text-yellow-800">
  Pending
</span>

{/* Info */}
<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                 bg-blue-100 text-blue-800">
  Draft
</span>

{/* Neutral */}
<span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                 bg-gray-100 text-gray-800">
  Default
</span>
```

---

## ⏳ LOADING STATES

### Loading Spinner
```jsx
<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
```

### Loading Button
```jsx
<button disabled className="...disabled:opacity-50 disabled:cursor-not-allowed">
  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2" />
  Loading...
</button>
```

### Skeleton Loader
```jsx
<div className="animate-pulse space-y-3">
  <div className="h-4 bg-gray-200 rounded w-3/4" />
  <div className="h-4 bg-gray-200 rounded w-1/2" />
  <div className="h-4 bg-gray-200 rounded w-5/6" />
</div>
```

---

## 📱 RESPONSIVE BREAKPOINTS

```css
/* Tailwind Default Breakpoints */
sm: 640px    /* Small devices (landscape phones) */
md: 768px    /* Medium devices (tablets) */
lg: 1024px   /* Large devices (desktops) */
xl: 1280px   /* Extra large devices */
2xl: 1536px  /* 2X large devices */
```

**Usage Examples:**
```jsx
{/* Mobile first */}
<div className="text-sm md:text-base lg:text-lg">
  Responsive text
</div>

{/* Hide on mobile */}
<div className="hidden md:block">
  Desktop only content
</div>

{/* Grid responsive */}
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  {/* Items */}
</div>
```

---

## 🎭 HOVER & FOCUS STATES

### Standard Hover States
```jsx
{/* Buttons */}
hover:bg-primary/90
hover:bg-gray-100

{/* Text links */}
hover:text-primary hover:underline

{/* Cards */}
hover:shadow-lg hover:border-gray-300

{/* Icons */}
hover:text-gray-900
```

### Focus States (Accessibility)
```jsx
{/* Standard focus ring */}
focus-visible:outline-none
focus-visible:ring-2
focus-visible:ring-primary
focus-visible:ring-offset-2

{/* Input focus */}
focus-visible:ring-2
focus-visible:ring-primary
focus-visible:border-primary
```

---

## ✅ IMPLEMENTATION CHECKLIST

**Ao criar um novo componente ou página, garanta:**

- [ ] **Layout Container**: Use `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8`
- [ ] **Background**: `bg-gray-50` para páginas, `bg-white` para cards
- [ ] **Typography**: Font Inter com hierarquia correta
- [ ] **Buttons**: Variante correta (primary, secondary, outline, destructive, ghost)
- [ ] **Forms**: Altura consistente `h-11` para inputs
- [ ] **Spacing**: Sistema de espaçamento padrão (p-6, gap-4, my-6)
- [ ] **Colors**: Apenas cores do sistema definido
- [ ] **Icons**: Lucide React com tamanhos padrões (w-4, w-5, w-6)
- [ ] **Hover States**: Todos os elementos interativos
- [ ] **Focus States**: Acessibilidade (focus-visible:ring-2)
- [ ] **Responsive**: Testa em todos os breakpoints
- [ ] **Borders**: `border-gray-200` ou `border-gray-300`
- [ ] **Shadows**: `shadow-sm` para cards, `shadow-lg` para modals
- [ ] **Transitions**: `transition-colors duration-200` para smooth effects

---

## 🔧 TAILWIND CONFIG

**Adicione ao seu `tailwind.config.js`:**

```javascript
export default {
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#005EB8',
          50: '#e6f1ff',
          100: '#b3d4ff',
          200: '#80b8ff',
          300: '#4d9bff',
          400: '#1a7eff',
          500: '#005EB8',
          600: '#004a91',
          700: '#003d7a',
          800: '#002952',
          900: '#001429',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      backgroundImage: {
        'rainbow-gradient': 'linear-gradient(to right, #E40303, #FF8C00, #FFED00, #008026, #004CFF, #732982)',
      },
    },
  },
}
```

---

## 🎯 QUICK EXAMPLES

### Login Page
```jsx
<div className="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
  <div className="max-w-md w-full space-y-8">
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Sign in</h2>

      <form className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Email
          </label>
          <input
            type="email"
            className="flex h-11 w-full rounded-md border border-gray-300 bg-white
                       px-3 py-2 text-sm placeholder:text-gray-400
                       focus-visible:outline-none focus-visible:ring-2
                       focus-visible:ring-primary focus-visible:border-primary"
            placeholder="you@example.com"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Password
          </label>
          <input
            type="password"
            className="flex h-11 w-full rounded-md border border-gray-300 bg-white
                       px-3 py-2 text-sm
                       focus-visible:outline-none focus-visible:ring-2
                       focus-visible:ring-primary focus-visible:border-primary"
          />
        </div>

        <button
          type="submit"
          className="w-full inline-flex items-center justify-center rounded-md
                     text-sm font-medium bg-primary text-white hover:bg-primary/90
                     h-11 px-4 py-2 transition-colors"
        >
          Sign in
        </button>
      </form>
    </div>
  </div>
</div>
```

### Dashboard Page
```jsx
<div className="min-h-screen bg-gray-50">
  <main className="py-8">
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <h1 className="text-2xl font-bold text-gray-900 mb-6">Dashboard</h1>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div className="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
          <p className="text-sm font-medium text-gray-500">Total Users</p>
          <p className="text-2xl font-bold text-gray-900 mt-2">1,234</p>
        </div>
        {/* More stats cards... */}
      </div>

      {/* Content Card */}
      <div className="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div className="p-6 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
        </div>
        <div className="p-6">
          {/* Content */}
        </div>
      </div>
    </div>
  </main>
</div>
```

---

## 📚 RESOURCES

**Libraries Used:**
- **Tailwind CSS** - Utility-first CSS framework
- **Lucide React** - Icon library (https://lucide.dev/)
- **class-variance-authority** - Variant system for components
- **clsx + tailwind-merge** - Class merging utility

**Inspiration:**
- shadcn/ui - Component design patterns
- Tailwind UI - Layout patterns
- Radix UI - Accessible primitives

---

## 💡 AI IMPLEMENTATION TIPS

**Para replicar este design em outro projeto:**

1. ✅ **Copie o Tailwind config** exatamente como está
2. ✅ **Use os mesmos nomes de classes** (não invente novas)
3. ✅ **Mantenha as proporções** (h-11 para inputs, p-6 para cards)
4. ✅ **Siga os exemplos de código** ao pé da letra
5. ✅ **Use Lucide React** para ícones (mesma biblioteca)
6. ✅ **Mantenha a hierarquia visual** (títulos, subtítulos, corpo)
7. ✅ **Não pule focus states** (acessibilidade é fundamental)
8. ✅ **Teste responsividade** em todos os breakpoints
9. ✅ **Use a mesma estrutura de container** (max-w-7xl)
10. ✅ **Mantenha consistência de cores** (primary, gray scale)

---

**📋 Versão do Documento:** 1.0
**📅 Última Atualização:** 20 de Novembro de 2025
**🏢 Projeto:** EAU Members Portal
**💻 Stack:** React + TypeScript + Tailwind CSS
