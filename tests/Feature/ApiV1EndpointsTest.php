<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Beneficiary;
use App\Models\Card;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiV1EndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Account $account;
    private Beneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['password' => bcrypt('password')]);
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->account = Account::create([
            'user_id' => $this->user->id,
            'type' => 'current',
            'account_number' => 'ACC'.str_pad($this->user->id, 8, '0', STR_PAD_LEFT),
            'currency' => 'USD',
            'balance' => 1000,
            'status' => 'active',
            'opened_at' => now(),
        ]);
        $this->beneficiary = Beneficiary::create([
            'user_id' => $this->user->id,
            'name' => 'Test Beneficiary',
            'account_number' => '12345678',
            'bank_code' => 'BANK',
            'bank_name' => 'Test Bank',
            'type' => 'same_bank',
        ]);
    }

    private function apiGet(string $uri, array $query = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1'.$uri.($query ? '?'.http_build_query($query) : ''));
    }

    private function apiPost(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1'.$uri, $data);
    }

    private function apiPut(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->putJson('/api/v1'.$uri, $data);
    }

    private function apiDelete(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->deleteJson('/api/v1'.$uri);
    }

    public function test_unauthorized_returns_401(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_profile_show_and_update(): void
    {
        $this->apiGet('/profile')->assertStatus(200)->assertJsonPath('data.email', $this->user->email);
        $this->apiPut('/profile', ['name' => 'Updated Name'])->assertStatus(200);
        $this->user->refresh();
        $this->assertSame('Updated Name', $this->user->name);
    }

    public function test_kyc_endpoints(): void
    {
        $this->apiGet('/kyc')->assertStatus(200);
        $r = $this->apiPost('/kyc', ['status' => 'draft']);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet("/kyc/{$id}")->assertStatus(200);
        $this->apiPost("/kyc/{$id}/documents", ['document_type' => 'id', 'file_path' => '/tmp/doc.pdf'])->assertStatus(201);
    }

    public function test_trusted_devices_endpoints(): void
    {
        $this->apiGet('/trusted-devices')->assertStatus(200);
        $r = $this->apiPost('/trusted-devices', ['device_identifier' => 'dev-1', 'name' => 'My Phone']);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiDelete("/trusted-devices/{$id}")->assertStatus(200);
    }

    public function test_accounts_summary_and_index(): void
    {
        $this->apiGet('/accounts/summary')->assertStatus(200)->assertJsonStructure(['data' => ['accounts', 'total_balance']]);
        $this->apiGet('/accounts')->assertStatus(200);
        $this->apiGet('/accounts/'.$this->account->id)->assertStatus(200);
        $this->apiPut('/accounts/'.$this->account->id, ['nickname' => 'Main', 'is_primary' => true])->assertStatus(200);
    }

    public function test_upcoming_payments(): void
    {
        $this->apiGet('/upcoming-payments')->assertStatus(200);
    }

    public function test_transactions_index_and_show(): void
    {
        $this->apiGet('/transactions')->assertStatus(200);
        $tx = Transaction::create([
            'account_id' => $this->account->id,
            'type' => 'credit',
            'amount' => 100,
            'balance_after' => 1100,
            'reference' => 'REF1',
            'description' => 'Test',
        ]);
        $this->apiGet('/transactions/'.$tx->id)->assertStatus(200);
    }

    public function test_beneficiaries_crud(): void
    {
        $this->apiGet('/beneficiaries')->assertStatus(200);
        $r = $this->apiPost('/beneficiaries', [
            'name' => 'New Ben',
            'type' => 'same_bank',
            'account_number' => '87654321',
            'bank_code' => 'B2',
            'bank_name' => 'Bank Two',
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/beneficiaries/'.$id)->assertStatus(200);
        $this->apiPut('/beneficiaries/'.$id, ['name' => 'Updated Ben'])->assertStatus(200);
        $this->apiDelete('/beneficiaries/'.$id)->assertStatus(200);
    }

    public function test_transfers_index_store_show(): void
    {
        $this->apiGet('/transfers')->assertStatus(200);
        $r = $this->apiPost('/transfers', [
            'from_account_id' => $this->account->id,
            'beneficiary_id' => $this->beneficiary->id,
            'amount' => 10,
            'type' => 'same_bank',
            'reference' => 'Test transfer',
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/transfers/'.$id)->assertStatus(200);
    }

    public function test_recurring_transfers_crud(): void
    {
        $this->apiGet('/recurring-transfers')->assertStatus(200);
        $r = $this->apiPost('/recurring-transfers', [
            'from_account_id' => $this->account->id,
            'beneficiary_id' => $this->beneficiary->id,
            'amount' => 50,
            'frequency' => 'monthly',
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/recurring-transfers/'.$id)->assertStatus(200);
        $this->apiPut('/recurring-transfers/'.$id, ['status' => 'paused'])->assertStatus(200);
        $this->apiDelete('/recurring-transfers/'.$id)->assertStatus(200);
    }

    public function test_bulk_payments(): void
    {
        $this->apiGet('/bulk-payments')->assertStatus(200);
        $r = $this->apiPost('/bulk-payments', [
            'name' => 'Payroll',
            'items' => [
                ['beneficiary_id' => $this->beneficiary->id, 'amount' => 100],
            ],
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/bulk-payments/'.$id)->assertStatus(200);
    }

    public function test_cardless_withdrawals(): void
    {
        $this->apiGet('/cardless-withdrawals')->assertStatus(200);
        $r = $this->apiPost('/cardless-withdrawals', ['account_id' => $this->account->id, 'amount' => 100]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/cardless-withdrawals/'.$id)->assertStatus(200);
    }

    public function test_loan_repayment(): void
    {
        $product = LoanProduct::create(['name' => 'Personal', 'min_amount' => 100, 'max_amount' => 10000, 'interest_rate' => 10, 'tenor_months' => 12]);
        $loan = Loan::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'loan_product_id' => $product->id,
            'amount' => 1000,
            'outstanding_balance' => 800,
            'interest_rate' => 10,
            'status' => 'active',
            'disbursed_at' => now(),
            'maturity_date' => now()->addMonths(12),
        ]);
        $this->apiPost('/loan-repayments', [
            'loan_id' => $loan->id,
            'amount' => 50,
            'account_id' => $this->account->id,
        ])->assertStatus(201);
    }

    public function test_cards_endpoints(): void
    {
        $card = Card::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'last_four' => '4242',
            'expiry_date' => now()->addYears(2),
            'type' => 'physical',
            'status' => 'active',
        ]);
        $this->apiGet('/cards')->assertStatus(200);
        $this->apiGet('/cards/'.$card->id)->assertStatus(200);
        $this->apiPut('/cards/'.$card->id, ['daily_limit_amount' => 500])->assertStatus(200);
        $this->apiPost('/cards/'.$card->id.'/freeze')->assertStatus(200);
        $this->apiPost('/cards/'.$card->id.'/unfreeze')->assertStatus(200);
        $this->apiGet('/cards/'.$card->id.'/spending-limits')->assertStatus(200);
        $this->apiPut('/cards/'.$card->id.'/spending-limits', [
            'limits' => [
                ['category' => 'online', 'limit_amount' => 200],
                ['category' => 'atm', 'limit_amount' => 300],
            ],
        ])->assertStatus(200);
        $this->apiPost('/cards/'.$card->id.'/block')->assertStatus(200);
    }

    public function test_loans_and_loan_applications(): void
    {
        $this->apiGet('/loan-products')->assertStatus(200);
        $this->apiGet('/loans')->assertStatus(200);
        $product = LoanProduct::create(['name' => 'Personal', 'min_amount' => 100, 'max_amount' => 10000, 'interest_rate' => 10, 'tenor_months' => 12]);
        $loan = Loan::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'loan_product_id' => $product->id,
            'amount' => 1000,
            'outstanding_balance' => 1000,
            'interest_rate' => 10,
            'status' => 'active',
            'disbursed_at' => now(),
            'maturity_date' => now()->addMonths(12),
        ]);
        $this->apiGet('/loans/'.$loan->id)->assertStatus(200);
        $this->apiGet('/loans/'.$loan->id.'/repayment-schedule')->assertStatus(200);
        $this->apiGet('/loans/'.$loan->id.'/early-settlement')->assertStatus(200);
        $this->apiPost('/loans/early-settlement', ['loan_id' => $loan->id])->assertStatus(201);
        $this->apiPost('/loans/topup', ['loan_id' => $loan->id, 'amount_requested' => 200])->assertStatus(201);
        $this->apiGet('/loan-applications')->assertStatus(200);
        $r = $this->apiPost('/loan-applications', [
            'loan_product_id' => $product->id,
            'amount_requested' => 2000,
            'tenor_months' => 12,
            'purpose' => 'Home',
        ]);
        $r->assertStatus(201);
        $appId = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/loan-applications/'.$appId)->assertStatus(200);
    }

    public function test_fixed_deposits(): void
    {
        $this->apiGet('/fixed-deposits')->assertStatus(200);
        $r = $this->apiPost('/fixed-deposits', ['account_id' => $this->account->id, 'amount' => 500, 'tenor_months' => 6]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/fixed-deposits/'.$id)->assertStatus(200);
        $this->apiPost('/fixed-deposits/'.$id.'/break')->assertStatus(200);
    }

    public function test_investments_and_reports(): void
    {
        DB::table('investment_products')->insert(['type' => 'mutual_fund', 'name' => 'Fund A', 'min_amount' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->apiGet('/investment-products')->assertStatus(200);
        $this->apiGet('/investments')->assertStatus(200);
        $r = $this->apiPost('/investments', ['investment_product_id' => 1, 'amount' => 200]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/investments/'.$id)->assertStatus(200);
        $this->apiGet('/securities')->assertStatus(200);
        $this->apiGet('/dividends')->assertStatus(200);
        $this->apiGet('/dividend-reinvestment')->assertStatus(200);
        $this->apiPut('/dividend-reinvestment', ['enabled' => true, 'investable_type' => 'App\Models\Investment', 'investable_id' => 1])->assertStatus(200);
    }

    public function test_gift_cards_money_requests_bill_splits(): void
    {
        $this->apiGet('/gift-cards')->assertStatus(200);
        $r = $this->apiPost('/gift-cards', ['amount' => 25, 'recipient_email' => 'gift@example.com']);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/gift-cards/'.$id)->assertStatus(200);

        $this->apiGet('/money-requests')->assertStatus(200);
        $other = User::factory()->create();
        $r = $this->apiPost('/money-requests', ['recipient_id' => $other->id, 'amount' => 10]);
        $r->assertStatus(201);
        $reqId = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/money-requests/'.$reqId)->assertStatus(200);

        // Respond to a request (as recipient): create request FROM other TO current user, then respond
        DB::table('money_requests')->insert([
            'from_user_id' => $other->id,
            'to_user_id' => $this->user->id,
            'amount' => 5,
            'status' => 'pending',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        $reqId2 = DB::table('money_requests')->where('to_user_id', $this->user->id)->where('status', 'pending')->value('id');
        $this->apiPost('/money-requests/'.$reqId2.'/respond', ['action' => 'reject'])->assertStatus(200);

        $this->apiGet('/bill-splits')->assertStatus(200);
        $r = $this->apiPost('/bill-splits', [
            'total_amount' => 100,
            'description' => 'Dinner',
            'members' => [['user_id' => $other->id, 'amount_owed' => 50]],
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/bill-splits/'.$id)->assertStatus(200);
    }

    public function test_notification_preferences(): void
    {
        $this->apiGet('/notification-preferences')->assertStatus(200);
        $this->apiPut('/notification-preferences', ['channel' => 'email', 'transaction_alerts' => true, 'low_balance_threshold' => 50])->assertStatus(200);
    }

    public function test_reports(): void
    {
        $this->apiGet('/reports/custom?from=2024-01-01&to=2024-12-31')->assertStatus(200);
        $this->apiGet('/reports/tax?year=2024')->assertStatus(200);
        $this->apiGet('/reports/interest-certificate?year=2024')->assertStatus(200);
    }

    public function test_service_requests(): void
    {
        $this->apiGet('/service-requests')->assertStatus(200);
        $r = $this->apiPost('/service-requests', ['type' => 'cheque_book', 'details' => []]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/service-requests/'.$id)->assertStatus(200);
    }

    public function test_support_tickets_branches_atms_faqs(): void
    {
        $this->apiGet('/support-tickets')->assertStatus(200);
        $r = $this->apiPost('/support-tickets', ['subject' => 'Help me']);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/support-tickets/'.$id)->assertStatus(200);

        $this->apiGet('/branches')->assertStatus(200);
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'Main Branch',
            'address' => '123 High St',
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        $this->apiGet('/branches/'.$branchId)->assertStatus(200);

        $this->apiGet('/atms')->assertStatus(200);
        $atmId = DB::table('atms')->insertGetId([
            'address' => '456 Main St',
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        $this->apiGet('/atms/'.$atmId)->assertStatus(200);

        $this->apiGet('/faqs')->assertStatus(200);
    }

    public function test_appointments(): void
    {
        $this->apiGet('/appointments')->assertStatus(200);
        $r = $this->apiPost('/appointments', [
            'type' => 'callback',
            'scheduled_at' => now()->addDays(1)->toIso8601String(),
            'notes' => 'Call back',
        ]);
        $r->assertStatus(201);
        $id = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/appointments/'.$id)->assertStatus(200);
        $this->apiDelete('/appointments/'.$id)->assertStatus(200);
    }

    public function test_security_endpoints(): void
    {
        $this->apiGet('/security/login-history')->assertStatus(200);
        $this->apiGet('/security/transaction-limits')->assertStatus(200);
        $this->apiPut('/security/transaction-limits', ['per_transaction_limit' => 1000, 'daily_limit' => 5000])->assertStatus(200);
        $this->apiPost('/security/fraud-report', ['description' => 'Suspicious activity'])->assertStatus(201);
        $this->apiPost('/security/change-password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(200);
        $this->apiPost('/security/biometric', ['enabled' => true])->assertStatus(200);
        // change-pin: 422 when no current PIN set, or 200 when valid
        $pinRes = $this->apiPost('/security/change-pin', [
            'current_pin' => '0000',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
        $this->assertContains($pinRes->status(), [200, 422]);
    }

    public function test_corporate_and_trade_endpoints(): void
    {
        $this->apiGet('/letter-of-credit')->assertStatus(200);
        $this->apiGet('/bank-guarantees')->assertStatus(200);
        $this->apiGet('/forex-bookings')->assertStatus(200);
        $this->apiGet('/corporate/accounts')->assertStatus(200);

        // Create corporate account (primary = current user) and corporate_user link so we can POST LC/BG/Forex
        $corpId = DB::table('corporate_accounts')->insertGetId([
            'primary_user_id' => $this->user->id,
            'company_name' => 'Test Corp',
            'status' => 'active',
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        $corpUserId = DB::table('corporate_users')->insertGetId([
            'corporate_account_id' => $corpId,
            'user_id' => $this->user->id,
            'role' => 'admin',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $r = $this->apiPost('/letter-of-credit', ['corporate_account_id' => $corpId, 'amount' => 5000]);
        $r->assertStatus(201);
        $lcId = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/letter-of-credit/'.$lcId)->assertStatus(200);

        $r = $this->apiPost('/bank-guarantees', ['corporate_account_id' => $corpId, 'amount' => 3000]);
        $r->assertStatus(201);
        $bgId = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/bank-guarantees/'.$bgId)->assertStatus(200);

        $r = $this->apiPost('/forex-bookings', [
            'corporate_account_id' => $corpId,
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'amount' => 1000,
            'rate' => 0.92,
        ]);
        $r->assertStatus(201);
        $fxId = $r->json('data')->id ?? $r->json('data')['id'];
        $this->apiGet('/forex-bookings/'.$fxId)->assertStatus(200);

        $this->apiGet('/corporate/accounts/'.$corpId.'/sub-users')->assertStatus(200);
        $subUser = User::factory()->create();
        $r = $this->apiPost('/corporate/accounts/'.$corpId.'/sub-users', ['user_id' => $subUser->id, 'role' => 'viewer']);
        $r->assertStatus(201);
        $subCorpUserId = $r->json('data')->id ?? $r->json('data')['id'];

        $this->apiGet('/corporate/accounts/'.$corpId.'/approval-workflows')->assertStatus(200);
        $wfId = DB::table('approval_workflows')->insertGetId([
            'corporate_account_id' => $corpId,
            'name' => 'Transfer approval',
            'type' => 'transfer',
            'is_active' => true,
            'created_at' => $now = now(),
            'updated_at' => $now,
        ]);
        $this->apiGet('/corporate/accounts/'.$corpId.'/approval-rights')->assertStatus(200);
        $this->apiPost('/corporate/approval-rights', [
            'corporate_account_id' => $corpId,
            'corporate_user_id' => $subCorpUserId,
            'approval_workflow_id' => $wfId,
            'max_approvable_amount' => 10000,
        ])->assertStatus(200);
    }
}
