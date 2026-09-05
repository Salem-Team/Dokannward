# Technical Specification

Project: Korfdya E-commerce Platform

## Core Requirements

- Multi-language: English & Arabic (RTL support) ✅ IMPLEMENTED
- Specialized bag e-commerce with detailed product presentation
- Laravel 12.4 backend with modern frontend stack ✅ IMPLEMENTED

## Technical Stack

**Backend:** Laravel 12.4, MySQL 8.0+ ✅
**Frontend:** Blade templates, Tailwind CSS, Livewire for interactive components ✅
**Payments:** Stripe, Mada, Apple Pay integration ⏳ PLANNED
**Shipping:** ARAMEX/FedEx API integration ⏳ PLANNED
**Authentication:** Laravel Breeze with multi-guard (customer/admin) ⏳ PLANNED

## Implemented Features

### ✅ Multi-language System (COMPLETED)
- Laravel localization configured for EN/AR
- SetLocale middleware registered globally in `bootstrap/app.php`
- RTL/LTR CSS support with `tailwindcss-rtl` plugin
- JSON translation files (`resources/lang/en.json`, `resources/lang/ar.json`)
- JSON fields in database for translatable content (Product model: name, description)
- Dynamic direction attribute: `dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"`
- Font switching: Noto Naskh Arabic (AR) / Playfair Display + Inter (EN)
- Language switcher component with live switching

### ✅ Premium Frontend Design (COMPLETED)
- Luxury landing page with hero section
- Featured collections grid
- Mobile-first responsive navigation
- Brand colors: Deep Navy (#1E2A38), Warm Taupe (#C4B7A6), Gold (#D4AF37)
- Custom CSS animations and transitions
- Reusable component classes (buttons, cards, inputs)
- Sticky header with shadow on scroll
- Accessibility-focused (WCAG 2.1 AA considerations)

### ✅ Product Management (BASIC IMPLEMENTATION)
- Products table with JSON translation fields
- Product model with CRUD operations
- ProductController with full CRUD routes
- Factories and seeders for Products, Brands, Categories
- Bilingual product data (EN/AR)

## Features to Implement

### ⏳ Product Management (ADVANCED)
- Multi-image gallery with zoom
- Bag-specific attributes (dimensions, material, capacity)
- Inventory tracking with low-stock alerts
- Advanced filtering by bag characteristics
- Product variants (size, color)
- Product reviews and ratings

### ⏳ Shopping & Checkout
- Session-based cart → persistent orders
- Multi-step checkout with address management
- Real-time shipping calculations
- Order tracking with status updates
- Payment gateway integration (Stripe, Mada, Apple Pay)
- Email notifications

### ⏳ Admin Panel
- Comprehensive dashboard for products, orders, customers
- Supplier management
- Inventory control
- Sales reporting and analytics
- User role management
- Order fulfillment workflow

### ⏳ Authentication & User Management
- Laravel Breeze integration
- Multi-guard authentication (customer/admin)
- User profiles and preferences
- Address book management
- Order history

### ⏳ Security & Performance
- Data encryption for sensitive information
- Payment tokenization
- Image optimization and lazy loading
- Caching strategies (Redis/Memcached)
- API rate limiting
- CSRF protection
- SQL injection prevention

## Database Structure

### Current Implementation
- `products` table with JSON fields (name, description)
- `brands` table with JSON fields (name, description)
- `categories` table with JSON fields (name, slug)
- JSON fields enable multi-language content without additional tables

### Planned Tables
- `product_images` - Separate table with ordering
- `orders` - Order management
- `order_items` - Order line items
- `addresses` - User shipping addresses
- `cart_items` - Persistent shopping cart
- `tracking` - Shipment tracking with event history
- `inventory` - Stock management with alerts
- `reviews` - Product reviews and ratings

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── ProductController.php ✅
│   │   └── LanguageController.php ✅
│   └── Middleware/
│       └── SetLocale.php ✅
├── Livewire/
│   ├── Navigation.php ✅
│   └── LanguageSwitcher.php ✅
└── Models/
    ├── Product.php ✅
    ├── Brand.php ✅
    └── Category.php ✅

resources/
├── css/
│   └── app.css ✅ (Premium styles, animations)
├── lang/
│   ├── en.json ✅
│   └── ar.json ✅
└── views/
    ├── welcome.blade.php ✅ (Premium landing page)
    ├── layouts/
    │   └── app.blade.php ✅
    └── livewire/
        ├── navigation.blade.php ✅
        └── language-switcher.blade.php ✅

config/
└── app.php ✅ (Locale configuration)

bootstrap/
└── app.php ✅ (SetLocale middleware registered)
```

## Next Steps (Priority Order)

1. **Authentication System**
   - Install Laravel Breeze
   - Configure multi-guard authentication
   - Create customer and admin dashboards

2. **Shopping Cart**
   - Session-based cart implementation
   - Cart persistence for logged-in users
   - Cart operations (add, update, remove, clear)

3. **Product Gallery**
   - Multi-image upload functionality
   - Image ordering and management
   - Zoom and lightbox features
   - 360-degree view capability

4. **Checkout Process**
   - Multi-step checkout form
   - Address management
   - Payment gateway integration
   - Order confirmation emails

5. **Admin Panel**
   - Product management interface
   - Order processing workflow
   - Inventory management
   - Analytics dashboard

Note: All components must maintain both LTR (English) and RTL (Arabic) layout support. Premium design language (luxury, minimalism, premium whitespace) should be consistent across all new features.


