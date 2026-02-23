# WhatsApp Flows Setup

WhatsApp Flows let users complete structured forms in chat (lists, amount input, PIN/passcode) instead of free text only.

## What’s implemented

- **Flow types:** transfer, loan_application, card_block, amount_passcode, add_beneficiary, recurring_transfer, cardless_withdrawal, loan_repayment, fixed_deposit, investment, card_freeze, card_unfreeze, book_appointment, support_ticket
- **Flow data endpoint:** `POST /api/webhooks/whatsapp-flows/data` (Meta calls this for dynamic lists and routing)
- **Sending flows:** `WhatsAppService::sendFlowMessage()`, internal `POST /api/internal/send-flow`
- **AI:** System prompt tells the AI to call `/send-flow` for the above intents so the client gets a structured form

## 1. Publish flows in Meta

1. Get Flow JSON for each type (run for each flow type you want to enable):
   ```bash
   php artisan whatsapp:flows-publish transfer > transfer_flow.json
   php artisan whatsapp:flows-publish loan_application > loan_flow.json
   php artisan whatsapp:flows-publish card_block > card_block_flow.json
   php artisan whatsapp:flows-publish amount_passcode > amount_passcode_flow.json
   php artisan whatsapp:flows-publish add_beneficiary > add_beneficiary_flow.json
   php artisan whatsapp:flows-publish recurring_transfer > recurring_transfer_flow.json
   php artisan whatsapp:flows-publish cardless_withdrawal > cardless_withdrawal_flow.json
   php artisan whatsapp:flows-publish loan_repayment > loan_repayment_flow.json
   php artisan whatsapp:flows-publish fixed_deposit > fixed_deposit_flow.json
   php artisan whatsapp:flows-publish investment > investment_flow.json
   php artisan whatsapp:flows-publish card_freeze > card_freeze_flow.json
   php artisan whatsapp:flows-publish card_unfreeze > card_unfreeze_flow.json
   php artisan whatsapp:flows-publish book_appointment > book_appointment_flow.json
   php artisan whatsapp:flows-publish support_ticket > support_ticket_flow.json
   ```

2. In [Meta Business Suite](https://business.facebook.com) go to WhatsApp > Flows (or use [Flow Playground](https://developers.facebook.com/docs/whatsapp/flows/playground/)).
3. Create a new flow for each JSON file. Paste the JSON; set the **Data channel URL** to your public HTTPS URL:  
   `https://your-domain.com/api/webhooks/whatsapp-flows/data`
4. Publish each flow and note the **Flow ID** Meta returns.

## 2. Configure .env

```env
# Optional: override if your app URL is different for flows
# WHATSAPP_FLOWS_DATA_URI=https://your-domain.com/api/webhooks/whatsapp-flows/data

# After publishing each flow in Meta, set:
WHATSAPP_FLOW_ID_TRANSFER=123456789
WHATSAPP_FLOW_ID_LOAN=123456790
WHATSAPP_FLOW_ID_CARD_BLOCK=123456791
WHATSAPP_FLOW_ID_AMOUNT_PASSCODE=123456792
# Add the following after publishing each flow in Meta:
# WHATSAPP_FLOW_ID_ADD_BENEFICIARY=
# WHATSAPP_FLOW_ID_RECURRING_TRANSFER=
# WHATSAPP_FLOW_ID_CARDLESS_WITHDRAWAL=
# WHATSAPP_FLOW_ID_LOAN_REPAYMENT=
# WHATSAPP_FLOW_ID_FIXED_DEPOSIT=
# WHATSAPP_FLOW_ID_INVESTMENT=
# WHATSAPP_FLOW_ID_CARD_FREEZE=
# WHATSAPP_FLOW_ID_CARD_UNFREEZE=
# WHATSAPP_FLOW_ID_BOOK_APPOINTMENT=
# WHATSAPP_FLOW_ID_SUPPORT_TICKET=
```

## 3. Sending a flow

- **From backend/AI:**  
  `POST http://127.0.0.1:8080/api/internal/send-flow`  
  Headers: `X-User-Id: <user_id>`  
  Body: `{"flow_type": "transfer", "body_text": "Tap to fill the transfer form.", "button_text": "Open"}`

- **Flow types:** transfer, loan_application, card_block, amount_passcode, add_beneficiary, recurring_transfer, cardless_withdrawal, loan_repayment, fixed_deposit, investment, card_freeze, card_unfreeze, book_appointment, support_ticket

## 4. Data endpoint

- Must be **public** and **HTTPS** in production.
- Meta sends `flow_token`, `screen`, `data` (and may send customer/phone); we resolve the user and return next screen + data (e.g. account/beneficiary lists) or `SUCCESS` with a completion message.
- Timeout: respond within **15 seconds**.

## 5. Flow JSON schema

Flow definitions live in `App\Services\WhatsApp\FlowDefinitions`. Screens use:

- **Dropdown** – options from the data endpoint (accounts, beneficiaries, cards, loan products).
- **TextInput** – `input-type`: text, number, passcode (PIN), password.
- **Footer** – button to go to next screen or complete.

Adjust screens and keys in `FlowDefinitions` and `FlowDataHandler` if you add or change flows.
