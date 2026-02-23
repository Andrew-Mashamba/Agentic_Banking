# Project scan – Agentic Banking

**Scanned:** Post–Smart-Dining conversion. Focus: runtime safety, leftover dining code, tests, and structure.

---

## 1. Routes

| File | Status |
|------|--------|
| `routes/web.php` | OK – home, login, logout, dashboard→settings, settings (role:admin,manager), WhatsApp webhook, test-errors (dev) |
| `routes/api.php` | OK – only `webhooks/whatsapp` (get verify, post handle) |

No remaining references to removed named routes in **app** code (sidebar/nav already fixed).

---

## 2. Views and UI

| Item | Status |
|------|--------|
| **app-header.blade.php** | Fixed – "Agentic Banking" title, Help link replaced with Settings (admin/manager), `@livewire('notification-bell')` removed |
| **app-sidebar.blade.php** | OK – only Dashboard + Settings for admin/manager; other roles see "Contact an administrator" |
| **navigation-menu.blade.php** | OK – Dashboard + Settings (for admin/manager), Help removed |
| **app-layout.blade.php** | Fixed – default title "Agentic Banking" |
| **layouts/app.blade.php** | Not checked – may still reference old title; consider aligning with app-layout |

Removed views/components: dining-specific views, Livewire (except Settings), help, receipts, kitchen/bar, etc. – not referenced in kept routes.

---

## 3. Auth and authorization

| Item | Status |
|------|--------|
| **AuthServiceProvider** | Fixed – uses `User`; gates use `User` and `role`; `Staff` and `StaffPolicy` removed from provider |
| **StaffPolicy** | Orphaned – no longer registered; can be deleted or kept for reference |
| **CheckRole / ApiCheckRole** | Still use role strings (admin, manager, etc.) – fine for User-based roles |
| **DeviceToken** | Fixed – `user_id` and `User` relationship; migration updated to `user_id` + `users` |
| **FcmService** | Fixed – `sendToUser`, `sendToUsers`, `sendToRole`; uses `User` |
| **SendFcmNotification job** | Fixed – target types: `user`, `users`, `role` |

If the app was already migrated with `staff_id` on `device_tokens`, you need a one-off migration to rename `staff_id` → `user_id` and point FK to `users` (and optionally backfill from old `staff` if you have a mapping). For a **fresh** install, current migration is enough.

---

## 4. Models

| Model | Status |
|------|--------|
| User, Guest, Setting, WhatsAppSession, GuestConversation, DeviceToken, ErrorLog | Kept and in use |
| Order, OrderItem, Payment, Tip, Table, MenuItem, MenuCategory, Reservation, Staff, etc. | Removed – not in `app/Models` |

**DeviceToken** now belongs to **User** (`user_id`). Any code that still referenced `staff_id` or `->staff()` in app has been updated (FcmService, Job).

---

## 5. Controllers and Livewire

| Item | Status |
|------|--------|
| **Web** | AuthController, WhatsApp\WebhookController only |
| **Livewire** | Only SettingsManagement (settings page) |
| **API** | No app API controllers (only webhook in api.php) |

---

## 6. WhatsApp / AI

| Item | Status |
|------|--------|
| **MessageHandler** | Does not use FlowManager; AI path via ProcessAiMessage |
| **ProcessAiMessage** | Runs AI and sends WhatsApp reply (no dining models in flow) |
| **AiAgentService** | Dining context methods stubbed (menu, orders, tables, reservations, promotions, etc.); system prompt still contains dining *instruction text* (not executed PHP) – safe, can be rewritten for banking later |
| **GuestMemoryService** | No Order usage; `getRecentOrders()` returns `[]` |
| **FlowManager, MenuBot, PaymentBot, ReservationBot, NotificationBot** | Still present under `app/Services/WhatsApp/` but **not used** by MessageHandler; they reference deleted models – safe to delete or refactor later for bank flows |
| **QRCodeService (table QR)** | Dining-specific; unused in current flow |

---

## 7. Remaining dining-related code (non-runtime or optional)

These do not run in the current “bank” flow but still reference removed models or dining concepts. They can cause errors if invoked (e.g. by tests or future code).

- **app/Services/WhatsApp/AiAgentService.php**  
  - System prompt text still describes Order::create, OrderItem, Reservation, etc. (comment/instruction only).  
  - `buildPromotionsContext()` still called; method returns `''`.

- **app/Listeners/**  
  - UpdateKitchenDisplay, SendWaiterRequestFcm, SendPrepStatusFcmNotification, SendOrderReadyFcmNotification, SendOrderNotification, NotifyWaiter, SendManagerRequestFcm, DeductInventoryStock – use Order, Staff, Table, etc.  
  - Only matter if events are still dispatched; **EventServiceProvider** may still register them.

- **app/Events/**  
  - OrderCreated, OrderStatusChanged, OrderItemReady, PaymentReceived, WaiterRequested, ManagerRequested, etc. – dining-specific.

- **app/Observers/OrderObserver** – Order model removed; observer will error if registered.

- **app/Services/**  
  - OrderWorkflowService, OrderManagement/*, Menu/*, GuestSession/SessionService, Payment/TipService, Payment/MpesaService, StripePaymentService, OrderWorkflowException, PaymentException, InventoryException – dining/order/payment flow.

- **app/Notifications/**  
  - LowStockAlert (MenuItem), AiStaffNotification – reference removed models.

- **app/Http/Resources/**  
  - TableResource, ReservationResource, PaymentResource, OrderResource, MenuItemResource, MenuCategoryResource, GuestResource – for removed models.

- **app/Http/Requests/**  
  - UpdateTableStatusRequest, UpdateOrderStatusRequest, StoreOrderRequest, ProcessPaymentRequest, etc. – dining/order.

- **app/Console/Commands/**  
  - UpdateOrderStatuses, TestReverbBroadcast, TestOrderBroadcast, SendReminders, DailySalesSummary, GenerateDocumentationPdfs, PosTestCredentials – reference Order, Staff, Table, etc.

- **app/Providers/EventServiceProvider**  
  - May still register OrderObserver and listeners for OrderCreated, etc. – worth checking and removing for bank.

- **database/seeders/**  
  - TestReceiptSeeder, StaffSeeder, RoleAndUserSeeder, ReverbTestSeeder, OrderSeeder – use Staff, Order, etc.  
  - **database/factories/OrderFactory.php** – Order, Staff.

- **prd.json**  
  - Old product requirements (sidebar, reports, staff); can ignore or update for bank.

---

## 8. Tests

Most **Feature** and **scripts** under `tests/` still use removed models and routes and will **fail** unless updated or excluded:

- **Feature:** WhatsAppIntegrationTest, OrderReceivingTest, ChatbotFlowTest, StripePaymentIntegrationTest, SecurityAuditTest, RoleBasedApiAccessTest, ReverbBroadcastTest, ReceiptPdfGenerationTest, ProductionReadinessTest, OrderWorkflowTest, OrderWorkflowServiceTest, InventoryDeductionTest, AuthorizationTest (StaffPolicy), ApiOrdersTest, ApiAuthenticationTest, LandingPageRouteTest (kitchen.display, bar.display), etc.
- **scripts:** test-reverb-broadcast, test_story_41*, test_order_event, test_inventory_debug, test-whatsapp-integration, test-story-49*, test-broadcast*, test-api-story-42*, test-api-endpoints, etc.

Recommendation: run test suite once; then either (a) temporarily exclude/skip these or (b) refactor tests to use User and bank flows only.

---

## 9. Database migrations

**Current migrations (13 files):**

- users, cache, jobs, personal_access_tokens, guests, two_factor_columns, phone_number/status (users), settings, error_logs, notifications, **device_tokens (user_id → users)**, whatsapp_sessions, guest_conversations.

No migrations reference `staff` or other removed tables. For an existing DB that already had `device_tokens` with `staff_id`, add a migration to rename column and repoint FK to `users` (and backfill if needed).

---

## 10. Summary

| Area | Status |
|------|--------|
| Routes & nav/sidebar/header | Clean; only bank-relevant routes and links |
| Auth & gates | User-based; DeviceToken and FCM use User |
| WhatsApp / AI | Working without dining models; bots/prompt can be adapted for bank later |
| Remaining dining code | In listeners, events, services, console, seeders, tests – remove or refactor when you add bank features |
| Tests | Largely still dining-oriented; will fail until updated or skipped |

The app should run for: login → Dashboard → Settings, and WhatsApp webhook → AI reply, with no dependency on Staff, Order, or other removed models in the main flow. Remaining work is optional cleanup (listeners, events, old tests, prompt wording) and adding bank-specific features.
