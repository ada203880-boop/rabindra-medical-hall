# Rabindra Medical Hall — e-Pharmacy Design & Architecture

Professional WordPress, WooCommerce, and Astra Child Theme architecture for **Rabindra Medical Hall**, a licensed neighborhood pharmacy located in Badambadi Colony, Cuttack, Odisha.

---

## 🌐 Live GitHub Pages Demo
Once GitHub Pages is enabled for this repository (under **Settings > Pages > Deploy from branch `main` / `root`**), the interactive visual showcase is accessible at:
```
https://<your-username>.github.io/<repository-name>/
```

---

## 🛡️ Architecture & Key Features

* **Mobile-First UX/UI**: Optimized for 320px–390px viewports (iOS Safari / Android) with strict WCAG 2.5.5 touch target compliance (≥44px minimum for all actions).
* **Pharmacy Regulatory Credibility**: Integrated badges and footer displaying:
  * Drug Licences: `CU-28841/R`, `CU-28842/RC`, `CU-11433/RX`
  * GSTIN: `21AARFR5599J1ZH`
* **Delivery Location System**: Optional GPS capture with automatic coordinates and Google Maps integration, supporting local zones in Cuttack (within 10 km).
* **WhatsApp Order Request Workflow**: Seamless WhatsApp order request generator providing structured order details directly to the pharmacy team.
* **16 Approved Healthcare Categories**:
  1. Vitamins & Supplements
  2. Pain Relief
  3. Digestive Care
  4. Personal Care
  5. Baby Care
  6. Respiratory Care
  7. Heart Care
  8. Skin Care
  9. Eye Care
  10. Oral Care
  11. Health & Wellness
  12. Women's Health
  13. Men's Health
  14. Sexual Wellness
  15. Mother & Maternity Care
  16. Medical Devices & Equipment

---

## 📁 Repository Structure

```
├── index.html                   # Static visual showcase for GitHub Pages hosting
├── assets/                      # CSS design system, responsive UI, JS & icons
│   ├── css/
│   │   ├── custom.css          # Primary component design system
│   │   ├── rmh-mobile-ui.css   # Mobile responsive UX/UI layer
│   │   └── rmh-checkout.css    # Checkout form styling
│   ├── js/                     # Location, search & checkout interactivity
│   └── images/                 # Category visuals & branding assets
├── theme/                       # Clean WordPress Child Theme source code
│   └── rabindra-medical-hall-child/
│       ├── functions.php       # HPOS hooks, shortcodes, WhatsApp generators
│       ├── style.css           # Astra child theme definition
│       ├── inc/                # e-Pharmacy modules & notification handlers
│       └── woocommerce/        # Template overrides
└── .gitignore                   # Protection rules preventing sensitive leaks
```

---

## ⚠️ Important Disclaimer

> [!NOTE]
> This GitHub repository contains an illustrative visual showcase and the front-end/theme source code.
> 
> * All products, prices, and forms displayed on the GitHub Pages demo are for portfolio and design presentation purposes.
> * Real transactions, inventory, patient records, and WhatsApp order fulfillment operate exclusively on the private, secure pharmacy production server.
> * No customer data, private API keys, or database credentials are contained in this repository.
