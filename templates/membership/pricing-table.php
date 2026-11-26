<?php
/**
 * Pricing Table Template ([spp_pricing_table])
 *
 * @var array  $plans
 * @var string $current_plan
 * @var bool   $is_logged_in
 * @var string $ajax_url
 * @var string $nonce
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="spp-pricing-wrap">
    <?php if (isset($_GET['spp_payment']) && $_GET['spp_payment'] === 'cancelled'): ?>
        <div class="spp-notice spp-notice-info">
            <p><?php esc_html_e('Checkout was cancelled. You can choose a plan below whenever you are ready.', 'sports-player-platform'); ?></p>
        </div>
    <?php endif; ?>

    <div id="spp-pricing-error" class="spp-notice spp-notice-error" style="display:none;"></div>

    <div class="spp-pricing-grid">
        <?php foreach ($plans as $plan_id => $plan): ?>
            <?php
            $is_popular = !empty($plan['popular']);
            $is_current = $is_logged_in && $current_plan === $plan_id;
            ?>
            <div class="spp-pricing-card <?php echo $is_popular ? 'is-popular' : ''; ?> <?php echo $is_current ? 'is-current' : ''; ?>">
                <?php if ($is_popular): ?>
                    <span class="spp-popular-badge"><?php esc_html_e('Most Popular', 'sports-player-platform'); ?></span>
                <?php endif; ?>

                <h3><?php echo esc_html($plan['name']); ?></h3>
                <div class="spp-price">
                    <span class="spp-price-amount">$<?php echo esc_html(number_format((float) $plan['price'], 0)); ?></span>
                    <span class="spp-price-interval"><?php echo esc_html($plan['interval_label']); ?></span>
                </div>

                <ul class="spp-plan-features">
                    <?php foreach ($plan['features'] as $feature): ?>
                        <li>✓ <?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>

                <div class="spp-plan-cta">
                    <?php if ($is_current): ?>
                        <span class="spp-btn spp-btn-disabled"><?php esc_html_e('Current Plan', 'sports-player-platform'); ?></span>
                    <?php elseif ($plan_id === 'free'): ?>
                        <a href="<?php echo esc_url($is_logged_in ? home_url('/dashboard/') : home_url('/register/')); ?>" class="spp-btn spp-btn-secondary">
                            <?php echo $is_logged_in ? esc_html__('Go to Dashboard', 'sports-player-platform') : esc_html__('Get Started Free', 'sports-player-platform'); ?>
                        </a>
                    <?php else: ?>
                        <button type="button" class="spp-btn spp-btn-primary spp-checkout-btn" data-plan="<?php echo esc_attr($plan_id); ?>">
                            <?php esc_html_e('Upgrade Now', 'sports-player-platform'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="spp-comparison-wrap">
        <h3><?php esc_html_e('Plan Comparison', 'sports-player-platform'); ?></h3>
        <table class="spp-table spp-comparison-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Feature', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Free', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Monthly Pro', 'sports-player-platform'); ?></th>
                    <th><?php esc_html_e('Annual All-Access', 'sports-player-platform'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Browse Player Directory', 'sports-player-platform'); ?></td>
                    <td>✓</td><td>✓</td><td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('View Contact Info', 'sports-player-platform'); ?></td>
                    <td>—</td><td>✓</td><td>✓</td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Direct Messaging', 'sports-player-platform'); ?></td>
                    <td>—</td><td>50 / mo</td><td><?php esc_html_e('Unlimited', 'sports-player-platform'); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Recruiting Lists', 'sports-player-platform'); ?></td>
                    <td>—</td><td>5</td><td><?php esc_html_e('Unlimited', 'sports-player-platform'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.spp-pricing-wrap { max-width: 1080px; margin: 32px auto; padding: 0 16px; }
.spp-pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 40px; }
.spp-pricing-card { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 28px 24px; display: flex; flex-direction: column; }
.spp-pricing-card.is-popular { border: 2px solid #1a56db; box-shadow: 0 8px 24px rgba(26, 86, 219, 0.12); }
.spp-pricing-card.is-current { background: #f8fafc; }
.spp-popular-badge { position: absolute; top: -12px; right: 20px; background: #f59e0b; color: #111827; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px; }
.spp-price { margin: 12px 0 20px; }
.spp-price-amount { font-size: 36px; font-weight: 800; color: #111827; }
.spp-price-interval { color: #6b7280; font-size: 14px; }
.spp-plan-features { list-style: none; padding: 0; margin: 0 0 24px; flex: 1; }
.spp-plan-features li { padding: 6px 0; color: #374151; }
.spp-btn-disabled { display: inline-block; padding: 10px 16px; border-radius: 6px; background: #e5e7eb; color: #6b7280; font-weight: 600; }
.spp-btn-secondary { display: inline-block; padding: 10px 16px; border-radius: 6px; background: #111827; color: #fff; text-decoration: none; font-weight: 600; text-align: center; }
.spp-checkout-btn { width: 100%; padding: 10px 16px; border-radius: 6px; background: #1a56db; color: #fff; border: none; font-weight: 600; cursor: pointer; }
</style>

<script>
document.querySelectorAll('.spp-checkout-btn').forEach((btn) => {
    btn.addEventListener('click', async () => {
        const plan = btn.getAttribute('data-plan');
        const errBox = document.getElementById('spp-pricing-error');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js(__('Redirecting...', 'sports-player-platform')); ?>';
        if (errBox) errBox.style.display = 'none';

        const body = new FormData();
        body.append('action', 'spp_create_checkout');
        body.append('nonce', '<?php echo esc_js($nonce); ?>');
        body.append('plan', plan);

        try {
            const res = await fetch('<?php echo esc_url($ajax_url); ?>', { method: 'POST', body });
            const data = await res.json();
            if (data.success && data.data?.checkout_url) {
                window.location.href = data.data.checkout_url;
                return;
            }
            if (data.data?.redirect_url) {
                window.location.href = data.data.redirect_url;
                return;
            }
            if (errBox) {
                errBox.style.display = 'block';
                errBox.textContent = data.data?.message || '<?php echo esc_js(__('Checkout failed.', 'sports-player-platform')); ?>';
            }
        } catch (e) {
            if (errBox) {
                errBox.style.display = 'block';
                errBox.textContent = '<?php echo esc_js(__('Network error. Please try again.', 'sports-player-platform')); ?>';
            }
        } finally {
            btn.disabled = false;
            btn.textContent = origText;
        }
    });
});
</script>
