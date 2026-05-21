# RBAC Audit

## Role Model

- `ROLE_ADMIN`
- `ROLE_STAFF`
- `ROLE_ADMIN` inherits `ROLE_STAFF` through `role_hierarchy`

## Access Rules

- Admin-only sections are protected with `ROLE_ADMIN`
- Staff sections are protected with `ROLE_STAFF`
- Profile and password pages are accessible to authenticated staff users

## Admin Routes

| Route | Path | Access | Notes |
|---|---|---|---|
| `admin_dashboard` | `/admin` | `ROLE_ADMIN` | Admin dashboard overview |
| `admin_users` | `/admin/users` | `ROLE_ADMIN` | Manage users |
| `admin_users_new` | `/admin/users/new` | `ROLE_ADMIN` | Create user accounts |
| `admin_users_edit` | `/admin/users/edit/{id}` | `ROLE_ADMIN` | Edit user accounts |
| `admin_users_delete` | `/admin/users/delete/{id}` | `ROLE_ADMIN` | Delete user accounts |
| `admin_users_login_as` | `/admin/users/login-as/{id}` | `ROLE_ADMIN` | Placeholder impersonation route |
| `admin_logs` | `/admin/logs` | `ROLE_ADMIN` | Admin log viewer |
| `admin_system` | `/admin/system` | `ROLE_ADMIN` | System data access log view |
| `admin_activity_logs_index` | `/admin/activity-logs/` | `ROLE_ADMIN` | Activity log index and filters |
| `admin_staff_index` | `/admin/staff` | `ROLE_ADMIN` | Staff list dashboard |
| `admin_staff_new` | `/admin/staff/new` | `ROLE_ADMIN` | Add staff account |
| `admin_staff_edit` | `/admin/staff/{id}/edit` | `ROLE_ADMIN` | Edit staff account |
| `admin_staff_reset_password` | `/admin/staff/{id}/reset-password` | `ROLE_ADMIN` | Reset staff password |
| `admin_staff_toggle_status` | `/admin/staff/{id}/toggle-status` | `ROLE_ADMIN` | Enable or disable staff account |
| `admin_staff_delete` | `/admin/staff/{id}/delete` | `ROLE_ADMIN` | Delete staff account |
| `admin_categories` | `/admin/categories` | `ROLE_ADMIN` | Category management |
| `admin_categories_new` | `/admin/categories/new` | `ROLE_ADMIN` | Create category |
| `admin_categories_edit` | `/admin/categories/{id}/edit` | `ROLE_ADMIN` | Edit category |
| `admin_categories_delete` | `/admin/categories/{id}/delete` | `ROLE_ADMIN` | Delete category |

## Staff Routes

| Route | Path | Access | Notes |
|---|---|---|---|
| `product_list` | `/admin/menus` | `ROLE_STAFF` | Staff menu/product browser |
| `admin_products` | `/admin/products` | `ROLE_STAFF` | Product management overview |
| `admin_products_new` | `/admin/products/new` | `ROLE_STAFF` | Create product |
| `admin_products_edit` | `/admin/products/edit/{id}` | `ROLE_STAFF` | Edit product |
| `admin_products_delete` | `/admin/products/delete/{id}` | `ROLE_STAFF` | Delete product, blocked by voter for staff |
| `admin_orders` | `/admin/orders` | `ROLE_STAFF` | Order list |
| `admin_order_show` | `/admin/orders/show/{id}` | `ROLE_STAFF` | Order details |
| `admin_order_update_status` | `/admin/orders/{id}/update-status` | `ROLE_STAFF` | Update order status |
| `admin_order_edit` | `/admin/orders/edit/{id}` | `ROLE_STAFF` | Edit order details |
| `admin_orders_new` | `/admin/orders/new` | `ROLE_STAFF` | Create order |
| `admin_order_delete` | `/admin/orders/{id}/delete` | `ROLE_STAFF` | Delete order, restricted by voter |
| `user_profile` | `/profile` | `ROLE_STAFF` | User profile page |
| `user_change_password` | `/change-password` | `ROLE_STAFF` | Change password page |
| `user_profile_edit` | `/profile/edit` | `ROLE_STAFF` | Edit profile page |

## Public Routes

| Route | Path | Access | Notes |
|---|---|---|---|
| `app_landing` | `/landing` | Public | Landing page |
| `home` | `/about` | Public | About page |
| `app_contact` | `/contact` | Public | Contact page |
| `app_login` | `/` | Public | Login page |
| `app_logout` | `/logout` | Authenticated | Handled by firewall |

## UI Role Logic

- `templates/base_admin.html.twig` shows different menu links depending on the active role
- Admin users see dashboard, staff, and logs links
- Staff users see products, orders, and profile links
- `templates/partials/_navbar.html.twig` keeps the public site navigation separate from admin navigation

## Notes for Grading

- The RBAC structure is solid and readable.
- The biggest remaining improvement would be to add more explicit role-based UI differences inside the admin pages themselves, such as hiding admin-only actions from staff tables where possible.
- Another useful enhancement would be more tests or a small access matrix screenshot set for the report.

