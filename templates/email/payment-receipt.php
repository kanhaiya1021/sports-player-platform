<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<h2 style="margin-top:0; color:#111827;"><?php esc_html_e('Payment Receipt', 'sports-player-platform'); ?></h2>
<p><?php printf(esc_html__('Hello %s,', 'sports-player-platform'), esc_html($data['name'] ?? '')); ?></p>
<p><?php esc_html_e('Thank you for your payment. Here are your transaction details:', 'sports-player-platform'); ?></p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse:collapse; margin:16px 0; border:1px solid #e5e7eb;">
    <tr style="background-color:#f9fafb;">
        <td style="border:1px solid #e5e7eb;"><strong><?php esc_html_e('Plan', 'sports-player-platform'); ?></strong></td>
        <td style="border:1px solid #e5e7eb;"><?php echo esc_html($data['plan_name'] ?? ''); ?></td>
    </tr>
    <tr>
        <td style="border:1px solid #e5e7eb;"><strong><?php esc_html_e('Amount', 'sports-player-platform'); ?></strong></td>
        <td style="border:1px solid #e5e7eb;"><?php echo esc_html(($data['currency'] ?? 'USD') . ' ' . number_format((float) ($data['amount'] ?? 0), 2)); ?></td>
    </tr>
    <tr style="background-color:#f9fafb;">
        <td style="border:1px solid #e5e7eb;"><strong><?php esc_html_e('Gateway', 'sports-player-platform'); ?></strong></td>
        <td style="border:1px solid #e5e7eb;"><?php echo esc_html($data['gateway'] ?? ''); ?></td>
    </tr>
    <tr>
        <td style="border:1px solid #e5e7eb;"><strong><?php esc_html_e('Transaction ID', 'sports-player-platform'); ?></strong></td>
        <td style="border:1px solid #e5e7eb;"><code><?php echo esc_html($data['transaction_id'] ?? ''); ?></code></td>
    </tr>
</table>
