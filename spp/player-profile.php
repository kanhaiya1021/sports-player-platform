<?php
/**
 * Player Profile Template
 * Displays an individual player's public profile page
 *
 * @package Sports Player Platform
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="spp-single-player">
    <div class="spp-player-header">
        <?php
        // Player thumbnail/featured image
        if ( has_post_thumbnail() ) {
            the_post_thumbnail( 'full', [ 'class' => 'spp-player-fullwidth-img' ] );
        }
        ?>
        <div class="spp-player-info">
            <h1 class="spp-player-name"><?php the_title(); ?></h1>
            <?php
            // Get public meta fields
            $position = get_post_meta( get_the_ID(), 'player_position', true );
            $sport = get_post_meta( get_the_ID(), 'sport', true );
            $grad_year = get_post_meta( get_the_ID(), 'graduation_year', true );
            $height = get_post_meta( get_the_ID(), 'player_height', true );
            $school = get_post_meta( get_the_ID(), 'school', true );
            ?>

            <?php if ( ! empty( $position ) ) : ?>
                <p class="spp-meta-row">
                    <span class="spp-meta-label"><?php esc_html_e( 'Position', 'sports-player-platform' ); ?></span>
                    <span class="spp-meta-value"><?php echo esc_html( $position ); ?></span>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $sport ) ) : ?>
                <p class="spp-meta-row">
                    <span class="spp-meta-label"><?php esc_html_e( 'Sport', 'sports-player-platform' ); ?></span>
                    <span class="spp-meta-value"><?php echo esc_html( $sport ); ?></span>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $grad_year ) ) : ?>
                <p class="spp-meta-row">
                    <span class="spp-meta-label"><?php esc_html_e( 'Graduation Year', 'sports-player-platform' ); ?></span>
                    <span class="spp-meta-value"><?php echo esc_html( $grad_year ); ?></span>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $height ) ) : ?>
                <p class="spp-meta-row">
                    <span class="spp-meta-label"><?php esc_html_e( 'Height', 'sports-player-platform' ); ?></span>
                    <span class="spp-meta-value"><?php echo esc_html( $height ) . 'cm'; ?></span>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $school ) ) : ?>
                <p class="spp-meta-row">
                    <span class="spp-meta-label"><?php esc_html_e( 'School', 'sports-player-platform' ); ?></span>
                    <span class="spp-meta-value"><?php echo esc_html( $school ); ?></span>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="spp-player-content">
        <?php the_content(); ?>
    </div>
</div>

<style>
.spp-single-player {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
}

.spp-player-header {
    text-align: center;
    margin-bottom: 40px;
}

.spp-player-fullwidth-img {
    width: 100%;
    height: auto;
    border-radius: 8px;
    margin-bottom: 20px;
}

.spp-player-name {
    font-size: 28px;
    margin-bottom: 10px;
}

.spp-meta-row {
    margin-bottom: 10px;
    line-height: 1.6;
}

.spp-meta-label {
    font-weight: bold;
    color: #555;
    display: inline-block;
    width: 120px;
}

.spp-meta-value {
    color: #333;
}

.spp-player-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
</style>