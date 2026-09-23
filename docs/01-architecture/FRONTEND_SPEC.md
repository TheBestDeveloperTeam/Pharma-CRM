# Frontend Rebuild Spec

## 1. Visual Language & Framework
- **Theme**: AdminLTE v3.2.0 (almsaeedstudio.io)
- **Base Framework**: Bootstrap 4.6 (Required by AdminLTE 3.x)
- **Styling**: Vanilla CSS (No Tailwind, No Alpine). Custom styles added in `/public/assets/css/app.css` using AdminLTE color palette.
- **Interactivity**: Vanilla JavaScript ES6 (No React/Vue). Custom logic in `/public/assets/js/app.js`.

## 2. Layout Structure
- **Master Layout**: `views/layouts/master.php`
  - Includes Header (Navbar), Sidebar (Navigation menu), Footer, and Control Sidebar.
  - Dynamically injects content into `.content-wrapper`.
- **Partials**: `views/components/` (e.g., `_alert.php`, `_modal.php`, `_datatable.php`).

## 3. Screen Inventory & Mapping
| Module | Screen | Route (View) | Controller | API Endpoints Called | Partials Used |
|---|---|---|---|---|---|
| Auth | Login | `/login` | `Web\AuthController` | `POST /api/v1/auth/token` | None |
| Dashboard | Dashboard | `/` | `Web\DashboardController` | `GET /api/v1/dashboard/stats` | `_widget_box` |
| Masters | Doctor List | `/doctors` | `Web\DoctorController` | `GET /api/v1/doctors` | `_datatable`, `_modal` |
| Inventory | Batch List | `/batches` | `Web\BatchController` | `GET /api/v1/batches` | `_datatable` |
| Orders | Order Create | `/orders/create`| `Web\OrderController` | `POST /api/v1/orders` | `_form`, `_product_select`|
| Roles | Role Mgt | `/roles` | `Web\RoleController` | `GET /api/v1/roles` | `_datatable` |

## 4. Components & Interactions
- **Datatables**: jQuery DataTables initialized via Vanilla JS wrappers. Data sourced via AJAX from the REST API using Server-Side processing mode.
- **Forms**: Standard HTML `<form>` tags. Submission intercepted by Vanilla JS (`addEventListener('submit')`) to serialize data and dispatch `fetch()` POST/PUT requests to the API.
- **Client-Side Validation**: HTML5 attributes (`required`, `pattern`, `min`, `max`) coupled with a Vanilla JS validation script that mirrors the server's `VALIDATION_MATRIX.csv`.
- **Modals**: Bootstrap 4 Modals toggled via Vanilla JS. Used for "Delete Confirmations" and "Quick Create" actions.
- **State Management**: No Redux. State is maintained in the DOM (`data-*` attributes) or transient JS variables. Token stored in `sessionStorage`.

## 5. Asset Pipeline
- **CSS**: Minified and concatenated manually or via a lightweight npm script (e.g., `esbuild` or standard CLI minifier) into `app.min.css`. Cache-busted using filemtime in PHP layout.
- **JS**: Modularized using ES6 imports, compiled to `app.min.js`.
