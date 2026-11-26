<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($subject); ?></title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f9; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background-color:#0f172a; padding:24px 32px; text-align:center;">
                            <h1 style="margin:0; font-size:20px; font-weight:700; color:#ffffff;"><?php echo esc_html($site_name); ?></h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px; font-size:15px; line-height:1.6; color:#374151;">
