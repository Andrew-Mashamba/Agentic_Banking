<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates comprehensive default settings for the restaurant
     */
    public function run(): void
    {
        $defaultSettings = [
            // Business Information
            ['key' => 'business_name', 'value' => 'SeaCliff Restaurant & Bar', 'type' => 'string'],
            ['key' => 'business_address', 'value' => '123 Ocean View Drive, Dar es Salaam, Tanzania', 'type' => 'string'],
            ['key' => 'business_phone', 'value' => '+255 22 123 4567', 'type' => 'string'],
            ['key' => 'business_email', 'value' => 'info@seacliff.com', 'type' => 'string'],
            ['key' => 'business_website', 'value' => 'https://www.seacliff.com', 'type' => 'string'],

            // Operating Hours
            ['key' => 'opening_hours', 'value' => '09:00', 'type' => 'string'],
            ['key' => 'closing_hours', 'value' => '23:00', 'type' => 'string'],
            ['key' => 'weekday_hours', 'value' => '09:00-23:00', 'type' => 'string'],
            ['key' => 'weekend_hours', 'value' => '08:00-00:00', 'type' => 'string'],

            // Tax and Financial Settings
            ['key' => 'tax_rate', 'value' => '18', 'type' => 'integer'],
            ['key' => 'service_charge_rate', 'value' => '0', 'type' => 'integer'],
            ['key' => 'currency', 'value' => 'TZS', 'type' => 'string'],
            ['key' => 'currency_symbol', 'value' => 'TSh', 'type' => 'string'],

            // Loyalty Program Settings
            ['key' => 'loyalty_points_enabled', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'loyalty_points_per_100', 'value' => '1', 'type' => 'integer'],
            ['key' => 'loyalty_points_redemption_rate', 'value' => '100', 'type' => 'integer'],

            // Order Settings
            ['key' => 'order_prefix', 'value' => 'ORD', 'type' => 'string'],
            ['key' => 'enable_table_reservations', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'enable_takeaway', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'enable_delivery', 'value' => '0', 'type' => 'boolean'],

            // Kitchen Settings
            ['key' => 'auto_print_kitchen_orders', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'kitchen_prep_time_buffer', 'value' => '5', 'type' => 'integer'],

            // Notification Settings
            ['key' => 'enable_sms_notifications', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'enable_email_notifications', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'admin_notification_email', 'value' => 'admin@seacliff.com', 'type' => 'string'],

            // Receipt Settings
            ['key' => 'receipt_footer_text', 'value' => 'Thank you for dining with us! Please visit again.', 'type' => 'string'],
            ['key' => 'receipt_show_loyalty_points', 'value' => '1', 'type' => 'boolean'],

            // System Settings
            ['key' => 'timezone', 'value' => 'Africa/Dar_es_Salaam', 'type' => 'string'],
            ['key' => 'date_format', 'value' => 'd/m/Y', 'type' => 'string'],
            ['key' => 'time_format', 'value' => 'H:i', 'type' => 'string'],

            // WhatsApp compliance (override via admin/settings; defaults match config)
            ['key' => 'whatsapp_consent_required', 'value' => '1', 'type' => 'boolean'],
            ['key' => 'whatsapp_retention_days_conversations', 'value' => '365', 'type' => 'integer'],
            ['key' => 'whatsapp_pending_task_ttl_days', 'value' => '90', 'type' => 'integer'],
            ['key' => 'whatsapp_retention_days_attachments', 'value' => '90', 'type' => 'integer'],
            ['key' => 'whatsapp_audit_retention_years', 'value' => '7', 'type' => 'integer'],
            ['key' => 'whatsapp_pending_task_max_per_user', 'value' => '5', 'type' => 'integer'],
            ['key' => 'whatsapp_retention_days_memory', 'value' => '365', 'type' => 'integer'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }

        $this->command->info('✓ Settings seeded successfully!');
        $this->command->info('  - Business information configured');
        $this->command->info('  - Tax rate: 18%');
        $this->command->info('  - Operating hours: 09:00-23:00');
        $this->command->info('  - Loyalty program enabled');
        $this->command->info('  - '.count($defaultSettings).' settings configured');
    }
}
